<?php

declare(strict_types=1);

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Page Optimizer - Объединение и минификация CSS/JS
 */
class PageOptimizer_Assets
{
    protected static function cacheDir(): string
    {
        return CMS_FOLDER . 'upload/page_optimizer_cache/';
    }

    protected static function cacheUrl(): string
    {
        return '/upload/page_optimizer_cache/';
    }

    // ==================== CSS ====================

    public static function combineCss(string $html, int $siteId, bool $minify = false): string
    {
        $files = self::collectCssFiles($html);

        if (count($files) < 2) {
            return $html;
        }

        $bundleTag = self::createCssBundle($files, $siteId, $minify);

        if ($bundleTag === null) {
            return $html; // В случае ошибки возвращаем исходный HTML
        }

        return preg_replace('/(?:\s*<link\b[^>]*stylesheet[^>]*>\s*)+/i', $bundleTag . "\n", $html, 1) ?? $html;
    }

    protected static function collectCssFiles(string $html): array
    {
        preg_match_all('/<link\b[^>]*>/i', $html, $matches);
        $files = [];

        foreach ($matches[0] as $tag) {
            if (stripos($tag, 'stylesheet') === false) {
                continue;
            }
            if (stripos($tag, 'data-page-optimizer-skip') !== false) {
                continue;
            }
            if (stripos($tag, 'disabled') !== false) {
                continue;
            }

            $href = self::getAttribute($tag, 'href');
            $path = self::getLocalPath($href);

            if ($path !== null) {
                $files[] = [
                    'tag'  => $tag,
                    'href' => $href,
                    'path' => $path,
                ];
            }
        }

        return $files;
    }

    protected static function createCssBundle(array $files, int $siteId, bool $minify): ?string
    {
        $combined = '';

        foreach ($files as $file) {
            $content = @file_get_contents($file['path']);

            if ($content === false) {
                return null; // Ошибка чтения файла
            }

            $content = self::rewriteCssUrls($content, dirname($file['href']));
            $content = $minify ? self::minifyCss($content) : trim($content);

            $combined .= "/* " . $file['href'] . " */\n" . $content . "\n";
        }

        $hash = self::generateHash($files);
        $filename = $minify ? 'combine.min.css' : 'combine.css';
        $fullPath = self::cacheDir() . $filename;

        if (!is_dir(self::cacheDir())) {
            if (!@mkdir(self::cacheDir(), 0755, true)) {
                return null;
            }
        }

        if (@file_put_contents($fullPath, $combined) === false) {
            return null;
        }

        $url = self::cacheUrl() . $filename . '?v=' . $hash;

        return '<link rel="stylesheet" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">';
    }

    // ==================== JS ====================

    public static function combineJs(string $html, int $siteId, bool $minify = false): string
    {
        $files = self::collectJsFiles($html);

        if (count($files) < 2) {
            return $html;
        }

        $bundleTag = self::createJsBundle($files, $siteId, $minify);

        if ($bundleTag === null) {
            return $html;
        }

        return preg_replace('/(?:\s*<script\b[^>]*><\/script>\s*)+/i', $bundleTag . "\n", $html, 1) ?? $html;
    }

    protected static function collectJsFiles(string $html): array
    {
        preg_match_all('/<script\b[^>]*><\/script>/i', $html, $matches);
        $files = [];

        foreach ($matches[0] as $tag) {
            if (stripos($tag, 'data-page-optimizer-skip') !== false) {
                continue;
            }

            if (preg_match('/\b(async|defer|nomodule|integrity)\b|type\s*=\s*["\']?\s*module/i', $tag)) {
                continue;
            }

            $src = self::getAttribute($tag, 'src');
            $path = self::getLocalPath($src);

            if ($path !== null) {
                $files[] = [
                    'tag'  => $tag,
                    'href' => $src,
                    'path' => $path,
                ];
            }
        }

        return $files;
    }

    protected static function createJsBundle(array $files, int $siteId, bool $minify): ?string
    {
        $combined = '';

        foreach ($files as $file) {
            $content = @file_get_contents($file['path']);

            if ($content === false) {
                return null;
            }

            $content = $minify ? self::minifyJs($content) : rtrim($content);
            $combined .= "/* " . $file['href'] . " */\n" . $content . ";\n";
        }

        $hash = self::generateHash($files);
        $filename = $minify ? 'combine.min.js' : 'combine.js';
        $fullPath = self::cacheDir() . $filename;

        if (!is_dir(self::cacheDir())) {
            if (!@mkdir(self::cacheDir(), 0755, true)) {
                return null;
            }
        }

        if (@file_put_contents($fullPath, $combined) === false) {
            return null;
        }

        $url = self::cacheUrl() . $filename . '?v=' . $hash;

        return '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></script>';
    }

    // ==================== Вспомогательные ====================

    protected static function getAttribute(string $tag, string $name): ?string
    {
        if (preg_match('/\b' . $name . '\s*=\s*["\']([^"\']*)["\']/i', $tag, $m)) {
            return $m[1];
        }
        return null;
    }

    protected static function getLocalPath(?string $href): ?string
    {
        if (!$href || strpos($href, '//') === 0 || preg_match('#^[a-z][a-z0-9+.\-]*:#i', $href)) {
            return null;
        }

        $path = preg_replace('/[?#].*$/', '', $href);
        if (substr($path, 0, 1) !== '/') {
            return null;
        }

        $realPath = @realpath(CMS_FOLDER . ltrim($path, '/'));
        $realRoot = @realpath(CMS_FOLDER);

        if ($realPath && $realRoot && strpos($realPath, $realRoot) === 0) {
            return $realPath;
        }

        return null;
    }

    protected static function resolveRelativePath(string $baseDir, string $relative): string
    {
        $combined = rtrim($baseDir, '/') . '/' . $relative;
        $parts = [];

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

    protected static function generateHash(array $files): string
    {
        $sig = '';
        foreach ($files as $f) {
            $sig .= ($f['path'] ?? '') . '|' . (@filemtime($f['path']) ?: 0) . ';';
        }
        return substr(md5($sig), 0, 12);
    }

    protected static function rewriteCssUrls(string $css, string $baseDir): string
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
        ) ?? $css;
    }

    protected static function minifyCss(string $css): string
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*([{}:;,>])\s*/', '$1', $css);
        $css = preg_replace('/;}/', '}', $css);
        return trim($css);
    }

    protected static function minifyJs(string $js): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $js);
        $out = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '//')) {
                continue;
            }
            if (str_starts_with($trimmed, '/*') && str_ends_with($trimmed, '*/')) {
                continue;
            }

            $out[] = $trimmed;
        }

        return implode("\n", $out);
    }
}
