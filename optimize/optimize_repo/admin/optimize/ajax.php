<?php

require_once('../../bootstrap.php');

Core_Auth::authorization('optimize');

header('Content-Type: application/json; charset=UTF-8');

$siteId = defined('CURRENT_SITE') ? CURRENT_SITE : 0;
$action = Core_Array::getPost('action', 'toggle');

$booleanSettings = array(
    'minify_html',
    'combine_css',
    'minify_css',
    'critical_css_enabled',
    'combine_js',
    'minify_js',
    'preload_fonts_enabled',
    'lazy_load_images',
    'rewrite_webp',
    'rewrite_avif',
    'dns_prefetch_enabled',
    'preconnect_enabled'
);

$textSettings = array(
    'critical_css',
    'preload_fonts',
    'lazy_load_exclude',
    'dns_prefetch',
    'preconnect'
);

$name = Core_Array::getPost('name', '');
$settings = Optimize_Settings::get($siteId);
$deleted = 0;

if ($action === 'stats') {
    echo json_encode(array(
        'status' => 'ok',
        'settings' => Optimize_Settings::get($siteId),
        'stats' => Optimize_Settings::getStatsSummary($siteId)
    ));
    exit;
}

if ($action === 'text') {
    if (!in_array($name, $textSettings, TRUE)) {
        echo json_encode(array('status' => 'error', 'message' => Core::_('Optimize.invalid_setting')));
        exit;
    }

    $settings[$name] = Core_Array::getPost('value', '');
    $result = Optimize_Settings::writePublic($siteId, $settings);
} else {
    if (!in_array($name, $booleanSettings, TRUE)) {
        echo json_encode(array('status' => 'error', 'message' => Core::_('Optimize.invalid_setting')));
        exit;
    }

    $value = Core_Array::getPost('value', 0) ? 1 : 0;
    $settings[$name] = (bool) $value;

    if ($name === 'combine_css' && !$value) {
        $settings['minify_css'] = FALSE;
    }

    if ($name === 'combine_js' && !$value) {
        $settings['minify_js'] = FALSE;
    }

    $result = Optimize_Settings::writePublic($siteId, $settings);

    if ($result && !$value && class_exists('Optimize_Assets')) {
        if ($name === 'combine_css' || $name === 'minify_css') {
            $deleted = Optimize_Assets::clearBundles('css');
            Optimize_Settings::resetBundleStats('css', $siteId);
        } elseif ($name === 'combine_js' || $name === 'minify_js') {
            $deleted = Optimize_Assets::clearBundles('js');
            Optimize_Settings::resetBundleStats('js', $siteId);
        }
    }
}

$statsSummary = Optimize_Settings::getStatsSummary($siteId);

echo json_encode(array(
    'status' => $result ? 'ok' : 'error',
    'name' => $name,
    'deleted' => (int) $deleted,
    'settings' => Optimize_Settings::get($siteId),
    'stats' => $statsSummary
));
