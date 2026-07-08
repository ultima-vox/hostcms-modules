<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Per-site settings and cumulative statistics for the Optimize module.
 *
 * Stored as a small JSON file rather than a database table on purpose:
 * this is two on/off flags plus a handful of counters, so a table + model
 * + install migration would be more moving parts than the data warrants.
 */
class Optimize_Settings
{
    /**
     * @var array
     */
    protected static $defaults = array(
        'combine_css' => TRUE,
        'combine_js'  => TRUE,
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

        // upload/ is normally web-servable, and the generated CSS/JS bundles
        // need to stay reachable there — but the state_*.json files carry
        // internal settings/stats and shouldn't be fetchable directly, so
        // block just that extension rather than the whole directory.
        $htaccess = $dir . '.htaccess';

        if (is_dir($dir) && !is_file($htaccess)) {
            @file_put_contents(
                $htaccess,
                "<FilesMatch \"\\.json$\">\n    Require all denied\n    Deny from all\n</FilesMatch>\n"
            );
        }

        return is_dir($dir);
    }

    /**
     * Read current settings/stats for a site, merged with defaults so a
     * missing file or missing keys never cause notices.
     *
     * @param int|null $siteId
     * @return array
     */
    public static function get($siteId = NULL)
    {
        $siteId = is_null($siteId) ? (defined('CURRENT_SITE') ? CURRENT_SITE : 0) : $siteId;
        $file = self::path($siteId);

        $data = self::$defaults;

        if (is_file($file)) {
            $decoded = json_decode(file_get_contents($file), TRUE);

            if (is_array($decoded)) {
                $data = array_merge($data, $decoded);
                $data['stats'] = array_merge(
                    self::$defaults['stats'],
                    (isset($decoded['stats']) && is_array($decoded['stats'])) ? $decoded['stats'] : array()
                );
            }
        }

        return $data;
    }

    /**
     * Save the enable/disable toggles. Stats are left untouched.
     */
    public static function saveToggles($combineCss, $combineJs, $siteId = NULL)
    {
        $siteId = is_null($siteId) ? (defined('CURRENT_SITE') ? CURRENT_SITE : 0) : $siteId;
        $data = self::get($siteId);
        $data['combine_css'] = (bool) $combineCss;
        $data['combine_js'] = (bool) $combineJs;

        return self::write($siteId, $data);
    }

    /**
     * Record the effect of (re)building one bundle. Call this only at the
     * moment a bundle is actually generated (cache miss), so the totals
     * reflect cumulative lifetime savings instead of inflating every time
     * a page view merely reuses an already-built bundle.
     *
     * @param string $type 'css' or 'js'
     * @param int $originalBytes combined size of the source files
     * @param int $minifiedBytes size of the bundle actually written
     * @param int $requestsSaved how many HTTP requests this bundle removes (file count - 1)
     */
    public static function addBundleSavings($type, $originalBytes, $minifiedBytes, $requestsSaved, $siteId = NULL)
    {
        $siteId = is_null($siteId) ? (defined('CURRENT_SITE') ? CURRENT_SITE : 0) : $siteId;
        $data = self::get($siteId);

        $data['stats'][$type . '_original_bytes'] += max(0, (int) $originalBytes);
        $data['stats'][$type . '_minified_bytes'] += max(0, (int) $minifiedBytes);
        $data['stats'][$type . '_requests_saved'] += max(0, (int) $requestsSaved);

        return self::write($siteId, $data);
    }

    protected static function write($siteId, $data)
    {
        if (!self::ensureDir()) {
            return FALSE;
        }

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
