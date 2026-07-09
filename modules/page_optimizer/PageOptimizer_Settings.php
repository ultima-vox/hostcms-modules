<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Page Optimizer - Управление настройками
 */
class PageOptimizer_Settings
{
    protected static $defaults = [
        'minify_html'           => false,
        'combine_css'           => false,
        'minify_css'            => false,
        'combine_js'            => false,
        'minify_js'             => false,
        'lazy_load_images'      => false,
        'rewrite_avif'          => false,
        'rewrite_webp'          => false,
        'dns_prefetch_enabled'  => false,
        'dns_prefetch'          => '',
        'preconnect_enabled'    => false,
        'preconnect'            => '',
        'preload_fonts_enabled' => false,
        'preload_fonts'         => '',
        'critical_css_enabled'  => false,
        'critical_css'          => '',
        'stats' => [
            'css_original_bytes'  => 0,
            'css_minified_bytes'  => 0,
            'css_requests_saved'  => 0,
            'js_original_bytes'   => 0,
            'js_minified_bytes'   => 0,
            'js_requests_saved'   => 0,
        ],
    ];

    protected static function dir()
    {
        return CMS_FOLDER . 'upload/page_optimizer_cache/';
    }

    protected static function path($siteId)
    {
        return self::dir() . 'settings_' . intval($siteId) . '.json';
    }

    protected static function ensureDir()
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return is_dir($dir);
    }

    public static function get($siteId = null)
    {
        $siteId = $siteId ?? (defined('CURRENT_SITE') ? CURRENT_SITE : 0);
        $file = self::path($siteId);

        $data = self::$defaults;

        if (is_file($file)) {
            $decoded = json_decode(file_get_contents($file), true);
            if (is_array($decoded)) {
                $data = array_merge($data, $decoded);
                if (isset($data['stats']) && is_array($data['stats'])) {
                    $data['stats'] = array_merge(self::$defaults['stats'], $data['stats']);
                }
            }
        }

        return $data;
    }

    public static function save($data, $siteId = null)
    {
        $siteId = $siteId ?? (defined('CURRENT_SITE') ? CURRENT_SITE : 0);

        if (!self::ensureDir()) {
            return false;
        }

        $file = self::path($siteId);
        $fp = fopen($file, 'c+');

        if (!$fp) return false;

        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return true;
    }

    public static function addBundleSavings($type, $originalBytes, $minifiedBytes, $requestsSaved, $siteId = null)
    {
        $siteId = $siteId ?? (defined('CURRENT_SITE') ? CURRENT_SITE : 0);
        $data = self::get($siteId);

        $key = $type;

        if (!isset($data['stats'][$key . '_original_bytes'])) {
            return false;
        }

        $data['stats'][$key . '_original_bytes']  += max(0, (int)$originalBytes);
        $data['stats'][$key . '_minified_bytes']  += max(0, (int)$minifiedBytes);
        $data['stats'][$key . '_requests_saved']  += max(0, (int)$requestsSaved);

        return self::save($data, $siteId);
    }

    public static function getStatsSummary($siteId = null)
    {
        $settings = self::get($siteId);
        $stats = $settings['stats'];

        $cssSaved = max(0, $stats['css_original_bytes'] - $stats['css_minified_bytes']);
        $jsSaved  = max(0, $stats['js_original_bytes']  - $stats['js_minified_bytes']);

        return [
            'total'    => self::formatBytes($cssSaved + $jsSaved),
            'css'      => self::formatBytes($cssSaved),
            'js'       => self::formatBytes($jsSaved),
            'requests' => $stats['css_requests_saved'] + $stats['js_requests_saved'],
        ];
    }

    protected static function formatBytes($bytes)
    {
        $bytes = max(0, (int)$bytes);
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' ГБ';
        if ($bytes >= 1048576)    return round($bytes / 1048576, 2) . ' МБ';
        if ($bytes >= 1024)       return round($bytes / 1024, 2) . ' КБ';
        return $bytes . ' Б';
    }
}
