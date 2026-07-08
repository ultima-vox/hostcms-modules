<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Finds locally-referenced <link rel="stylesheet"> and <script src="...">
 * tags in already-rendered HTML output and replaces consecutive runs of
 * them with a single combined, minified file — entirely server-side,
 * without touching template markup (templates keep using whatever they
 * already use, e.g. Core_Page::instance()->showCss()).
 *
 * Safety rules baked in, and why:
 *
 * - Only *consecutive* tags are combined. A run broken by an inline
 *   <script> or other markup becomes two separate groups instead of one,
 *   because combining across a gap could change execution order relative
 *   to inline code that depends on a preceding script having already run.
 * - Only local files (root-relative paths starting with "/", resolved
 *   and checked to actually exist under CMS_FOLDER) are combined; external
 *   URLs, protocol-relative ("//cdn...") URLs, and page-relative paths
 *   (no leading "/") are left completely untouched.
 * - <script> tags with async, defer, type="module", nomodule or integrity
 *   are never combined: those attributes change load/execution semantics,
 *   or rely on Subresource Integrity hashes that concatenation would
 *   invalidate.
 * - CSS url(...) references are rewritten to root-absolute paths before
 *   concatenation, so a combined file living in a different directory
 *   doesn't break relative references to background images/fonts.
 * - Bundles are cached to disk under a name derived from a hash of every
 *   source file's (path, mtime), so editing any source file produces a
 *   new bundle (and a new URL) automatically instead of serving stale
 *   content.
 * - Stats are recorded only when a bundle is actually (re)built (cache
 *   miss) via Optimize_Settings::addBundleSavings() — never on every page
 *   view that merely reuses an existing bundle.
 */
class Optimize_Assets
{
    /**
     * Directory generated bundles are written to. Shared with
     * Optimize_Settings's state files (upload/ is web-servable, which is
     * exactly what the bundles need to be); the state files themselves are
     * protected by the .htaccess Optimize_Settings::ensureDir() writes.
     */
    protected static function cacheDir()
    {
        return CMS_FOLDER . 'upload/optimize_cache/';
    }

    protected static function cacheUrl()
    {
        return '/upload/optimize_cache/';
    }

    // ------------------------------------------------------------------
    // CSS
    // ------------------------------------------------------------------

    public static function combineCss($html, $siteId)
    {
        return preg_replace_callback(
            '/(?:\s*<link\b[^>]*>\s*)+/i',
            function ($m) use ($siteId) {
                return self::combineCssGroup($m[0], $siteId);
            },
            $html
        );
    }

    protected static function combineCssGroup($groupHtml, $siteId)
    {
        preg_match_all('/<link\b[^>]*>/i', $groupHtml, $m);
        $tags = $m[0];

        $output = '';
        $pending = array();
        $pendingMedia = NULL;

        $flush = function () use (&$pending, &$pendingMedia, &$output, $siteId) {
            if (count($pending) >= 2) {
                $output .= self::writeCssBundle($pending, $pendingMedia, $siteId);
            } elseif (count($pending) === 1) {
                $output .= $pending[0]['tag'];
            }
            $pending = array();
            $pendingMedia = NULL;
        };

        foreach ($tags as $tag) {
            if (stripos($tag, 'stylesheet') === FALSE) {
                $flush();
                $output .= $tag;
                continue;
            }

            $href = self::attr($tag, 'href');
            $media = self::attr($tag, 'media');
            $media = $media === NULL ? 'all' : $media;
            $path = $href !== NULL ? self::localPath($href) : NULL;

            $skip = $path === NULL
                || stripos($tag, 'data-optimize-skip') !== FALSE
                || stripos($tag, 'disabled') !== FALSE;

            if ($skip) {
                $flush();
                $output .= $tag;
                continue;
            }

            if ($pendingMedia !== NULL && $pendingMedia !== $media) {
                $flush();
            }

            $pendingMedia = $media;
            $pending[] = array('tag' => $tag, 'path' => $path, 'href' => $href);
        }

        $flush();

        return $output;
    }

    protected static function writeCssBundle($files, $media, $siteId)
    {
        $info = self::bundleInfo($files, 'css');

        if ($info === NULL) {
            $out = '';
            foreach ($files as $f) {
                $out .= $f['tag'];
            }
            return $out;
        }

        list($cacheFile, $cacheUrl) = $info;

        if (!is_file($cacheFile)) {
            $originalSize = 0;
            $combined = '';

            foreach ($files as $f) {
                $css = file_get_contents($f['path']);
                $originalSize += strlen($css);
                $css = self::rewriteCssUrls($css, dirname($f['href']));
                $combined .= '/* ' . $f['href'] . " */\n" . self::minifyCss($css) . "\n";
            }

            if (@file_put_contents($cacheFile, $combined) !== FALSE) {
                Optimize_Settings::addBundleSavings(
                    'css',
                    $originalSize,
                    strlen($combined),
                    count($files) - 1,
                    $siteId
                );
            }
        }

        $mediaAttr = ($media && $media !== 'all') ? ' media="' . htmlspecialchars($media, ENT_QUOTES) . '"' : '';

        return '<link rel="stylesheet" href="' . $cacheUrl . '"' . $mediaAttr . '>';
    }

    /**
     * Strip CSS comments and collapse whitespace. Safe for CSS specifically
     * because, unlike JS, CSS has no line comments and no string-embedded
     * "/*"-like ambiguity worth guarding against in practice.
     */
    protected static function minifyCss($css)
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        return trim($css);
    }

    /**
     * Rewrite relative url(...) references (images, fonts) to root-
     * absolute paths based on the original CSS file's own directory, so
     * they keep working once moved into the combined bundle.
     */
    protected static function rewriteCssUrls($css, $baseDir)
    {
        return preg_replace_callback(
            '/url\(\s*([\'"]?)(?!(?:https?:)?\/\/|data:)([^\'")]+)\1\s*\)/i',
            function ($m) use ($baseDir) {
                $quote = $m[1];
                $path = trim($m[2]);

                if ($path === '' || substr($path, 0, 1) === '/') {
                    return 'url(' . $quote . $path . $quote . ')';
                }

                return 'url(' . $quote . self::resolvePath($baseDir, $path) . $quote . ')';
            },
            $css
        );
    }

    // ------------------------------------------------------------------
    // JS
    // ------------------------------------------------------------------

    public static function combineJs($html, $siteId)
    {
        return preg_replace_callback(
            '/(?:\s*<script\b[^>]*><\/script>\s*)+/i',
            function ($m) use ($siteId) {
                return self::combineJsGroup($m[0], $siteId);
            },
            $html
        );
    }

    protected static function combineJsGroup($groupHtml, $siteId)
    {
        preg_match_all('/<script\b[^>]*><\/script>/i', $groupHtml, $m);
        $tags = $m[0];

        $output = '';
        $pending = array();

        $flush = function () use (&$pending, &$output, $siteId) {
            if (count($pending) >= 2) {
                $output .= self::writeJsBundle($pending, $siteId);
            } elseif (count($pending) === 1) {
                $output .= $pending[0]['tag'];
            }
            $pending = array();
        };

        foreach ($tags as $tag) {
            $unsafe = (bool) preg_match(
                '/\b(async|defer|nomodule|integrity)\b|type\s*=\s*["\']?\s*module/i',
                $tag
            );
            $src = self::attr($tag, 'src');
            $path = ($src !== NULL && !$unsafe) ? self::localPath($src) : NULL;

            $skip = $path === NULL || stripos($tag, 'data-optimize-skip') !== FALSE;

            if ($skip) {
                $flush();
                $output .= $tag;
                continue;
            }

            $pending[] = array('tag' => $tag, 'path' => $path, 'href' => $src);
        }

        $flush();

        return $output;
    }

    protected static function writeJsBundle($files, $siteId)
    {
        $info = self::bundleInfo($files, 'js');

        if ($info === NULL) {
            $out = '';
            foreach ($files as $f) {
                $out .= $f['tag'];
            }
            return $out;
        }

        list($cacheFile, $cacheUrl) = $info;

        if (!is_file($cacheFile)) {
            $originalSize = 0;
            $combined = '';

            foreach ($files as $f) {
                $js = file_get_contents($f['path']);
                $originalSize += strlen($js);
                // ";\n" between files guards against ASI pitfalls where the
                // end of one file and the start of the next would otherwise
                // parse as a single (broken) statement.
                $combined .= '/* ' . $f['href'] . " */\n" . self::minifyJs($js) . "\n;\n";
            }

            if (@file_put_contents($cacheFile, $combined) !== FALSE) {
                Optimize_Settings::addBundleSavings(
                    'js',
                    $originalSize,
                    strlen($combined),
                    count($files) - 1,
                    $siteId
                );
            }
        }

        return '<script src="' . $cacheUrl . '"></script>';
    }

    /**
     * Deliberately conservative JS "minification": strips only whole-line
     * comments and blank lines, and trims indentation. Does NOT touch
     * in-line trailing comments, string contents, regex literals or
     * template strings — telling those apart from a real comment reliably
     * requires a full JS tokenizer, and a wrong guess there breaks the
     * script (exactly what happened in the previous version of this
     * module). Still removes a meaningful amount of dead weight: comment
     * blocks, blank lines, leading indentation.
     */
    protected static function minifyJs($code)
    {
        $lines = preg_split('/\r\n|\r|\n/', $code);
        $out = array();
        $inBlockComment = FALSE;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($inBlockComment) {
                $pos = strpos($trimmed, '*/');
                if ($pos !== FALSE) {
                    $trimmed = trim(substr($trimmed, $pos + 2));
                    $inBlockComment = FALSE;
                } else {
                    continue;
                }
            }

            if ($trimmed === '') {
                continue;
            }

            if (substr($trimmed, 0, 2) === '/*') {
                $end = strpos($trimmed, '*/', 2);
                if ($end !== FALSE) {
                    $trimmed = trim(substr($trimmed, $end + 2));
                    if ($trimmed === '') {
                        continue;
                    }
                } else {
                    $inBlockComment = TRUE;
                    continue;
                }
            }

            if (substr($trimmed, 0, 2) === '//') {
                continue;
            }

            $out[] = $trimmed;
        }

        return implode("\n", $out);
    }

    // ------------------------------------------------------------------
    // Shared helpers
    // ------------------------------------------------------------------

    protected static function attr($tag, $name)
    {
        if (preg_match('/\b' . $name . '\s*=\s*(["\'])(.*?)\1/i', $tag, $m)) {
            return $m[2];
        }
        return NULL;
    }

    /**
     * Resolve an href/src to a real local filesystem path, or NULL if it's
     * external, page-relative (unsupported — see class docblock), or
     * doesn't actually exist under CMS_FOLDER.
     */
    protected static function localPath($href)
    {
        $href = trim($href);

        if ($href === '' || strpos($href, '//') === 0 || preg_match('#^[a-z][a-z0-9+.\-]*:#i', $href)) {
            return NULL; // protocol-relative or absolute URL (http:, https:, data:, ...)
        }

        if (substr($href, 0, 1) !== '/') {
            return NULL; // page-relative path, not safely resolvable from here
        }

        $path = preg_replace('/[?#].*$/', '', $href);
        $realRoot = realpath(CMS_FOLDER);
        $realPath = realpath(CMS_FOLDER . ltrim($path, '/'));

        if ($realPath === FALSE || $realRoot === FALSE || strpos($realPath, $realRoot) !== 0) {
            return NULL; // missing file, or resolved outside the site root
        }

        return $realPath;
    }

    /**
     * Resolve a relative CSS url() path against the directory of the CSS
     * file that referenced it, normalizing "..", and return a root-
     * absolute path.
     */
    protected static function resolvePath($baseDir, $relative)
    {
        $combined = rtrim($baseDir, '/') . '/' . $relative;
        $parts = array();

        foreach (explode('/', $combined) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $segment;
        }

        return '/' . implode('/', $parts);
    }

    protected static function bundleInfo($files, $ext)
    {
        $dir = self::cacheDir();

        if (!is_dir($dir) && !@mkdir($dir, 0755, TRUE) && !is_dir($dir)) {
            return NULL;
        }

        $sig = '';
        foreach ($files as $f) {
            $sig .= $f['path'] . '|' . @filemtime($f['path']) . ';';
        }

        $hash = substr(md5($sig), 0, 16);
        $filename = 'bundle_' . $hash . '.' . $ext;

        return array($dir . $filename, self::cacheUrl() . $filename);
    }
}
