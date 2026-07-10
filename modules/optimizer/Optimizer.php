<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

class Optimizer
{
    protected static $bufferStarted = false;

    /**
     * Start output buffering once for frontend requests.
     *
     * The callback receives the final rendered response and applies enabled
     * optimizations only when it is a complete HTML document.
     */
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

    /**
     * Backward-compatible entry point for old layouts.
     */
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

        if (!empty($settings['combine_css'])) {
            $html = Optimizer_Assets::combineCss($html, $siteId, !empty($settings['minify_css']));
        }

        if (!empty($settings['combine_js'])) {
            $html = Optimizer_Assets::combineJs($html, $siteId, !empty($settings['minify_js']));
        }

        if (!empty($settings['minify_html'])) {
            $html = Optimizer_Html::minify($html, array(
                'remove_comments' => !empty($settings['html_remove_comments']),
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

        $result = preg_replace_callback('/<img\b[^>]*>/i', function ($match) use ($settings) {
            $img = $match[0];

            if (stripos($img, 'data-optimizer-skip') !== false) {
                return $img;
            }

            if (!empty($settings['rewrite_avif']) || !empty($settings['rewrite_webp'])) {
                if (preg_match('/\ssrc=["\'](.*?)["\']/i', $img, $srcMatch)) {
                    $newSrc = self::getBestImageVariant($srcMatch[1], $settings);
                    if ($newSrc !== $srcMatch[1]) {
                        $img = str_replace($srcMatch[1], $newSrc, $img);
                    }
                }
            }

            if (!empty($settings['lazy_load_images']) && stripos($img, ' loading=') === false) {
                $img = rtrim($img, '>') . ' loading="lazy" decoding="async">';
            }

            return $img;
        }, $html);

        return is_string($result) ? $result : $html;
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
        $clean = preg_replace('/[?#].*$/', '', $src);
        $ext = strtolower(pathinfo($clean, PATHINFO_EXTENSION));

        if (!in_array($ext, array('jpg', 'jpeg', 'png'), true) || substr($clean, 0, 1) !== '/') {
            return $src;
        }

        $base = preg_replace('/\.' . preg_quote($ext, '/') . '$/i', '', $clean);

        if (!empty($settings['rewrite_avif']) && is_file(CMS_FOLDER . ltrim($base . '.avif', '/'))) {
            return $base . '.avif' . substr($src, strlen($clean));
        }

        if (!empty($settings['rewrite_webp']) && is_file(CMS_FOLDER . ltrim($base . '.webp', '/'))) {
            return $base . '.webp' . substr($src, strlen($clean));
        }

        return $src;
    }
}