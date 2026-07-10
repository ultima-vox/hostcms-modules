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

$oMainTab = Admin_Form_Entity::factory('Tab')
    ->name('main')
    ->caption(Core::_('Optimizer.tab_main'));

$checkboxes = array(
    'minify_html' => Core::_('Optimizer.minify_html'),
    'html_remove_comments' => Core::_('Optimizer.html_remove_comments'),
    'lazy_load_images' => Core::_('Optimizer.lazy_load_images'),
    'rewrite_webp' => Core::_('Optimizer.rewrite_webp'),
    'rewrite_avif' => Core::_('Optimizer.rewrite_avif'),
    'combine_css' => Core::_('Optimizer.combine_css'),
    'minify_css' => Core::_('Optimizer.minify_css'),
    'combine_js' => Core::_('Optimizer.combine_js'),
    'minify_js' => Core::_('Optimizer.minify_js'),
    'dns_prefetch_enabled' => Core::_('Optimizer.dns_prefetch_enabled'),
    'preconnect_enabled' => Core::_('Optimizer.preconnect_enabled'),
    'preload_fonts_enabled' => Core::_('Optimizer.preload_fonts_enabled'),
    'critical_css_enabled' => Core::_('Optimizer.critical_css_enabled')
);

foreach ($checkboxes as $name => $caption) {
    $oMainTab->add(
        Admin_Form_Entity::factory('Div')->class('row')->add(
            Admin_Form_Entity::factory('Checkbox')
                ->name($name)
                ->value(!empty($settings[$name]) ? 1 : 0)
                ->caption($caption)
                ->divAttr(array('class' => 'form-group col-xs-12 col-md-6'))
        )
    );
}

$textareas = array(
    'dns_prefetch' => array(Core::_('Optimizer.dns_prefetch'), 4),
    'preconnect' => array(Core::_('Optimizer.preconnect'), 4),
    'preload_fonts' => array(Core::_('Optimizer.preload_fonts'), 4),
    'critical_css' => array(Core::_('Optimizer.critical_css'), 10)
);

foreach ($textareas as $name => $meta) {
    $oMainTab->add(
        Admin_Form_Entity::factory('Code')->html(
            '<div class="form-group">'
            . '<label for="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($meta[0], ENT_QUOTES, 'UTF-8')
            . '</label>'
            . '<textarea class="form-control" id="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
            . '" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
            . '" rows="' . (int) $meta[1] . '">'
            . htmlspecialchars(isset($settings[$name]) ? $settings[$name] : '', ENT_QUOTES, 'UTF-8')
            . '</textarea></div>'
        )
    );
}

$oTabs = Admin_Form_Entity::factory('Tabs');
$oTabs->add($oMainTab);
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
