<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

class Optimizer
{
    protected static $bufferStarted = false;

    public static function startOutputBuffer()
    {
        if (self::$bufferStarted || PHP_SAPI === 'cli') {
            return false;
        }

        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if ($uri !== '' && preg_match('#^/(admin|hostcmsfiles|modules)/#i', $uri)) {
            return false;
        }

        $method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');
        if ($method !== 'GET' && $method !== 'HEAD') {
            return false;
        }

        self::$bufferStarted = ob_start(array(__CLASS__, 'process'));

        return self::$bufferStarted;
    }

    public static function ob()
    {
        return self::startOutputBuffer();
    }

    public static function process($html)
    {
        if (!Optimizer_Context::shouldProcess($html)) {
            return $html;
        }

        $siteId = defined('CURRENT_SITE') ? CURRENT_SITE : 0;
        $settings = Optimizer_Settings::get($siteId);

        $html = self::injectHead($html, $settings);
        $html = self::optimizeImages($html, $settings);

        if (!empty($settings['minify_html'])) {
            $html = Optimizer_Html::minify($html, array(
                'remove_comments' => !empty($settings['html_remove_comments'])
            ));
        }

        return $html;
    }

    protected static function injectHead($html, $settings)
    {
        if (stripos($html, '</head>') === false) {
            return $html;
        }

        $head = '';

        if (!empty($settings['dns_prefetch_enabled'])) {
            foreach (self::parseLines(isset($settings['dns_prefetch']) ? $settings['dns_prefetch'] : '') as $host) {
                $host = self::cleanHost($host);
                if ($host) {
                    $head .= '<link rel="dns-prefetch" href="//' . htmlspecialchars($host, ENT_QUOTES, 'UTF-8') . '">' . "\n";
                }
            }
        }

        if (!empty($settings['preconnect_enabled'])) {
            foreach (self::parseLines(isset($settings['preconnect']) ? $settings['preconnect'] : '') as $host) {
                $host = self::cleanHost($host);
                if ($host) {
                    $head .= '<link rel="preconnect" href="https://' . htmlspecialchars($host, ENT_QUOTES, 'UTF-8') . '" crossorigin>' . "\n";
                }
            }
        }

        if (!empty($settings['preload_fonts_enabled'])) {
            foreach (self::parseLines(isset($settings['preload_fonts']) ? $settings['preload_fonts'] : '') as $font) {
                $head .= '<link rel="preload" href="' . htmlspecialchars($font, ENT_QUOTES, 'UTF-8') . '" as="font" type="' . self::getFontType($font) . '" crossorigin>' . "\n";
            }
        }

        if (!empty($settings['critical_css_enabled']) && trim(isset($settings['critical_css']) ? $settings['critical_css'] : '') !== '') {
            $head .= '<style data-optimizer-critical>' . trim($settings['critical_css']) . '</style>' . "\n";
        }

        return $head !== '' ? str_ireplace('</head>', $head . '</head>', $html) : $html;
    }

    protected static function optimizeImages($html, $settings)
    {
        if (empty($settings['lazy_load_images']) && empty($settings['rewrite_avif']) && empty($settings['rewrite_webp'])) {
            return $html;
        }

        $skipFirst = max(0, isset($settings['lazy_load_skip_first']) ? (int) $settings['lazy_load_skip_first'] : 2);
        $excludedClasses = self::parseClassList(isset($settings['image_exclude_classes']) ? $settings['image_exclude_classes'] : '');
        $imageIndex = 0;

        $result = preg_replace_callback('/<img\b[^>]*>/i', function ($match) use ($settings, $skipFirst, $excludedClasses, &$imageIndex) {
            $img = $match[0];
            $currentIndex = $imageIndex++;

            if (self::shouldSkipImage($img, $excludedClasses)) {
                return $img;
            }

            if (!empty($settings['rewrite_avif']) || !empty($settings['rewrite_webp'])) {
                $img = self::rewriteAttributeUrl($img, 'src', $settings);
                $img = self::rewriteSrcsetAttribute($img, 'srcset', $settings);
            }

            $protectFromLazy = $currentIndex < $skipFirst
                || preg_match('/\sloading\s*=\s*["\']eager["\']/i', $img)
                || preg_match('/\sfetchpriority\s*=\s*["\']high["\']/i', $img);

            if (!empty($settings['lazy_load_images']) && !$protectFromLazy && stripos($img, ' loading=') === false) {
                $img = self::appendAttribute($img, 'loading="lazy"');
            }

            if (!empty($settings['lazy_load_images']) && !$protectFromLazy && stripos($img, ' decoding=') === false) {
                $img = self::appendAttribute($img, 'decoding="async"');
            }

            return $img;
        }, $html);

        if (!is_string($result)) {
            return $html;
        }

        if (!empty($settings['rewrite_avif']) || !empty($settings['rewrite_webp'])) {
            $result = preg_replace_callback('/<source\b[^>]*>/i', function ($match) use ($settings, $excludedClasses) {
                $source = $match[0];

                if (self::shouldSkipImage($source, $excludedClasses)) {
                    return $source;
                }

                return self::rewriteSrcsetAttribute($source, 'srcset', $settings);
            }, $result);
        }

        return is_string($result) ? $result : $html;
    }

    protected static function shouldSkipImage($tag, array $excludedClasses)
    {
        if (stripos($tag, 'data-optimizer-skip') !== false) {
            return true;
        }

        if (!empty($excludedClasses) && preg_match('/\sclass\s*=\s*(["\'])(.*?)\1/i', $tag, $match)) {
            $classes = preg_split('/\s+/', trim($match[2]));
            foreach ($classes as $class) {
                if (in_array(strtolower($class), $excludedClasses, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected static function rewriteAttributeUrl($tag, $attribute, $settings)
    {
        $pattern = '/(\s' . preg_quote($attribute, '/') . '\s*=\s*)(["\'])(.*?)\2/i';

        return preg_replace_callback($pattern, function ($match) use ($settings) {
            return $match[1] . $match[2] . self::getBestImageVariant($match[3], $settings) . $match[2];
        }, $tag, 1);
    }

    protected static function rewriteSrcsetAttribute($tag, $attribute, $settings)
    {
        $pattern = '/(\s' . preg_quote($attribute, '/') . '\s*=\s*)(["\'])(.*?)\2/i';

        return preg_replace_callback($pattern, function ($match) use ($settings) {
            $items = preg_split('/\s*,\s*/', trim($match[3]));
            $rewritten = array();

            foreach ($items as $item) {
                if ($item === '') {
                    continue;
                }

                $parts = preg_split('/\s+/', trim($item), 2);
                $rewritten[] = self::getBestImageVariant($parts[0], $settings)
                    . (isset($parts[1]) ? ' ' . $parts[1] : '');
            }

            return $match[1] . $match[2] . implode(', ', $rewritten) . $match[2];
        }, $tag, 1);
    }

    protected static function appendAttribute($tag, $attribute)
    {
        if (preg_match('/\/\s*>$/', $tag)) {
            return preg_replace('/\/\s*>$/', ' ' . $attribute . ' />', $tag);
        }

        return preg_replace('/>$/', ' ' . $attribute . '>', $tag);
    }

    protected static function parseClassList($value)
    {
        $classes = preg_split('/[\s,;]+/', strtolower((string) $value));
        $result = array();

        foreach ($classes as $class) {
            $class = trim($class);
            if ($class !== '') {
                $result[] = $class;
            }
        }

        return array_values(array_unique($result));
    }

    protected static function parseLines($value)
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $value);
        $result = array();

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && substr($line, 0, 1) !== '#') {
                $result[] = $line;
            }
        }

        return $result;
    }

    protected static function cleanHost($host)
    {
        $host = preg_replace('#^https?://#i', '', trim($host));
        return preg_replace('#/.*$#', '', $host);
    }

    protected static function getFontType($url)
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        return substr($path, -5) === '.woff' ? 'font/woff' : 'font/woff2';
    }

    protected static function getBestImageVariant($src, $settings)
    {
        $src = trim((string) $src);

        if ($src === '' || preg_match('#^(?:https?:)?//#i', $src) || stripos($src, 'data:') === 0 || stripos($src, 'blob:') === 0) {
            return $src;
        }

        $clean = preg_replace('/[?#].*$/', '', $src);
        $ext = strtolower(pathinfo($clean, PATHINFO_EXTENSION));

        if (!in_array($ext, array('jpg', 'jpeg', 'png'), true) || substr($clean, 0, 1) !== '/') {
            return $src;
        }

        $suffix = substr($src, strlen($clean));
        $base = preg_replace('/\.' . preg_quote($ext, '/') . '$/i', '', $clean);

        if (!empty($settings['rewrite_avif']) && is_file(CMS_FOLDER . ltrim($base . '.avif', '/'))) {
            return $base . '.avif' . $suffix;
        }

        if (!empty($settings['rewrite_webp']) && is_file(CMS_FOLDER . ltrim($base . '.webp', '/'))) {
            return $base . '.webp' . $suffix;
        }

        return $src;
    }
}
