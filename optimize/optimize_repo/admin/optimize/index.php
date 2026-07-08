<?php

require_once('../../bootstrap.php');

Core_Auth::authorization($sModule = 'optimize');

$sAdminFormAction = '/admin/optimize/index.php';
$sTitle = Core::_('Optimize.model_name');

$oAdmin_Form = Core_Entity::factory('Admin_Form')->getByGuid('OPTIMIZE-SETTINGS-FORM');

if (!$oAdmin_Form) {
    $oAdmin_Form = Core_Entity::factory('Admin_Form');
    $oAdmin_Form->name = $sTitle;
    $oAdmin_Form->guid = 'OPTIMIZE-SETTINGS-FORM';
    $oAdmin_Form->save();
}

$oAdmin_Form_Controller = Admin_Form_Controller::create($oAdmin_Form);
$oAdmin_Form_Controller
    ->module(Core_Module::factory($sModule))
    ->setUp()
    ->path($sAdminFormAction)
    ->title($sTitle)
    ->pageTitle($sTitle);

$siteId = defined('CURRENT_SITE') ? CURRENT_SITE : 0;
$settings = Optimize_Settings::get($siteId);
$statsSummary = Optimize_Settings::getStatsSummary($siteId);

$oAdmin_Form_Entity_Breadcrumbs = Admin_Form_Entity::factory('Breadcrumbs');
$oAdmin_Form_Entity_Breadcrumbs->add(
    Admin_Form_Entity::factory('Breadcrumb')
        ->name($sTitle)
        ->href($oAdmin_Form_Controller->getAdminLoadHref($oAdmin_Form_Controller->getPath(), NULL, NULL, ''))
        ->onclick($oAdmin_Form_Controller->getAdminLoadAjax($oAdmin_Form_Controller->getPath(), NULL, NULL, ''))
);
$oAdmin_Form_Controller->addEntity($oAdmin_Form_Entity_Breadcrumbs);

$oAdmin_View = Admin_View::create();
$oAdmin_View
    ->module(Core_Module::factory($sModule))
    ->pageTitle($sTitle);

$oAdmin_Form_Entity_Form = Admin_Form_Entity::factory('Form')
    ->controller($oAdmin_Form_Controller)
    ->action($oAdmin_Form_Controller->getPath())
    ->enctype('multipart/form-data');

$statusItems = array(
    'minify_html' => Core::_('Optimize.minify_html'),
    'combine_css' => Core::_('Optimize.combine_css'),
    'minify_css' => Core::_('Optimize.minify_css'),
    'critical_css_enabled' => Core::_('Optimize.critical_css_enabled'),
    'combine_js' => Core::_('Optimize.combine_js'),
    'minify_js' => Core::_('Optimize.minify_js'),
    'preload_fonts_enabled' => Core::_('Optimize.preload_fonts_enabled'),
    'lazy_load_images' => Core::_('Optimize.lazy_load_images'),
    'rewrite_webp' => Core::_('Optimize.rewrite_webp'),
    'rewrite_avif' => Core::_('Optimize.rewrite_avif'),
    'dns_prefetch_enabled' => Core::_('Optimize.dns_prefetch_enabled'),
    'preconnect_enabled' => Core::_('Optimize.preconnect_enabled')
);

$sStatusHtml = '<div class="optimize-dashboard"><div class="optimize-status"><div class="optimize-status__title">' . htmlspecialchars(Core::_('Optimize.status_title'), ENT_QUOTES) . '</div><div class="optimize-status__grid">';
foreach ($statusItems as $key => $label) {
    $active = !empty($settings[$key]);
    $sStatusHtml .= '<span class="optimize-status__item ' . ($active ? 'is-active' : 'is-inactive') . '" data-optimize-status="' . htmlspecialchars($key, ENT_QUOTES) . '"><b>' . htmlspecialchars($label, ENT_QUOTES) . '</b><em>' . htmlspecialchars($active ? Core::_('Optimize.status_on') : Core::_('Optimize.status_off'), ENT_QUOTES) . '</em></span>';
}
$sStatusHtml .= '</div></div>';

$sStatsHtml = '<div class="optimize-stats-panel"><div class="optimize-stats-grid">'
    . '<div><span>' . htmlspecialchars(Core::_('Optimize.stats_total'), ENT_QUOTES) . '</span><strong data-optimize-stat="total">' . htmlspecialchars($statsSummary['total'], ENT_QUOTES) . '</strong></div>'
    . '<div><span>' . htmlspecialchars(Core::_('Optimize.stats_css'), ENT_QUOTES) . '</span><strong data-optimize-stat="css">' . htmlspecialchars($statsSummary['css'], ENT_QUOTES) . '</strong></div>'
    . '<div><span>' . htmlspecialchars(Core::_('Optimize.stats_js'), ENT_QUOTES) . '</span><strong data-optimize-stat="js">' . htmlspecialchars($statsSummary['js'], ENT_QUOTES) . '</strong></div>'
    . '<div><span>' . htmlspecialchars(Core::_('Optimize.stats_requests'), ENT_QUOTES) . '</span><strong data-optimize-stat="requests">' . (int) $statsSummary['requests'] . '</strong></div>'
    . '</div><p class="optimize-note">' . htmlspecialchars(Core::_('Optimize.stats_note'), ENT_QUOTES) . '</p></div></div>';

$oAdmin_Form_Entity_Form->add(Admin_Form_Entity::factory('Code')->html($sStatusHtml . $sStatsHtml));

$oOptimizeTab = Admin_Form_Entity::factory('Tab')
    ->name('optimize')
    ->caption(Core::_('Optimize.tab_optimize'));

$oOptimizeTab
    ->add(Admin_Form_Entity::factory('Code')->html('<div class="optimize-tab-grid">'))
    ->add(Admin_Form_Entity::factory('Code')->html('<div class="optimize-card"><h3 class="optimize-section-title">' . htmlspecialchars(Core::_('Optimize.section_html'), ENT_QUOTES) . '</h3>'))
    ->add(Admin_Form_Entity::factory('Div')->class('row')->add(
        Admin_Form_Entity::factory('Checkbox')
            ->name('minify_html')
            ->class('optimize-toggle')
            ->value(!empty($settings['minify_html']) ? 1 : 0)
            ->caption(Core::_('Optimize.minify_html'))
            ->divAttr(array('class' => 'form-group col-xs-12 optimize-switch-field'))
    ))
    ->add(Admin_Form_Entity::factory('Code')->html('</div>'))

    ->add(Admin_Form_Entity::factory('Code')->html('<div class="optimize-card"><h3 class="optimize-section-title">' . htmlspecialchars(Core::_('Optimize.section_css'), ENT_QUOTES) . '</h3>'))
    ->add(Admin_Form_Entity::factory('Div')->class('row')->add(
        Admin_Form_Entity::factory('Checkbox')
            ->name('combine_css')
            ->class('optimize-toggle')
            ->value(!empty($settings['combine_css']) ? 1 : 0)
            ->caption(Core::_('Optimize.combine_css'))
            ->divAttr(array('class' => 'form-group col-xs-12 optimize-switch-field'))
    ))
    ->add(Admin_Form_Entity::factory('Div')->class('row')->add(
        Admin_Form_Entity::factory('Checkbox')
            ->name('minify_css')
            ->class('optimize-toggle')
            ->value(!empty($settings['minify_css']) ? 1 : 0)
            ->caption(Core::_('Optimize.minify_css'))
            ->divAttr(array('class' => 'form-group col-xs-12 optimize-switch-field optimize-dependent-css'))
    ))
    ->add(Admin_Form_Entity::factory('Div')->class('row')->add(
        Admin_Form_Entity::factory('Checkbox')
            ->name('critical_css_enabled')
            ->class('optimize-toggle')
            ->value(!empty($settings['critical_css_enabled']) ? 1 : 0)
            ->caption(Core::_('Optimize.critical_css_enabled'))
            ->divAttr(array('class' => 'form-group col-xs-12 optimize-switch-field'))
    ))
    ->add(Admin_Form_Entity::factory('Code')->html(
        '<div class="optimize-editor-field"><label>' . htmlspecialchars(Core::_('Optimize.critical_css'), ENT_QUOTES) . '</label><textarea name="critical_css" class="optimize-setting-text optimize-code-css" rows="10">' . htmlspecialchars($settings['critical_css'], ENT_QUOTES) . '</textarea><p>' . htmlspecialchars(Core::_('Optimize.critical_css_hint'), ENT_QUOTES) . '</p></div></div>'
    ))

    ->add(Admin_Form_Entity::factory('Code')->html('<div class="optimize-card"><h3 class="optimize-section-title">' . htmlspecialchars(Core::_('Optimize.section_js'), ENT_QUOTES) . '</h3>'))
    ->add(Admin_Form_Entity::factory('Div')->class('row')->add(
        Admin_Form_Entity::factory('Checkbox')
            ->name('combine_js')
            ->class('optimize-toggle')
            ->value(!empty($settings['combine_js']) ? 1 : 0)
            ->caption(Core::_('Optimize.combine_js'))
            ->divAttr(array('class' => 'form-group col-xs-12 optimize-switch-field'))
    ))
    ->add(Admin_Form_Entity::factory('Div')->class('row')->add(
        Admin_Form_Entity::factory('Checkbox')
            ->name('minify_js')
            ->class('optimize-toggle')
            ->value(!empty($settings['minify_js']) ? 1 : 0)
            ->caption(Core::_('Optimize.minify_js'))
            ->divAttr(array('class' => 'form-group col-xs-12 optimize-switch-field optimize-dependent-js'))
    ))
    ->add(Admin_Form_Entity::factory('Code')->html('</div></div>'));

$oResourcesTab = Admin_Form_Entity::factory('Tab')
    ->name('resources')
    ->caption(Core::_('Optimize.tab_resources'));

$oResourcesTab
    ->add(Admin_Form_Entity::factory('Code')->html('<div class="optimize-tab-grid">'))
    ->add(Admin_Form_Entity::factory('Code')->html('<div class="optimize-card"><h3 class="optimize-section-title">' . htmlspecialchars(Core::_('Optimize.section_fonts'), ENT_QUOTES) . '</h3>'))
    ->add(Admin_Form_Entity::factory('Div')->class('row')->add(
        Admin_Form_Entity::factory('Checkbox')
            ->name('preload_fonts_enabled')
            ->class('optimize-toggle')
            ->value(!empty($settings['preload_fonts_enabled']) ? 1 : 0)
            ->caption(Core::_('Optimize.preload_fonts_enabled'))
            ->divAttr(array('class' => 'form-group col-xs-12 optimize-switch-field'))
    ))
    ->add(Admin_Form_Entity::factory('Code')->html(
        '<div class="optimize-editor-field"><label>' . htmlspecialchars(Core::_('Optimize.preload_fonts'), ENT_QUOTES) . '</label><textarea name="preload_fonts" class="optimize-setting-text" rows="5">' . htmlspecialchars($settings['preload_fonts'], ENT_QUOTES) . '</textarea><p>' . htmlspecialchars(Core::_('Optimize.preload_fonts_hint'), ENT_QUOTES) . '</p></div></div>'
    ))
    ->add(Admin_Form_Entity::factory('Code')->html('<div class="optimize-card"><h3 class="optimize-section-title">' . htmlspecialchars(Core::_('Optimize.section_images'), ENT_QUOTES) . '</h3>'))
    ->add(Admin_Form_Entity::factory('Div')->class('row')->add(
        Admin_Form_Entity::factory('Checkbox')
            ->name('lazy_load_images')
            ->class('optimize-toggle')
            ->value(!empty($settings['lazy_load_images']) ? 1 : 0)
            ->caption(Core::_('Optimize.lazy_load_images'))
            ->divAttr(array('class' => 'form-group col-xs-12 optimize-switch-field'))
    ))
    ->add(Admin_Form_Entity::factory('Code')->html(
        '<div class="optimize-editor-field"><label>' . htmlspecialchars(Core::_('Optimize.lazy_load_exclude'), ENT_QUOTES) . '</label><textarea name="lazy_load_exclude" class="optimize-setting-text" rows="4">' . htmlspecialchars($settings['lazy_load_exclude'], ENT_QUOTES) . '</textarea><p>' . htmlspecialchars(Core::_('Optimize.lazy_load_exclude_hint'), ENT_QUOTES) . '</p></div>'
    ))
    ->add(Admin_Form_Entity::factory('Div')->class('row')->add(
        Admin_Form_Entity::factory('Checkbox')
            ->name('rewrite_webp')
            ->class('optimize-toggle')
            ->value(!empty($settings['rewrite_webp']) ? 1 : 0)
            ->caption(Core::_('Optimize.rewrite_webp'))
            ->divAttr(array('class' => 'form-group col-xs-12 optimize-switch-field'))
    ))
    ->add(Admin_Form_Entity::factory('Div')->class('row')->add(
        Admin_Form_Entity::factory('Checkbox')
            ->name('rewrite_avif')
            ->class('optimize-toggle')
            ->value(!empty($settings['rewrite_avif']) ? 1 : 0)
            ->caption(Core::_('Optimize.rewrite_avif'))
            ->divAttr(array('class' => 'form-group col-xs-12 optimize-switch-field'))
    ))
    ->add(Admin_Form_Entity::factory('Code')->html('</div></div>'));

$oNetworkTab = Admin_Form_Entity::factory('Tab')
    ->name('network')
    ->caption(Core::_('Optimize.tab_network'));

$oNetworkTab
    ->add(Admin_Form_Entity::factory('Code')->html('<div class="optimize-tab-grid">'))
    ->add(Admin_Form_Entity::factory('Code')->html('<div class="optimize-card"><h3 class="optimize-section-title">' . htmlspecialchars(Core::_('Optimize.section_hints'), ENT_QUOTES) . '</h3>'))
    ->add(Admin_Form_Entity::factory('Div')->class('row')->add(
        Admin_Form_Entity::factory('Checkbox')
            ->name('dns_prefetch_enabled')
            ->class('optimize-toggle')
            ->value(!empty($settings['dns_prefetch_enabled']) ? 1 : 0)
            ->caption(Core::_('Optimize.dns_prefetch_enabled'))
            ->divAttr(array('class' => 'form-group col-xs-12 optimize-switch-field'))
    ))
    ->add(Admin_Form_Entity::factory('Code')->html(
        '<div class="optimize-editor-field"><label>' . htmlspecialchars(Core::_('Optimize.dns_prefetch'), ENT_QUOTES) . '</label><textarea name="dns_prefetch" class="optimize-setting-text" rows="4">' . htmlspecialchars($settings['dns_prefetch'], ENT_QUOTES) . '</textarea><p>' . htmlspecialchars(Core::_('Optimize.resource_hint_hint'), ENT_QUOTES) . '</p></div>'
    ))
    ->add(Admin_Form_Entity::factory('Div')->class('row')->add(
        Admin_Form_Entity::factory('Checkbox')
            ->name('preconnect_enabled')
            ->class('optimize-toggle')
            ->value(!empty($settings['preconnect_enabled']) ? 1 : 0)
            ->caption(Core::_('Optimize.preconnect_enabled'))
            ->divAttr(array('class' => 'form-group col-xs-12 optimize-switch-field'))
    ))
    ->add(Admin_Form_Entity::factory('Code')->html(
        '<div class="optimize-editor-field"><label>' . htmlspecialchars(Core::_('Optimize.preconnect'), ENT_QUOTES) . '</label><textarea name="preconnect" class="optimize-setting-text" rows="4">' . htmlspecialchars($settings['preconnect'], ENT_QUOTES) . '</textarea><p>' . htmlspecialchars(Core::_('Optimize.resource_hint_hint'), ENT_QUOTES) . '</p></div></div></div>'
    ));

$oTabs = Admin_Form_Entity::factory('Tabs');
$oTabs
    ->add($oOptimizeTab)
    ->add($oResourcesTab)
    ->add($oNetworkTab);
$oAdmin_Form_Entity_Form->add($oTabs);

$sCssFile = CMS_FOLDER . 'admin/optimize/assets/style.css';
$sJsFile = CMS_FOLDER . 'admin/optimize/assets/script.js';
$sAssets = '';

if (is_file($sCssFile)) {
    $sAssets .= '<style>' . file_get_contents($sCssFile) . '</style>';
}

if (is_file($sJsFile)) {
    $sAssets .= '<script>' . file_get_contents($sJsFile) . '</script>';
}

ob_start();

echo $sAssets;
$oAdmin_Form_Entity_Form->execute();

$content = ob_get_clean();

ob_start();

$oAdmin_View
    ->content($content)
    ->show();

Core_Skin::instance()
    ->answer()
    ->ajax(Core_Array::getRequest('_', FALSE))
    ->content(ob_get_clean())
    ->title($sTitle)
    ->execute();
