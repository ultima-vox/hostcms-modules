<?php

/**
 * Page Optimizer administration section.
 *
 * @package HostCMS
 * @subpackage PageOptimizer
 * @version 7.x
 */
require_once('../../bootstrap.php');

Core_Auth::authorization($sModule = 'page_optimizer');

require_once CMS_FOLDER . 'modules/page_optimizer/PageOptimizer_Settings.php';

function pageOptimizerEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function pageOptimizerGetCsrfToken()
{
    if (session_status() !== PHP_SESSION_ACTIVE)
    {
        @session_start();
    }

    if (empty($_SESSION['page_optimizer_csrf']))
    {
        $_SESSION['page_optimizer_csrf'] = bin2hex(random_bytes(24));
    }

    return $_SESSION['page_optimizer_csrf'];
}

function pageOptimizerCheckCsrfToken($token)
{
    $storedToken = pageOptimizerGetCsrfToken();

    return $token !== '' && hash_equals($storedToken, $token);
}

function pageOptimizerRenderCheckbox($name, $caption, array $settings, $experimental = FALSE)
{
    $checked = !empty($settings[$name]) ? ' checked="checked"' : '';

    echo '<div class="checkbox"><label>';
    echo '<input type="checkbox" name="' . pageOptimizerEscape($name) . '" value="1"' . $checked . '>';
    echo '<span class="text">' . pageOptimizerEscape($caption);

    if ($experimental)
    {
        echo ' <span class="label label-warning">' . pageOptimizerEscape(Core::_('PageOptimizer.experimental')) . '</span>';
    }

    echo '</span></label></div>';
}

function pageOptimizerRenderTextarea($name, $caption, array $settings, $rows = 3)
{
    echo '<div class="form-group">';
    echo '<label for="' . pageOptimizerEscape($name) . '">' . pageOptimizerEscape($caption) . '</label>';
    echo '<textarea class="form-control" id="' . pageOptimizerEscape($name) . '" name="' . pageOptimizerEscape($name) . '" rows="' . intval($rows) . '">';
    echo pageOptimizerEscape(isset($settings[$name]) ? $settings[$name] : '');
    echo '</textarea></div>';
}

function pageOptimizerRenderStatusCard($title, $value, $icon)
{
    echo '<div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">';
    echo '<div class="databox radius-bordered databox-shadowed databox-graded">';
    echo '<div class="databox-left bg-blue"><div class="databox-piechart"><i class="fa ' . pageOptimizerEscape($icon) . ' fa-2x white"></i></div></div>';
    echo '<div class="databox-right"><span class="databox-number blue">' . pageOptimizerEscape($value) . '</span>';
    echo '<div class="databox-text darkgray">' . pageOptimizerEscape($title) . '</div></div>';
    echo '</div></div>';
}

$iSiteId = defined('CURRENT_SITE') ? CURRENT_SITE : 0;
$sMessage = '';
$sMessageType = 'success';

if (strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET') === 'POST')
{
    $sCsrfToken = (string) Core_Array::getPost('csrf_token', '');

    if (!pageOptimizerCheckCsrfToken($sCsrfToken))
    {
        $sMessage = Core::_('PageOptimizer.csrf_error');
        $sMessageType = 'danger';
    }
    else
    {
        $aSettings = PageOptimizer_Settings::get($iSiteId);
        $aBooleanKeys = array(
            'minify_html',
            'html_remove_comments',
            'combine_css',
            'minify_css',
            'combine_js',
            'minify_js',
            'lazy_load_images',
            'rewrite_avif',
            'rewrite_webp',
            'dns_prefetch_enabled',
            'preconnect_enabled',
            'preload_fonts_enabled',
            'critical_css_enabled'
        );

        foreach ($aBooleanKeys as $sKey)
        {
            $aSettings[$sKey] = (bool) Core_Array::getPost($sKey, 0);
        }

        $aSettings['dns_prefetch'] = trim((string) Core_Array::getPost('dns_prefetch', ''));
        $aSettings['preconnect'] = trim((string) Core_Array::getPost('preconnect', ''));
        $aSettings['preload_fonts'] = trim((string) Core_Array::getPost('preload_fonts', ''));
        $aSettings['critical_css'] = trim((string) Core_Array::getPost('critical_css', ''));

        if (PageOptimizer_Settings::save($aSettings, $iSiteId))
        {
            $sMessage = Core::_('PageOptimizer.messages_success_save');
        }
        else
        {
            $sMessage = Core::_('PageOptimizer.messages_save_error');
            $sMessageType = 'danger';
        }
    }
}

$aSettings = PageOptimizer_Settings::get($iSiteId);
$aStats = PageOptimizer_Settings::getStatsSummary($iSiteId);
$sPath = Admin_Form_Controller::correctBackendPath('/{admin}/page_optimizer/index.php');
$sCsrfToken = pageOptimizerGetCsrfToken();
$iEnabledCount = 0;

foreach ($aSettings as $mValue)
{
    is_bool($mValue) && $mValue && $iEnabledCount++;
}

echo '<div id="page-optimizer-content">';
echo '<div class="row"><div class="col-xs-12">';
echo '<h5 class="row-title before-blue"><i class="fa fa-tachometer"></i> ' . pageOptimizerEscape(Core::_('PageOptimizer.title')) . '</h5>';

if ($sMessage !== '')
{
    echo '<div class="alert alert-' . pageOptimizerEscape($sMessageType) . '">' . pageOptimizerEscape($sMessage) . '</div>';
}

echo '<div class="row">';
pageOptimizerRenderStatusCard(Core::_('PageOptimizer.status_mode'), $iEnabledCount === 0 ? Core::_('PageOptimizer.safe_mode') : Core::_('PageOptimizer.custom_mode'), 'fa-shield');
pageOptimizerRenderStatusCard(Core::_('PageOptimizer.status_enabled'), $iEnabledCount, 'fa-check-square-o');
pageOptimizerRenderStatusCard(Core::_('PageOptimizer.total_saved'), $aStats['total'], 'fa-compress');
pageOptimizerRenderStatusCard(Core::_('PageOptimizer.requests_saved'), $aStats['requests'], 'fa-exchange');
echo '</div>';

echo '<form id="page_optimizer_form" method="post" action="' . pageOptimizerEscape($sPath) . '">';
echo '<input type="hidden" name="csrf_token" value="' . pageOptimizerEscape($sCsrfToken) . '">';
echo '<div class="widget flat radius-bordered">';
echo '<div class="widget-header bg-blue"><span class="widget-caption">' . pageOptimizerEscape(Core::_('PageOptimizer.tab_main')) . '</span></div>';
echo '<div class="widget-body">';
echo '<div class="alert alert-info">' . pageOptimizerEscape(Core::_('PageOptimizer.safe_mode_notice')) . '</div>';

echo '<div class="row"><div class="col-md-6">';
pageOptimizerRenderCheckbox('minify_html', Core::_('PageOptimizer.minify_html'), $aSettings);
pageOptimizerRenderCheckbox('html_remove_comments', Core::_('PageOptimizer.html_remove_comments'), $aSettings);
pageOptimizerRenderCheckbox('lazy_load_images', Core::_('PageOptimizer.lazy_load_images'), $aSettings);
pageOptimizerRenderCheckbox('rewrite_webp', Core::_('PageOptimizer.rewrite_webp'), $aSettings);
pageOptimizerRenderCheckbox('rewrite_avif', Core::_('PageOptimizer.rewrite_avif'), $aSettings);
echo '</div><div class="col-md-6">';
pageOptimizerRenderCheckbox('combine_css', Core::_('PageOptimizer.combine_css'), $aSettings, TRUE);
pageOptimizerRenderCheckbox('minify_css', Core::_('PageOptimizer.minify_css'), $aSettings, TRUE);
pageOptimizerRenderCheckbox('combine_js', Core::_('PageOptimizer.combine_js'), $aSettings, TRUE);
pageOptimizerRenderCheckbox('minify_js', Core::_('PageOptimizer.minify_js'), $aSettings, TRUE);
echo '</div></div>';

echo '<hr><h5>' . pageOptimizerEscape(Core::_('PageOptimizer.head_optimization')) . '</h5>';
pageOptimizerRenderCheckbox('dns_prefetch_enabled', Core::_('PageOptimizer.dns_prefetch_enabled'), $aSettings);
pageOptimizerRenderTextarea('dns_prefetch', Core::_('PageOptimizer.dns_prefetch'), $aSettings);
pageOptimizerRenderCheckbox('preconnect_enabled', Core::_('PageOptimizer.preconnect_enabled'), $aSettings);
pageOptimizerRenderTextarea('preconnect', Core::_('PageOptimizer.preconnect'), $aSettings);
pageOptimizerRenderCheckbox('preload_fonts_enabled', Core::_('PageOptimizer.preload_fonts_enabled'), $aSettings);
pageOptimizerRenderTextarea('preload_fonts', Core::_('PageOptimizer.preload_fonts'), $aSettings);
pageOptimizerRenderCheckbox('critical_css_enabled', Core::_('PageOptimizer.critical_css_enabled'), $aSettings);
pageOptimizerRenderTextarea('critical_css', Core::_('PageOptimizer.critical_css'), $aSettings, 8);

echo '<div class="form-group margin-top-20">';
echo '<button type="submit" class="btn btn-blue"><i class="fa fa-save"></i> ' . pageOptimizerEscape(Core::_('PageOptimizer.save')) . '</button>';
echo '</div></div></div></form>';

echo '<script>$(function(){var f=$("#page_optimizer_form");f.off("submit.pageOptimizer").on("submit.pageOptimizer",function(e){e.preventDefault();$.post(f.attr("action"),f.serialize(),function(html){var replacement=$("<div>").html(html).find("#page-optimizer-content");if(replacement.length){$("#page-optimizer-content").replaceWith(replacement);}else{$("#page-optimizer-content").html(html);}});});});</script>';
echo '</div></div></div>';
