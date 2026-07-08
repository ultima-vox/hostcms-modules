<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

class Optimize
{
    protected static $_protectedTags = array('script', 'style', 'pre', 'textarea');
    protected static $_vault = array();

    public static function html($str)
    {
        self::$_vault = array();

        $siteId = defined('CURRENT_SITE') ? CURRENT_SITE : 0;
        $settings = Optimize_Settings::get($siteId);

        $str = self::injectHead($str, $settings);
        $str = self::optimizeImages($str, $settings);

        if (!empty($settings['combine_css'])) {
            $str = Optimize_Assets::combineCss($str, $siteId, !empty($settings['minify_css']));
        }

        if (!empty($settings['combine_js'])) {
            $str = Optimize_Assets::combineJs($str, $siteId, !empty($settings['minify_js']));
        }

        if (empty($settings['minify_html'])) {
            return $str;
        }

        $str = self::_extract($str);
        $str = preg_replace('/[ \t\r\n]+/', ' ', $str);
        $str = trim($str);
        $str = self::_restore($str);

        return $str;
    }

    protected static function injectHead($html, $settings)
    {
        if (stripos($html, '</head>') === FALSE) {
            return $html;
        }

        $head = '';

        if (!empty($settings['dns_prefetch_enabled'])) {
            foreach (self::lines($settings['dns_prefetch']) as $host) {
                $host = self::cleanHost($host);
                if ($host !== '') {
                    $head .= '<link rel="dns-prefetch" href="//' . htmlspecialchars($host, ENT_QUOTES) . '">' . "\n";
                }
            }
        }

        if (!empty($settings['preconnect_enabled'])) {
            foreach (self::lines($settings['preconnect']) as $host) {
                $host = self::cleanHost($host);
                if ($host !== '') {
                    $head .= '<link rel="preconnect" href="https://' . htmlspecialchars($host, ENT_QUOTES) . '" crossorigin>' . "\n";
                }
            }
        }

        if (!empty($settings['preload_fonts_enabled'])) {
            foreach (self::lines($settings['preload_fonts']) as $font) {
                $head .= '<link rel="preload" href="' . htmlspecialchars($font, ENT_QUOTES) . '" as="font" type="' . self::fontType($font) . '" crossorigin>' . "\n";
            }
        }

        if (!empty($settings['critical_css_enabled']) && trim($settings['critical_css']) !== '') {
            $head .= '<st' . 'yle data-optimize-critical>' . trim($settings['critical_css']) . '</st' . 'yle>' . "\n";
        }

        return $head === '' ? $html : str_ireplace('</head>', $head . '</head>', $html);
    }

    protected static function optimizeImages($html, $settings)
    {
        if (empty($settings['lazy_load_images']) && empty($settings['rewrite_avif']) && empty($settings['rewrite_webp'])) {
            return $html;
        }

        $exclude = self::lines(isset($settings['lazy_load_exclude']) ? $settings['lazy_load_exclude'] : '');

        return preg_replace_callback('/<img\b[^>]*>/i', function ($m) use ($settings, $exclude) {
            $tag = $m[0];

            if (stripos($tag, 'data-optimize-skip') !== FALSE) {
                return $tag;
            }

            if ((!empty($settings['rewrite_avif']) || !empty($settings['rewrite_webp'])) && preg_match('/\ssrc=("|\')(.*?)\1/i', $tag, $srcMatch)) {
                $newSrc = self::bestImageVariant($srcMatch[2], $settings);
                if ($newSrc !== $srcMatch[2]) {
                    $tag = str_replace($srcMatch[1] . $srcMatch[2] . $srcMatch[1], $srcMatch[1] . $newSrc . $srcMatch[1], $tag);
                }
            }

            if (!empty($settings['lazy_load_images']) && stripos($tag, ' loading=') === FALSE) {
                foreach ($exclude as $needle) {
                    if ($needle !== '' && stripos($tag, $needle) !== FALSE) {
                        return $tag;
                    }
                }

                $tag = rtrim($tag, '>') . ' loading="lazy"';
                if (stripos($tag, ' decoding=') === FALSE) {
                    $tag .= ' decoding="async"';
                }
                $tag .= '>';
            }

            return $tag;
        }, $html);
    }

    protected static function bestImageVariant($src, $settings)
    {
        $clean = preg_replace('/[?#].*$/', '', $src);
        $ext = strtolower(pathinfo($clean, PATHINFO_EXTENSION));

        if (!in_array($ext, array('jpg', 'jpeg', 'png'), TRUE) || substr($clean, 0, 1) !== '/') {
            return $src;
        }

        $base = preg_replace('/\.' . preg_quote($ext, '/') . '$/i', '', $clean);
        $suffix = substr($src, strlen($clean));

        if (!empty($settings['rewrite_avif']) && is_file(CMS_FOLDER . ltrim($base . '.avif', '/'))) {
            return $base . '.avif' . $suffix;
        }

        if (!empty($settings['rewrite_webp']) && is_file(CMS_FOLDER . ltrim($base . '.webp', '/'))) {
            return $base . '.webp' . $suffix;
        }

        return $src;
    }

    protected static function cleanHost($value)
    {
        $value = trim($value);
        $value = preg_replace('#^https?://#i', '', $value);
        $value = preg_replace('#/.*$#', '', $value);
        return $value;
    }

    protected static function fontType($url)
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH));
        return substr($path, -5) === '.woff' ? 'font/woff' : 'font/woff2';
    }

    protected static function lines($value)
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

    protected static function _extract($str)
    {
        $tagsPattern = implode('|', self::$_protectedTags);
        $pattern = '#'
            . '<!--\[if[^>]*+>.*?<!\[endif\]-->'
            . '|<!\[CDATA\[.*?\]\]>'
            . '|<(' . $tagsPattern . ')\b[^>]*+>.*?</\1\s*>'
            . '|<!--.*?-->'
            . '#siu';

        return preg_replace_callback($pattern, function ($m) {
            $whole = $m[0];
            $isPlainComment = substr($whole, 0, 4) === '<!--'
                && substr($whole, 4, 4) !== '[if ' && substr($whole, 4, 3) !== '[if';

            if ($isPlainComment && empty($m[1])) {
                return '';
            }

            $token = chr(1) . 'OPT' . count(Optimize::$_vault) . chr(2);
            Optimize::$_vault[$token] = $whole;
            return $token;
        }, $str);
    }

    protected static function _restore($str)
    {
        return strtr($str, self::$_vault);
    }

    public static function ob()
    {
        ob_start(array(__CLASS__, 'html'));
    }

    public static function clean()
    {
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
    }

    public static function show()
    {
        echo 'It works!';
    }
}
