<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Page Optimizer - Core HTML processing class
 */
class PageOptimizer
{
    public static function process($html)
    {
        if (!PageOptimizer_Context::shouldProcess($html)) {
            return $html;
        }

        $siteId = defined('CURRENT_SITE') ? CURRENT_SITE : 0;
        $settings = PageOptimizer_Settings::get($siteId);

        $html = self::injectHead($html, $settings);
        $html = self::optimizeImages($html, $settings);

        if (!empty($settings['combine_css'])) {
            $html = PageOptimizer_Assets::combineCss($html, $siteId, !empty($settings['minify_css']));
        }

        if (!empty($settings['combine_js'])) {
            $html = PageOptimizer_Assets::combineJs($html, $siteId, !empty($settings['minify_js']));
        }

        if (!empty($settings['minify_html'])) {
            $html = PageOptimizer_Html::minify($html, [
                'remove_comments' => !empty($settings['html_remove_comments']),
            ]);
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
            foreach (self::parseLines($settings['dns_prefetch'] ?? '') as $host) {
                $host = self::cleanHost($host);
                if ($host) {
                    $head .= '<link rel="dns-prefetch" href="//' . htmlspecialchars($host) . '">' . "\n";
                }
            }
        }

        if (!empty($settings['preconnect_enabled'])) {
            foreach (self::parseLines($settings['preconnect'] ?? '') as $host) {
                $host = self::cleanHost($host);
                if ($host) {
                    $head .= '<link rel="preconnect" href="https://' . htmlspecialchars($host) . '" crossorigin>' . "\n";
                }
            }
        }

        if (!empty($settings['preload_fonts_enabled'])) {
            foreach (self::parseLines($settings['preload_fonts'] ?? '') as $font) {
                $head .= '<link rel="preload" href="' . htmlspecialchars($font) . '" as="font" type="' . self::getFontType($font) . '" crossorigin>' . "\n";
            }
        }

        if (!empty($settings['critical_css_enabled']) && trim($settings['critical_css'] ?? '')) {
            $head .= '<style data-page-optimizer-critical>' . trim($settings['critical_css']) . '</style>' . "\n";
        }

        return $head ? str_ireplace('</head>', $head . '</head>', $html) : $html;
    }

    protected static function optimizeImages($html, $settings)
    {
        if (empty($settings['lazy_load_images']) && empty($settings['rewrite_avif']) && empty($settings['rewrite_webp'])) {
            return $html;
        }

        return preg_replace_callback('/<img\b[^>]*>/i', function ($match) use ($settings) {
            $img = $match[0];

            if (stripos($img, 'data-page-optimizer-skip') !== false) {
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
    }

    protected static function parseLines($value)
    {
        $lines = preg_split('/\r\n|\r|\n/', (string)$value);
        $result = [];

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
        $host = trim($host);
        $host = preg_replace('#^https?://#i', '', $host);
        return preg_replace('#/.*$#', '', $host);
    }

    protected static function getFontType($url)
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH));
        return str_ends_with($path, '.woff') ? 'font/woff' : 'font/woff2';
    }

    protected static function getBestImageVariant($src, $settings)
    {
        $clean = preg_replace('/[?#].*$/', '', $src);
        $ext = strtolower(pathinfo($clean, PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true) || substr($clean, 0, 1) !== '/') {
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
