<?php

require_once('../../bootstrap.php');

Core_Auth::authorization($sModule = 'optimizer');

$oModule = Core_Module::factory($sModule);

if (!class_exists('Optimizer_Settings', false)) {
    require_once CMS_FOLDER . 'modules/optimizer/Optimizer_Settings.php';
}

$sAdminFormAction = '/admin/optimizer/index.php';
$sTitle = Core::_('Optimizer.title');

$oAdmin_Form = Core_Entity::factory('Admin_Form')->getByGuid('OPTIMIZER-SETTINGS-FORM');

if (!$oAdmin_Form) {
    $oAdmin_Form = Core_Entity::factory('Admin_Form');
    $oAdmin_Form->name = $sTitle;
    $oAdmin_Form->guid = 'OPTIMIZER-SETTINGS-FORM';
    $oAdmin_Form->save();
}

$oAdmin_Form_Controller = Admin_Form_Controller::create($oAdmin_Form);
$oAdmin_Form_Controller
    ->module($oModule)
    ->setUp()
    ->path($sAdminFormAction)
    ->title($sTitle)
    ->pageTitle($sTitle);

$siteId = defined('CURRENT_SITE') ? CURRENT_SITE : 0;
$message = '';
$messageType = 'success';

if ((int) Core_Array::getRequest('save', 0) === 1) {
    $settings = Optimizer_Settings::get($siteId);

    $booleanKeys = array(
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

    foreach ($booleanKeys as $key) {
        $settings[$key] = (bool) Core_Array::getRequest($key, 0);
    }

    $settings['dns_prefetch'] = trim((string) Core_Array::getRequest('dns_prefetch', ''));
    $settings['preconnect'] = trim((string) Core_Array::getRequest('preconnect', ''));
    $settings['preload_fonts'] = trim((string) Core_Array::getRequest('preload_fonts', ''));
    $settings['critical_css'] = trim((string) Core_Array::getRequest('critical_css', ''));

    if (Optimizer_Settings::save($settings, $siteId)) {
        $message = Core::_('Optimizer.messages_success_save');
    }
    else {
        $message = Core::_('Optimizer.messages_save_error');
        $messageType = 'danger';
    }
}

$settings = Optimizer_Settings::get($siteId);
$statsSummary = Optimizer_Settings::getStatsSummary($siteId);

$oBreadcrumbs = Admin_Form_Entity::factory('Breadcrumbs');
$oBreadcrumbs->add(
    Admin_Form_Entity::factory('Breadcrumb')
        ->name($sTitle)
        ->href($oAdmin_Form_Controller->getAdminLoadHref($oAdmin_Form_Controller->getPath(), NULL, NULL, ''))
        ->onclick($oAdmin_Form_Controller->getAdminLoadAjax($oAdmin_Form_Controller->getPath(), NULL, NULL, ''))
);
$oAdmin_Form_Controller->addEntity($oBreadcrumbs);

$oAdmin_View = Admin_View::create();
$oAdmin_View
    ->module($oModule)
    ->pageTitle($sTitle);

$oForm = Admin_Form_Entity::factory('Form')
    ->controller($oAdmin_Form_Controller)
    ->action($oAdmin_Form_Controller->getPath())
    ->enctype('multipart/form-data');

if ($message !== '') {
    $oForm->add(
        Admin_Form_Entity::factory('Code')->html(
            '<div class="alert alert-' . htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . '</div>'
        )
    );
}

$enabledCount = 0;
foreach ($settings as $value) {
    if (is_bool($value) && $value) {
        $enabledCount++;
    }
}

$statusHtml = '<div class="alert alert-info">'
    . htmlspecialchars(Core::_('Optimizer.safe_mode_notice'), ENT_QUOTES, 'UTF-8')
    . '</div><p><strong>'
    . htmlspecialchars(Core::_('Optimizer.status_mode'), ENT_QUOTES, 'UTF-8')
    . ':</strong> '
    . htmlspecialchars($enabledCount === 0 ? Core::_('Optimizer.safe_mode') : Core::_('Optimizer.custom_mode'), ENT_QUOTES, 'UTF-8')
    . ' &nbsp; <strong>'
    . htmlspecialchars(Core::_('Optimizer.status_enabled'), ENT_QUOTES, 'UTF-8')
    . ':</strong> ' . (int) $enabledCount
    . ' &nbsp; <strong>'
    . htmlspecialchars(Core::_('Optimizer.total_saved'), ENT_QUOTES, 'UTF-8')
    . ':</strong> '
    . htmlspecialchars($statsSummary['total'], ENT_QUOTES, 'UTF-8')
    . '</p>';

$oForm->add(Admin_Form_Entity::factory('Code')->html($statusHtml));
$oForm->add(Admin_Form_Entity::factory('Code')->html('<input type="hidden" name="save" value="1">'));

function optimizerAddCheckbox($tab, $name, $caption, array $settings, $class = 'form-group col-xs-12 col-md-6')
{
    $tab->add(
        Admin_Form_Entity::factory('Div')->class('row')->add(
            Admin_Form_Entity::factory('Checkbox')
                ->name($name)
                ->value(!empty($settings[$name]) ? 1 : 0)
                ->caption($caption)
                ->divAttr(array('class' => $class))
        )
    );
}

function optimizerAddTextarea($tab, $name, $caption, array $settings, $rows = 5)
{
    $tab->add(
        Admin_Form_Entity::factory('Code')->html(
            '<div class="form-group">'
            . '<label for="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8')
            . '</label>'
            . '<textarea class="form-control" id="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
            . '" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
            . '" rows="' . (int) $rows . '">'
            . htmlspecialchars(isset($settings[$name]) ? $settings[$name] : '', ENT_QUOTES, 'UTF-8')
            . '</textarea></div>'
        )
    );
}

$oMainTab = Admin_Form_Entity::factory('Tab')
    ->name('main')
    ->caption(Core::_('Optimizer.tab_main'));
$oMainTab->add(Admin_Form_Entity::factory('Code')->html('<h4>' . htmlspecialchars(Core::_('Optimizer.section_html'), ENT_QUOTES, 'UTF-8') . '</h4>'));
optimizerAddCheckbox($oMainTab, 'minify_html', Core::_('Optimizer.minify_html'), $settings);
optimizerAddCheckbox($oMainTab, 'html_remove_comments', Core::_('Optimizer.html_remove_comments'), $settings);

$oAssetsTab = Admin_Form_Entity::factory('Tab')
    ->name('assets')
    ->caption(Core::_('Optimizer.tab_assets'));
$oAssetsTab->add(Admin_Form_Entity::factory('Code')->html('<h4>' . htmlspecialchars(Core::_('Optimizer.section_css'), ENT_QUOTES, 'UTF-8') . '</h4>'));
optimizerAddCheckbox($oAssetsTab, 'combine_css', Core::_('Optimizer.combine_css'), $settings);
optimizerAddCheckbox($oAssetsTab, 'minify_css', Core::_('Optimizer.minify_css'), $settings);
$oAssetsTab->add(Admin_Form_Entity::factory('Code')->html('<hr><h4>' . htmlspecialchars(Core::_('Optimizer.section_js'), ENT_QUOTES, 'UTF-8') . '</h4>'));
optimizerAddCheckbox($oAssetsTab, 'combine_js', Core::_('Optimizer.combine_js'), $settings);
optimizerAddCheckbox($oAssetsTab, 'minify_js', Core::_('Optimizer.minify_js'), $settings);

$oImagesTab = Admin_Form_Entity::factory('Tab')
    ->name('images')
    ->caption(Core::_('Optimizer.tab_images'));
$oImagesTab->add(Admin_Form_Entity::factory('Code')->html('<h4>' . htmlspecialchars(Core::_('Optimizer.section_images'), ENT_QUOTES, 'UTF-8') . '</h4>'));
optimizerAddCheckbox($oImagesTab, 'lazy_load_images', Core::_('Optimizer.lazy_load_images'), $settings);
optimizerAddCheckbox($oImagesTab, 'rewrite_webp', Core::_('Optimizer.rewrite_webp'), $settings);
optimizerAddCheckbox($oImagesTab, 'rewrite_avif', Core::_('Optimizer.rewrite_avif'), $settings);

$oNetworkTab = Admin_Form_Entity::factory('Tab')
    ->name('network')
    ->caption(Core::_('Optimizer.tab_network'));
$oNetworkTab->add(Admin_Form_Entity::factory('Code')->html('<h4>' . htmlspecialchars(Core::_('Optimizer.section_dns'), ENT_QUOTES, 'UTF-8') . '</h4>'));
optimizerAddCheckbox($oNetworkTab, 'dns_prefetch_enabled', Core::_('Optimizer.dns_prefetch_enabled'), $settings, 'form-group col-xs-12');
optimizerAddTextarea($oNetworkTab, 'dns_prefetch', Core::_('Optimizer.dns_prefetch'), $settings, 5);
$oNetworkTab->add(Admin_Form_Entity::factory('Code')->html('<hr><h4>' . htmlspecialchars(Core::_('Optimizer.section_preconnect'), ENT_QUOTES, 'UTF-8') . '</h4>'));
optimizerAddCheckbox($oNetworkTab, 'preconnect_enabled', Core::_('Optimizer.preconnect_enabled'), $settings, 'form-group col-xs-12');
optimizerAddTextarea($oNetworkTab, 'preconnect', Core::_('Optimizer.preconnect'), $settings, 5);

$oAdvancedTab = Admin_Form_Entity::factory('Tab')
    ->name('advanced')
    ->caption(Core::_('Optimizer.tab_advanced'));
$oAdvancedTab->add(Admin_Form_Entity::factory('Code')->html('<h4>' . htmlspecialchars(Core::_('Optimizer.section_fonts'), ENT_QUOTES, 'UTF-8') . '</h4>'));
optimizerAddCheckbox($oAdvancedTab, 'preload_fonts_enabled', Core::_('Optimizer.preload_fonts_enabled'), $settings, 'form-group col-xs-12');
optimizerAddTextarea($oAdvancedTab, 'preload_fonts', Core::_('Optimizer.preload_fonts'), $settings, 6);
$oAdvancedTab->add(Admin_Form_Entity::factory('Code')->html('<hr><h4>' . htmlspecialchars(Core::_('Optimizer.section_critical_css'), ENT_QUOTES, 'UTF-8') . '</h4>'));
optimizerAddCheckbox($oAdvancedTab, 'critical_css_enabled', Core::_('Optimizer.critical_css_enabled'), $settings, 'form-group col-xs-12');
optimizerAddTextarea($oAdvancedTab, 'critical_css', Core::_('Optimizer.critical_css'), $settings, 12);

$oTabs = Admin_Form_Entity::factory('Tabs');
$oTabs
    ->add($oMainTab)
    ->add($oAssetsTab)
    ->add($oImagesTab)
    ->add($oNetworkTab)
    ->add($oAdvancedTab);
$oForm->add($oTabs);

$savePath = Admin_Form_Controller::correctBackendPath('/{admin}/optimizer/index.php');
$oForm->add(
    Admin_Form_Entity::factory('Code')->html(
        '<div class="form-group margin-top-20">'
        . '<button type="button" class="btn btn-blue" onclick="var f=$(this).closest(\'form\'); mainFormLocker.unlock(); $.adminLoad({path:'
        . htmlspecialchars(json_encode($savePath), ENT_QUOTES, 'UTF-8')
        . ',additionalParams:f.serialize(),windowId:\'id_content\'}); return false;">'
        . '<i class="fa fa-save"></i> '
        . htmlspecialchars(Core::_('Optimizer.save'), ENT_QUOTES, 'UTF-8')
        . '</button></div>'
    )
);

ob_start();
$oForm->execute();
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