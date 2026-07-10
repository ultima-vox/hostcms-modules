<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

class Optimizer_Settings
{
    protected static $defaults = array(
        'config_version' => 5,
        'minify_html' => false,
        'html_remove_comments' => false,
        'lazy_load_images' => false,
        'lazy_load_skip_first' => 2,
        'image_exclude_classes' => "logo\nhero\nlcp",
        'rewrite_avif' => false,
        'rewrite_webp' => false,
        'image_generate_webp' => false,
        'image_generate_avif' => false,
        'image_webp_quality' => 82,
        'image_avif_quality' => 50,
        'image_scan_paths' => "upload",
        'image_batch_limit' => 20,
        'image_max_source_mb' => 25,
        'dns_prefetch_enabled' => false,
        'dns_prefetch' => '',
        'preconnect_enabled' => false,
        'preconnect' => '',
        'preload_fonts_enabled' => false,
        'preload_fonts' => '',
        'critical_css_enabled' => false,
        'critical_css' => ''
    );

    public static function getDefaults()
    {
        return self::$defaults;
    }

    public static function normalize(array $data)
    {
        $normalized = array_merge(self::$defaults, $data);

        foreach (array(
            'minify_html',
            'html_remove_comments',
            'lazy_load_images',
            'rewrite_avif',
            'rewrite_webp',
            'image_generate_webp',
            'image_generate_avif',
            'dns_prefetch_enabled',
            'preconnect_enabled',
            'preload_fonts_enabled',
            'critical_css_enabled'
        ) as $key) {
            $normalized[$key] = !empty($normalized[$key]);
        }

        $ranges = array(
            'lazy_load_skip_first' => array(0, 20),
            'image_webp_quality' => array(1, 100),
            'image_avif_quality' => array(1, 100),
            'image_batch_limit' => array(1, 200),
            'image_max_source_mb' => array(1, 200)
        );

        foreach ($ranges as $key => $range) {
            $value = isset($normalized[$key]) && is_numeric($normalized[$key])
                ? (int) $normalized[$key]
                : (int) self::$defaults[$key];
            $normalized[$key] = max($range[0], min($range[1], $value));
        }

        foreach (array(
            'image_exclude_classes',
            'image_scan_paths',
            'dns_prefetch',
            'preconnect',
            'preload_fonts',
            'critical_css'
        ) as $key) {
            $normalized[$key] = isset($normalized[$key]) && is_scalar($normalized[$key])
                ? (string) $normalized[$key]
                : self::$defaults[$key];
        }

        if (trim($normalized['image_scan_paths']) === '') {
            $normalized['image_scan_paths'] = self::$defaults['image_scan_paths'];
        }

        foreach (array('combine_css', 'minify_css', 'combine_js', 'minify_js', 'stats') as $legacyKey) {
            unset($normalized[$legacyKey]);
        }

        $normalized['config_version'] = self::$defaults['config_version'];

        return $normalized;
    }

    public static function getDirectory()
    {
        return CMS_FOLDER . 'upload/optimizer_cache/';
    }

    protected static function path($siteId)
    {
        return self::getDirectory() . 'settings_' . intval($siteId) . '.json';
    }

    protected static function ensureDir()
    {
        $dir = self::getDirectory();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return is_dir($dir) && is_writable($dir);
    }

    public static function install($siteId = null)
    {
        $siteId = $siteId === null ? (defined('CURRENT_SITE') ? CURRENT_SITE : 0) : $siteId;

        if (!self::ensureDir()) {
            return false;
        }

        return is_file(self::path($siteId)) || self::save(self::$defaults, $siteId);
    }

    public static function uninstall()
    {
        $dir = self::getDirectory();
        if (!is_dir($dir)) {
            return true;
        }

        $files = glob($dir . '*');
        if (is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }

        @rmdir($dir);

        return !is_dir($dir);
    }

    public static function get($siteId = null)
    {
        $siteId = $siteId === null ? (defined('CURRENT_SITE') ? CURRENT_SITE : 0) : $siteId;
        $data = array();
        $file = self::path($siteId);

        if (is_file($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        return self::normalize($data);
    }

    public static function save($data, $siteId = null)
    {
        $siteId = $siteId === null ? (defined('CURRENT_SITE') ? CURRENT_SITE : 0) : $siteId;
        $data = self::normalize(is_array($data) ? $data : array());

        if (!self::ensureDir()) {
            return false;
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return $json !== false
            && file_put_contents(self::path($siteId), $json, LOCK_EX) !== false;
    }
}
