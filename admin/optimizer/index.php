<?php

require_once('../../bootstrap.php');

Core_Auth::authorization($sModule = 'optimizer');
Core_Module::factory('optimizer');

function optimizerEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function optimizerCheckbox($name, $caption, array $settings, $experimental = false)
{
    $checked = !empty($settings[$name]) ? ' checked="checked"' : '';
    echo '<div class="checkbox"><label><input type="checkbox" name="' . optimizerEscape($name) . '" value="1"' . $checked . '>';
    echo '<span class="text">' . optimizerEscape($caption);
    if ($experimental) {
        echo ' <span class="label label-warning">' . optimizerEscape(Core::_('Optimizer.experimental')) . '</span>';
    }
    echo '</span></label></div>';
}

function optimizerTextarea($name, $caption, array $settings, $rows = 3)
{
    echo '<div class="form-group"><label for="' . optimizerEscape($name) . '">' . optimizerEscape($caption) . '</label>';
    echo '<textarea class="form-control" id="' . optimizerEscape($name) . '" name="' . optimizerEscape($name) . '" rows="' . intval($rows) . '">';
    echo optimizerEscape(isset($settings[$name]) ? $settings[$name] : '');
    echo '</textarea></div>';
}

$siteId = defined('CURRENT_SITE') ? CURRENT_SITE : 0;
$message = '';
$messageType = 'success';

if ((int) Core_Array::getPost('save', 0) === 1) {
    $settings = Optimizer_Settings::get($siteId);
    $booleanKeys = array(
        'minify_html', 'html_remove_comments', 'combine_css', 'minify_css',
        'combine_js', 'minify_js', 'lazy_load_images', 'rewrite_avif',
        'rewrite_webp', 'dns_prefetch_enabled', 'preconnect_enabled',
        'preload_fonts_enabled', 'critical_css_enabled'
    );

    foreach ($booleanKeys as $key) {
        $settings[$key] = (bool) Core_Array::getPost($key, 0);
    }

    $settings['dns_prefetch'] = trim((string) Core_Array::getPost('dns_prefetch', ''));
    $settings['preconnect'] = trim((string) Core_Array::getPost('preconnect', ''));
    $settings['preload_fonts'] = trim((string) Core_Array::getPost('preload_fonts', ''));
    $settings['critical_css'] = trim((string) Core_Array::getPost('critical_css', ''));

    if (Optimizer_Settings::save($settings, $siteId)) {
        $message = Core::_('Optimizer.messages_success_save');
    }
    else {
        $message = Core::_('Optimizer.messages_save_error');
        $messageType = 'danger';
    }
}

$settings = Optimizer_Settings::get($siteId);
$stats = Optimizer_Settings::getStatsSummary($siteId);
$path = Admin_Form_Controller::correctBackendPath('/{admin}/optimizer/index.php');

$enabledCount = 0;
foreach ($settings as $value) {
    if (is_bool($value) && $value) {
        $enabledCount++;
    }
}

echo '<div id="optimizer-content" class="row"><div class="col-xs-12">';
echo '<h5 class="row-title before-blue"><i class="fa fa-tachometer"></i> ' . optimizerEscape(Core::_('Optimizer.title')) . '</h5>';

if ($message !== '') {
    echo '<div class="alert alert-' . optimizerEscape($messageType) . '">' . optimizerEscape($message) . '</div>';
}

echo '<div class="alert alert-info">' . optimizerEscape(Core::_('Optimizer.safe_mode_notice')) . '</div>';
echo '<p><strong>' . optimizerEscape(Core::_('Optimizer.status_mode')) . ':</strong> ';
echo optimizerEscape($enabledCount === 0 ? Core::_('Optimizer.safe_mode') : Core::_('Optimizer.custom_mode'));
echo ' &nbsp; <strong>' . optimizerEscape(Core::_('Optimizer.status_enabled')) . ':</strong> ' . intval($enabledCount);
echo ' &nbsp; <strong>' . optimizerEscape(Core::_('Optimizer.total_saved')) . ':</strong> ' . optimizerEscape($stats['total']);
echo '</p>';

echo '<form id="optimizer-form" method="post" action="' . optimizerEscape($path) . '">';
echo '<input type="hidden" name="save" value="1">';
echo '<div class="widget flat radius-bordered"><div class="widget-header bg-blue"><span class="widget-caption">' . optimizerEscape(Core::_('Optimizer.tab_main')) . '</span></div><div class="widget-body">';
echo '<div class="row"><div class="col-md-6">';
optimizerCheckbox('minify_html', Core::_('Optimizer.minify_html'), $settings);
optimizerCheckbox('html_remove_comments', Core::_('Optimizer.html_remove_comments'), $settings);
optimizerCheckbox('lazy_load_images', Core::_('Optimizer.lazy_load_images'), $settings);
optimizerCheckbox('rewrite_webp', Core::_('Optimizer.rewrite_webp'), $settings);
optimizerCheckbox('rewrite_avif', Core::_('Optimizer.rewrite_avif'), $settings);
echo '</div><div class="col-md-6">';
optimizerCheckbox('combine_css', Core::_('Optimizer.combine_css'), $settings, true);
optimizerCheckbox('minify_css', Core::_('Optimizer.minify_css'), $settings, true);
optimizerCheckbox('combine_js', Core::_('Optimizer.combine_js'), $settings, true);
optimizerCheckbox('minify_js', Core::_('Optimizer.minify_js'), $settings, true);
echo '</div></div><hr><h5>' . optimizerEscape(Core::_('Optimizer.head_optimization')) . '</h5>';
optimizerCheckbox('dns_prefetch_enabled', Core::_('Optimizer.dns_prefetch_enabled'), $settings);
optimizerTextarea('dns_prefetch', Core::_('Optimizer.dns_prefetch'), $settings);
optimizerCheckbox('preconnect_enabled', Core::_('Optimizer.preconnect_enabled'), $settings);
optimizerTextarea('preconnect', Core::_('Optimizer.preconnect'), $settings);
optimizerCheckbox('preload_fonts_enabled', Core::_('Optimizer.preload_fonts_enabled'), $settings);
optimizerTextarea('preload_fonts', Core::_('Optimizer.preload_fonts'), $settings);
optimizerCheckbox('critical_css_enabled', Core::_('Optimizer.critical_css_enabled'), $settings);
optimizerTextarea('critical_css', Core::_('Optimizer.critical_css'), $settings, 8);
echo '<button type="submit" class="btn btn-blue"><i class="fa fa-save"></i> ' . optimizerEscape(Core::_('Optimizer.save')) . '</button>';
echo '</div></div></form>';

echo '<script>$(function(){var f=$("#optimizer-form");f.off("submit.optimizer").on("submit.optimizer",function(e){e.preventDefault();$.ajax({url:f.attr("action"),type:"POST",data:f.serialize(),dataType:"html",global:false,cache:false}).done(function(html){var r=$("<div>").html(html).find("#optimizer-content");if(r.length){$("#optimizer-content").replaceWith(r);}else{$("#optimizer-content").html(html);}}).fail(function(xhr,status,error){alert("Optimizer save error: "+status+(error?" — "+error:""));});return false;});});</script>';
echo '</div></div>';