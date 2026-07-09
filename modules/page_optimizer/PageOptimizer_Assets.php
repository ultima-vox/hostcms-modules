<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Page Optimizer - Объединение и минификация CSS/JS
 */
class PageOptimizer_Assets
{
    protected static function cacheDir()
    {
        return CMS_FOLDER . 'upload/page_optimizer_cache/';
    }

    protected static function cacheUrl()
    {
        return '/upload/page_optimizer_cache/';
    }

    // ==================== CSS ====================

    public static function combineCss($html, $siteId, $minify = false)
    {
        $files = self::collectCssFiles($html);

        if (count($files) < 2) {
            return $html;
        }

        $bundleTag = self::createCssBundle($files, $siteId, $minify);

        return preg_replace('/(?:\s*<link\b[^>]*stylesheet[^>]*>\s*)+/i', $bundleTag . "\n", $html, 1);
    }

    protected static function collectCssFiles($html)
    {
        preg_match_all('/<link\b[^>]*>/i', $html, $matches);
        $files = [];

        foreach ($matches[0] as $tag) {
            if (stripos($tag, 'stylesheet') === false) continue;
            if (stripos($tag, 'data-page-optimizer-skip') !== false) continue;
            if (stripos($tag, 'disabled') !== false) continue;

            $href = self::getAttribute($tag, 'href');
            $path = self::getLocalPath($href);

            if ($path) {
                $files[] = [
                    'tag'  => $tag,
                    'href' => $href,
                    'path' => $path,
                ];
            }
        }

        return $files;
    }

    protected static function createCssBundle($files, $siteId, $minify)
    {
        $combined = '';
        $originalSize = 0;

        foreach ($files as $file) {
            $content = file_get_contents($file['path']);
            $originalSize += strlen($content);

            $content = self::rewriteCssUrls($content, dirname($file['href']));
            $content = $minify ? self::minifyCss($content) : trim($content);

            $combined .= "/* " . $file['href'] . " */\n" . $content . "\n";
        }

        $hash = self::generateHash($files);
        $filename = $minify ? 'combine.min.css' : 'combine.css';
        $fullPath = self::cacheDir() . $filename;

        if (!is_dir(self::cacheDir())) {
            @mkdir(self::cacheDir(), 0755, true);
        }

        file_put_contents($fullPath, $combined);

        $url = self::cacheUrl() . $filename . '?v=' . $hash;

        return '<link rel="stylesheet" href="' . $url . '">';
    }

    protected static function rewriteCssUrls($css, $baseDir)
    {
        return preg_replace_callback(
            '/url\(\s*([\'"]?)(?!(?:https?:)?\/\/|data:|#)([^\'"\)]+)\1\s*\)/i',
            function ($m) use ($baseDir) {
                $quote = $m[1];
                $path = trim($m[2]);

                if ($path === '' || substr($path, 0, 1) === '/') {
                    return 'url(' . $quote . $path . $quote . ')';
                }

                return 'url(' . $quote . self::resolveRelativePath($baseDir, $path) . $quote . ')';
            },
            $css
        );
    }

    protected static function minifyCss($css)
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*([{}:;,>])\s*/', '$1', $css);
        $css = preg_replace('/;}/', '}', $css);
        return trim($css);
    }

    // ==================== JS ====================

    public static function combineJs($html, $siteId, $minify = false)
    {
        $files = self::collectJsFiles($html);

        if (count($files) < 2) {
            return $html;
        }

        $bundleTag = self::createJsBundle($files, $siteId, $minify);

        return preg_replace('/(?:\s*<script\b[^>]*><\/script>\s*)+/i', $bundleTag . "\n", $html, 1);
    }

    protected static function collectJsFiles($html)
    {
        preg_match_all('/<script\b[^>]*><\/script>/i', $html, $matches);
        $files = [];

        foreach ($matches[0] as $tag) {
            if (stripos($tag, 'data-page-optimizer-skip') !== false) continue;

            if (preg_match('/\b(async|defer|nomodule|integrity)\b|type\s*=\s*["\']?\s*module/i', $tag)) {
                continue;
            }

            $src = self::getAttribute($tag, 'src');
            $path = self::getLocalPath($src);

            if ($path) {
                $files[] = [
                    'tag'  => $tag,
                    'href' => $src,
                    'path' => $path,
                ];
            }
        }

        return $files;
    }

    protected static function createJsBundle($files, $siteId, $minify)
    {
        $combined = '';
        $originalSize = 0;

        foreach ($files as $file) {
            $content = file_get_contents($file['path']);
            $originalSize += strlen($content);

            $content = $minify ? self::minifyJs($content) : rtrim($content);
            $combined .= "/* " . $file['href'] . " */\n" . $content . ";\n";
        }

        $hash = self::generateHash($files);
        $filename = $minify ? 'combine.min.js' : 'combine.js';
        $fullPath = self::cacheDir() . $filename;

        if (!is_dir(self::cacheDir())) {
            @mkdir(self::cacheDir(), 0755, true);
        }

        file_put_contents($fullPath, $combined);

        $url = self::cacheUrl() . $filename . '?v=' . $hash;

        return '<script src="' . $url . '"></script>';
    }

    protected static function minifyJs($js)
    {
        $lines = preg_split('/\r\n|\r|\n/', $js);
        $out = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '//')) continue;
            if (str_starts_with($trimmed, '/*') && str_ends_with($trimmed, '*/')) continue;

            $out[] = $trimmed;
        }

        return implode("\n", $out);
    }

    // ==================== Вспомогательные ====================

    protected static function getAttribute($tag, $name)
    {
        if (preg_match('/\b' . $name . '\s*=\s*["\']([^"\']*)["\']/i', $tag, $m)) {
            return $m[1];
        }
        return null;
    }

    protected static function getLocalPath($href)
    {
        if (!$href || strpos($href, '//') === 0 || preg_match('#^[a-z][a-z0-9+.\-]*:#i', $href)) {
            return null;
        }

        $path = preg_replace('/[?#].*$/', '', $href);
        if (substr($path, 0, 1) !== '/') return null;

        $realPath = realpath(CMS_FOLDER . ltrim($path, '/'));
        $realRoot = realpath(CMS_FOLDER);

        if ($realPath && $realRoot && strpos($realPath, $realRoot) === 0) {
            return $realPath;
        }

        return null;
    }

    protected static function resolveRelativePath($baseDir, $relative)
    {
        $combined = rtrim($baseDir, '/') . '/' . $relative;
        $parts = [];

        foreach (explode('/', $combined) as $segment) {
            if ($segment === '' || $segment === '.') continue;
            if ($segment === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $segment;
        }

        return '/' . implode('/', $parts);
    }

    protected static function generateHash($files)
    {
        $sig = '';
        foreach ($files as $f) {
            $sig .= $f['path'] . '|' . @filemtime($f['path']) . ';';
        }
        return substr(md5($sig), 0, 12);
    }
}