<?php

require_once('../../bootstrap.php');

Core_Auth::authorization('optimize');

header('Content-Type: application/json; charset=UTF-8');

$allowed = array(
    'minify_html',
    'combine_css',
    'combine_js'
);

$name = Core_Array::getPost('name', '');
$value = Core_Array::getPost('value', 0) ? 1 : 0;
$siteId = defined('CURRENT_SITE') ? CURRENT_SITE : 0;

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
$statsSummary = Optimize_Settings::getStatsSummary($siteId);

echo json_encode(array(
    'status' => $result ? 'ok' : 'error',
    'name' => $name,
    'value' => $value,
    'stats' => $statsSummary
));
