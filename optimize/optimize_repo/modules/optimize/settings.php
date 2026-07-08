<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

class Optimize_Settings
{
    protected static $defaults = array(
        'minify_html' => TRUE,
        'combine_css' => TRUE,
        'minify_css' => FALSE,
        'critical_css_enabled' => FALSE,
        'critical_css' => '',
        'combine_js'  => TRUE,
        'minify_js' => FALSE,
        'preload_fonts_enabled' => FALSE,
        'preload_fonts' => '',
        'lazy_load_images' => FALSE,
        'lazy_load_exclude' => '',
        'rewrite_webp' => FALSE,
        'rewrite_avif' => FALSE,
        'dns_prefetch_enabled' => FALSE,
        'dns_prefetch' => '',
        'preconnect_enabled' => FALSE,
        'preconnect' => '',
        'stats' => array(
            'css_original_bytes' => 0,
            'css_minified_bytes' => 0,
            'css_requests_saved' => 0,
            'js_original_bytes'  => 0,
            'js_minified_bytes'  => 0,
            'js_requests_saved'  => 0,
        ),
    );

    protected static function dir()
    {
        return CMS_FOLDER . 'upload/optimize_cache/';
    }

    protected static function path($siteId)
    {
        return self::dir() . 'state_' . intval($siteId) . '.json';
    }

    protected static function ensureDir()
    {
        $dir = self::dir();

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, TRUE);
        }

        return is_dir($dir);
    }

    public static function get($siteId = NULL)
    {
        $siteId = is_null($siteId) ? (defined('CURRENT_SITE') ? CURRENT_SITE : 0) : $siteId;
        $file = self::path($siteId);

        $data = self::$defaults;

        if (is_file($file)) {
            $decoded = json_decode(file_get_contents($file), TRUE);

            if (is_array($decoded)) {
                $data = array_merge($data, $decoded);
                unset($data['enabled']);
                $data['stats'] = array_merge(
                    self::$defaults['stats'],
                    (isset($decoded['stats']) && is_array($decoded['stats'])) ? $decoded['stats'] : array()
                );
            }
        }

        return $data;
    }

    public static function saveToggles($minifyHtml, $combineCss, $minifyCss, $combineJs, $minifyJs, $siteId = NULL)
    {
        $siteId = is_null($siteId) ? (defined('CURRENT_SITE') ? CURRENT_SITE : 0) : $siteId;

        $data = self::get($siteId);
        $data['minify_html'] = (bool) $minifyHtml;
        $data['combine_css'] = (bool) $combineCss;
        $data['minify_css'] = (bool) $minifyCss;
        $data['combine_js'] = (bool) $combineJs;
        $data['minify_js'] = (bool) $minifyJs;

        return self::write($siteId, $data);
    }

    public static function writePublic($siteId, $data)
    {
        unset($data['enabled']);
        return self::write($siteId, $data);
    }

    public static function addBundleSavings($type, $originalBytes, $minifiedBytes, $requestsSaved, $siteId = NULL)
    {
        $siteId = is_null($siteId) ? (defined('CURRENT_SITE') ? CURRENT_SITE : 0) : $siteId;
        $data = self::get($siteId);

        if (!isset($data['stats'][$type . '_original_bytes'])) {
            return FALSE;
        }

        $data['stats'][$type . '_original_bytes'] += max(0, (int) $originalBytes);
        $data['stats'][$type . '_minified_bytes'] += max(0, (int) $minifiedBytes);
        $data['stats'][$type . '_requests_saved'] += max(0, (int) $requestsSaved);

        return self::write($siteId, $data);
    }

    public static function resetBundleStats($type = NULL, $siteId = NULL)
    {
        $siteId = is_null($siteId) ? (defined('CURRENT_SITE') ? CURRENT_SITE : 0) : $siteId;
        $data = self::get($siteId);

        $types = $type === NULL ? array('css', 'js') : array($type);

        foreach ($types as $item) {
            if (isset($data['stats'][$item . '_original_bytes'])) {
                $data['stats'][$item . '_original_bytes'] = 0;
                $data['stats'][$item . '_minified_bytes'] = 0;
                $data['stats'][$item . '_requests_saved'] = 0;
            }
        }

        return self::write($siteId, $data);
    }

    public static function getStatsSummary($siteId = NULL)
    {
        $settings = self::get($siteId);
        $stats = $settings['stats'];

        $cssSaved = max(0, $stats['css_original_bytes'] - $stats['css_minified_bytes']);
        $jsSaved = max(0, $stats['js_original_bytes'] - $stats['js_minified_bytes']);
        $totalSaved = $cssSaved + $jsSaved;
        $totalRequestsSaved = $stats['css_requests_saved'] + $stats['js_requests_saved'];

        return array(
            'total' => self::formatBytes($totalSaved),
            'css' => self::formatBytes($cssSaved),
            'js' => self::formatBytes($jsSaved),
            'requests' => (int) $totalRequestsSaved,
        );
    }

    public static function formatBytes($bytes)
    {
        $bytes = max(0, (int) $bytes);

        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' ГБ';
        }

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' МБ';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' КБ';
        }

        return $bytes . ' Б';
    }

    protected static function write($siteId, $data)
    {
        if (!self::ensureDir()) {
            return FALSE;
        }

        unset($data['enabled']);

        $fp = fopen(self::path($siteId), 'c+');

        if (!$fp) {
            return FALSE;
        }

        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($data));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return TRUE;
    }
}
