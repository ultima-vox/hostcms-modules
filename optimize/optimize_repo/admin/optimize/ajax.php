<?php

require_once('../../bootstrap.php');

Core_Auth::authorization('optimize');

header('Content-Type: application/json; charset=UTF-8');

$siteId = defined('CURRENT_SITE') ? CURRENT_SITE : 0;
$action = Core_Array::getPost('action', 'toggle');

if ($action === 'clear_bundles') {
    $deleted = class_exists('Optimize_Assets') ? Optimize_Assets::clearBundles() : 0;
    echo json_encode(array(
        'status' => 'ok',
        'deleted' => (int) $deleted,
        'message' => Core::_('Optimize.clear_bundles_done'),
        'stats' => Optimize_Settings::getStatsSummary($siteId)
    ));
    exit;
}

$allowed = array(
    'minify_html',
    'combine_css',
    'minify_css',
    'combine_js',
    'minify_js'
);

$name = Core_Array::getPost('name', '');
$value = Core_Array::getPost('value', 0) ? 1 : 0;

if (!in_array($name, $allowed, TRUE)) {
    echo json_encode(array(
        'status' => 'error',
        'message' => Core::_('Optimize.invalid_setting')
    ));
    exit;
}

$settings = Optimize_Settings::get($siteId);
$settings[$name] = (bool) $value;

$result = Optimize_Settings::writePublic($siteId, $settings);

if ($result && $value === 0) {
    if ($name === 'combine_css') {
        Optimize_Assets::clearBundles('css');
    } elseif ($name === 'combine_js') {
        Optimize_Assets::clearBundles('js');
    }
}

$statsSummary = Optimize_Settings::getStatsSummary($siteId);

echo json_encode(array(
    'status' => $result ? 'ok' : 'error',
    'name' => $name,
    'value' => $value,
    'stats' => $statsSummary
));
