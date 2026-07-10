<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

class Optimizer_Assets
{
    protected static function cacheDir()
    {
        return CMS_FOLDER . 'upload/optimizer_cache/';
    }

    protected static function cacheUrl()
    {
        return '/upload/optimizer_cache/';
    }

    public static function combineCss($html, $siteId, $minify = false)
    {
        $files = self::collectCssFiles($html);
        if (count($files) < 2) {
            return $html;
        }

        $tag = self::createCssBundle($files, $minify);
        if ($tag === null) {
            return $html;
        }

        $result = preg_replace('/(?:\s*<link\b[^>]*stylesheet[^>]*>\s*)+/i', $tag . "\n", $html, 1);
        return is_string($result) ? $result : $html;
    }

    protected static function collectCssFiles($html)
    {
        preg_match_all('/<link\b[^>]*>/i', $html, $matches);
        $files = array();
        foreach ($matches[0] as $tag) {
            if (stripos($tag, 'stylesheet') === false || stripos($tag, 'data-optimizer-skip') !== false || stripos($tag, 'disabled') !== false) {
                continue;
            }
            $href = self::getAttribute($tag, 'href');
            $path = self::getLocalPath($href);
            if ($path !== null) {
                $files[] = array('href' => $href, 'path' => $path);
            }
        }
        return $files;
    }

    protected static function createCssBundle(array $files, $minify)
    {
        $combined = '';
        foreach ($files as $file) {
            $content = @file_get_contents($file['path']);
            if ($content === false) {
                return null;
            }
            $content = self::rewriteCssUrls($content, dirname($file['href']));
            $combined .= $minify ? self::minifyCss($content) : trim($content) . "\n";
        }
        return self::writeBundle($combined, $files, $minify ? 'combine.min.css' : 'combine.css', 'css');
    }

    public static function combineJs($html, $siteId, $minify = false)
    {
        $files = self::collectJsFiles($html);
        if (count($files) < 2) {
            return $html;
        }

        $tag = self::createJsBundle($files, $minify);
        if ($tag === null) {
            return $html;
        }

        $result = preg_replace('/(?:\s*<script\b[^>]*><\/script>\s*)+/i', $tag . "\n", $html, 1);
        return is_string($result) ? $result : $html;
    }

    protected static function collectJsFiles($html)
    {
        preg_match_all('/<script\b[^>]*><\/script>/i', $html, $matches);
        $files = array();
        foreach ($matches[0] as $tag) {
            if (stripos($tag, 'data-optimizer-skip') !== false || preg_match('/\b(async|defer|nomodule|integrity)\b|type\s*=\s*["\']?\s*module/i', $tag)) {
                continue;
            }
            $src = self::getAttribute($tag, 'src');
            $path = self::getLocalPath($src);
            if ($path !== null) {
                $files[] = array('href' => $src, 'path' => $path);
            }
        }
        return $files;
    }

    protected static function createJsBundle(array $files, $minify)
    {
        $combined = '';
        foreach ($files as $file) {
            $content = @file_get_contents($file['path']);
            if ($content === false) {
                return null;
            }
            $combined .= ($minify ? self::minifyJs($content) : rtrim($content)) . ";\n";
        }
        return self::writeBundle($combined, $files, $minify ? 'combine.min.js' : 'combine.js', 'js');
    }

    protected static function writeBundle($content, array $files, $filename, $type)
    {
        $dir = self::cacheDir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return null;
        }
        if (@file_put_contents($dir . $filename, $content, LOCK_EX) === false) {
            return null;
        }

        $url = self::cacheUrl() . $filename . '?v=' . self::generateHash($files);
        if ($type === 'css') {
            return '<link rel="stylesheet" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">';
        }
        return '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></script>';
    }

    protected static function getAttribute($tag, $name)
    {
        if (preg_match('/\b' . preg_quote($name, '/') . '\s*=\s*["\']([^"\']*)["\']/i', $tag, $match)) {
            return $match[1];
        }
        return null;
    }

    protected static function getLocalPath($href)
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
        return $realPath && $realRoot && strpos($realPath, $realRoot) === 0 ? $realPath : null;
    }

    protected static function rewriteCssUrls($css, $baseDir)
    {
        $result = preg_replace_callback('/url\(\s*([\'\"]?)(?!(?:https?:)?\/\/|data:|#)([^\'\"\)]+)\1\s*\)/i', function ($match) use ($baseDir) {
            $path = trim($match[2]);
            if ($path === '' || substr($path, 0, 1) === '/') {
                return $match[0];
            }
            $parts = array();
            foreach (explode('/', rtrim($baseDir, '/') . '/' . $path) as $segment) {
                if ($segment === '' || $segment === '.') continue;
                if ($segment === '..') { array_pop($parts); continue; }
                $parts[] = $segment;
            }
            return 'url(' . $match[1] . '/' . implode('/', $parts) . $match[1] . ')';
        }, $css);
        return is_string($result) ? $result : $css;
    }

    protected static function generateHash(array $files)
    {
        $signature = '';
        foreach ($files as $file) {
            $signature .= $file['path'] . '|' . (@filemtime($file['path']) ?: 0) . ';';
        }
        return substr(md5($signature), 0, 12);
    }

    protected static function minifyCss($css)
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*([{}:;,>])\s*/', '$1', $css);
        return trim((string) $css);
    }

    protected static function minifyJs($js)
    {
        $lines = preg_split('/\r\n|\r|\n/', $js);
        $output = array();
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && strpos($line, '//') !== 0) {
                $output[] = $line;
            }
        }
        return implode("\n", $output);
    }
}
