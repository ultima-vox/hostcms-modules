<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

class Optimize_Assets
{
    protected static function cacheDir()
    {
        return CMS_FOLDER . 'upload/optimize_cache/';
    }

    protected static function cacheUrl()
    {
        return '/upload/optimize_cache/';
    }

    public static function clearBundles($type = NULL)
    {
        $dir = self::cacheDir();
        $deleted = 0;

        if (!is_dir($dir)) {
            return 0;
        }

        $patterns = array();

        if ($type === 'css') {
            $patterns[] = 'combine*.css';
            $patterns[] = 'bundle_*.css'; // backward compatibility
        } elseif ($type === 'js') {
            $patterns[] = 'combine*.js';
            $patterns[] = 'bundle_*.js';
        } else {
            $patterns[] = 'combine*.css';
            $patterns[] = 'combine*.js';
            $patterns[] = 'bundle_*.css';
            $patterns[] = 'bundle_*.js';
        }

        foreach ($patterns as $pattern) {
            foreach (glob($dir . $pattern) as $file) {
                if (is_file($file) && @unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    public static function combineCss($html, $siteId, $minify = FALSE)
    {
        return preg_replace_callback(
            '/(?:\s*<link\b[^>]*>\s*)+/i',
            function ($m) use ($siteId, $minify) {
                return self::combineCssGroup($m[0], $siteId, $minify);
            },
            $html
        );
    }

    protected static function combineCssGroup($groupHtml, $siteId, $minify)
    {
        preg_match_all('/<link\b[^>]*>/i', $groupHtml, $m);
        $tags = $m[0];

        $output = '';
        $pending = array();
        $pendingMedia = NULL;

        $flush = function () use (&$pending, &$pendingMedia, &$output, $siteId, $minify) {
            if (count($pending) >= 2) {
                $output .= self::writeCssBundle($pending, $pendingMedia, $siteId, $minify);
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

    protected static function writeCssBundle($files, $media, $siteId, $minify)
    {
        $info = self::bundleInfo($files, 'css', $minify ? 'min' : 'raw');

        if ($info === NULL) {
            $out = '';
            foreach ($files as $f) {
                $out .= $f['tag'];
            }
            return $out;
        }

        list($cacheFile, $cacheUrlWithQuery) = $info;

        if (!is_file($cacheFile)) {
            $originalSize = 0;
            $combined = '';

            foreach ($files as $f) {
                $css = file_get_contents($f['path']);
                $originalSize += strlen($css);
                $css = self::rewriteCssUrls($css, dirname($f['href']));
                $css = $minify ? self::minifyCssImproved($css) : trim($css);
                $combined .= '/* ' . $f['href'] . " */\n" . $css . "\n";
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
        return '<link rel="stylesheet" href="' . $cacheUrlWithQuery . '"' . $mediaAttr . '>';
    }

    protected static function minifyCssImproved($css)
    {
        // Remove comments
        $css = preg_replace('#/\*.*?\*/#s', '', $css);

        // Remove whitespace
        $css = preg_replace('/\s+/', ' ', $css);

        // Optimize common patterns
        $css = preg_replace('/\s*([{}:;,>])\s*/', '$1', $css);
        $css = preg_replace('/;}/', '}', $css);
        $css = preg_replace('/([:,])\s+/', '$1', $css);

        // Remove units from zero values
        $css = preg_replace('/\b0(px|em|rem|%|vh|vw)\b/', '0', $css);

        return trim($css);
    }

    protected static function rewriteCssUrls($css, $baseDir)
    {
        // More robust regex for url()
        return preg_replace_callback(
            '/url\(\s*([\'"]?)(?!(?:https?:)?\/\/|data:|#|%23)([^\'"\)]+?)\1\s*\)/i',
            function ($m) use ($baseDir) {
                $quote = $m[1];
                $path = trim($m[2]);

                if ($path === '' || substr($path, 0, 1) === '/') {
                    return 'url(' . $quote . $path . $quote . ')';
                }

                $resolved = self::resolvePath($baseDir, $path);
                return 'url(' . $quote . $resolved . $quote . ')';
            },
            $css
        );
    }

    public static function combineJs($html, $siteId, $minify = FALSE)
    {
        return preg_replace_callback(
            '/(?:\s*<script\b[^>]*><\/script>\s*)+/i',
            function ($m) use ($siteId, $minify) {
                return self::combineJsGroup($m[0], $siteId, $minify);
            },
            $html
        );
    }

    protected static function combineJsGroup($groupHtml, $siteId, $minify)
    {
        preg_match_all('/<script\b[^>]*><\/script>/i', $groupHtml, $m);
        $tags = $m[0];

        $output = '';
        $pending = array();

        $flush = function () use (&$pending, &$output, $siteId, $minify) {
            if (count($pending) >= 2) {
                $output .= self::writeJsBundle($pending, $siteId, $minify);
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

    protected static function writeJsBundle($files, $siteId, $minify)
    {
        $info = self::bundleInfo($files, 'js', $minify ? 'min' : 'raw');

        if ($info === NULL) {
            $out = '';
            foreach ($files as $f) {
                $out .= $f['tag'];
            }
            return $out;
        }

        list($cacheFile, $cacheUrlWithQuery) = $info;

        if (!is_file($cacheFile)) {
            $originalSize = 0;
            $combined = '';

            foreach ($files as $f) {
                $js = file_get_contents($f['path']);
                $originalSize += strlen($js);
                $js = $minify ? self::minifyJsImproved($js) : rtrim($js);
                $combined .= '/* ' . $f['href'] . " */\n" . $js . "\n;\n";
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

        return '<script src="' . $cacheUrlWithQuery . '"></script>';
    }

    protected static function minifyJsImproved($code)
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

            // Remove trailing semicolons before newlines in some cases (light)
            $out[] = $trimmed;
        }

        return implode("\n", $out);
    }

    protected static function attr($tag, $name)
    {
        if (preg_match('/\b' . $name . '\s*=\s*(["\'])(.*?)\1/i', $tag, $m)) {
            return $m[2];
        }
        return NULL;
    }

    protected static function localPath($href)
    {
        $href = trim($href);

        if ($href === '' || strpos($href, '//') === 0 || preg_match('#^[a-z][a-z0-9+.\-]*:#i', $href)) {
            return NULL;
        }

        if (substr($href, 0, 1) !== '/') {
            return NULL;
        }

        $path = preg_replace('/[?#].*$/', '', $href);
        $realRoot = realpath(CMS_FOLDER);
        $realPath = realpath(CMS_FOLDER . ltrim($path, '/'));

        if ($realPath === FALSE || $realRoot === FALSE || strpos($realPath, $realRoot) !== 0) {
            return NULL;
        }

        return $realPath;
    }

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

    protected static function bundleInfo($files, $ext, $mode)
    {
        $dir = self::cacheDir();

        if (!is_dir($dir) && !@mkdir($dir, 0755, TRUE) && !is_dir($dir)) {
            return NULL;
        }

        $sig = $ext . '|' . $mode . '|';
        foreach ($files as $f) {
            $sig .= $f['path'] . '|' . @filemtime($f['path']) . ';';
        }

        $hash = substr(md5($sig), 0, 12);

        // Clean name + query string for cache busting
        $baseName = 'combine' . ($mode === 'min' ? '.min' : '') . '.' . $ext;
        $filename = $baseName;
        $cacheUrl = self::cacheUrl() . $filename . '?v=' . $hash;

        return array($dir . $filename, $cacheUrl);
    }
}
