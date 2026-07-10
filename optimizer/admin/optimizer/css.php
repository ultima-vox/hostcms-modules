<?php

require_once('../../bootstrap.php');

Core_Auth::authorization($sModule = 'optimizer');
$oModule = Core_Module::factory($sModule);
$sAdminFormAction = '/admin/optimizer/css.php';
$sTitle = Core::_('Optimizer.css_loading_title');
$siteId = defined('CURRENT_SITE') ? CURRENT_SITE : 0;

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (empty($_SESSION['optimizer_ajax_token'])) {
    $_SESSION['optimizer_ajax_token'] = bin2hex(random_bytes(24));
}

$optimizerAjaxToken = (string) $_SESSION['optimizer_ajax_token'];

function optimizerCssJsonResponse(array $data)
{
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function optimizerCssCheckToken($expectedToken)
{
    $token = (string) Core_Array::getPost('token', '');
    return $token !== '' && hash_equals($expectedToken, $token);
}

function optimizerCssSummary(array $settings)
{
    $enabledCount = 0;
    foreach ($settings as $value) {
        if (is_bool($value) && $value) {
            $enabledCount++;
        }
    }

    return array(
        'enabledCount' => $enabledCount,
        'mode' => $enabledCount === 0
            ? Core::_('Optimizer.safe_mode')
            : Core::_('Optimizer.custom_mode')
    );
}

if ((int) Core_Array::getPost('ajax_save_css_loading', 0) === 1) {
    if (!optimizerCssCheckToken($optimizerAjaxToken)) {
        optimizerCssJsonResponse(array(
            'success' => false,
            'message' => Core::_('Optimizer.csrf_error')
        ));
    }

    $settings = Optimizer_Settings::get($siteId);
    $settings['defer_css_enabled'] = (bool) ((int) Core_Array::getPost('defer_css_enabled', 0));
    $settings['critical_styles'] = trim((string) Core_Array::getPost('critical_styles', ''));
    $settings['deferred_styles'] = trim((string) Core_Array::getPost('deferred_styles', ''));

    $success = Optimizer_Settings::save($settings, $siteId);
    $summary = optimizerCssSummary($settings);

    optimizerCssJsonResponse(array(
        'success' => $success,
        'message' => $success
            ? Core::_('Optimizer.css_loading_saved')
            : Core::_('Optimizer.messages_save_error'),
        'enabledCount' => $summary['enabledCount'],
        'mode' => $summary['mode']
    ));
}

$oAdmin_Form = Core_Entity::factory('Admin_Form')->getByGuid('OPTIMIZER-CSS-LOADING-FORM');

if (!$oAdmin_Form) {
    $oAdmin_Form = Core_Entity::factory('Admin_Form');
    $oAdmin_Form->name = $sTitle;
    $oAdmin_Form->guid = 'OPTIMIZER-CSS-LOADING-FORM';
    $oAdmin_Form->save();
}

$oAdmin_Form_Controller = Admin_Form_Controller::create($oAdmin_Form);
$oAdmin_Form_Controller
    ->module($oModule)
    ->setUp()
    ->path($sAdminFormAction)
    ->title($sTitle)
    ->pageTitle($sTitle);

$settings = Optimizer_Settings::get($siteId);

$oBreadcrumbs = Admin_Form_Entity::factory('Breadcrumbs');
$oBreadcrumbs->add(
    Admin_Form_Entity::factory('Breadcrumb')
        ->name(Core::_('Optimizer.title'))
        ->href($oAdmin_Form_Controller->getAdminLoadHref('/admin/optimizer/index.php', NULL, NULL, ''))
        ->onclick($oAdmin_Form_Controller->getAdminLoadAjax('/admin/optimizer/index.php', NULL, NULL, ''))
);
$oBreadcrumbs->add(
    Admin_Form_Entity::factory('Breadcrumb')
        ->name($sTitle)
        ->href($oAdmin_Form_Controller->getAdminLoadHref($oAdmin_Form_Controller->getPath(), NULL, NULL, ''))
        ->onclick($oAdmin_Form_Controller->getAdminLoadAjax($oAdmin_Form_Controller->getPath(), NULL, NULL, ''))
);
$oAdmin_Form_Controller->addEntity($oBreadcrumbs);

$oAdmin_View = Admin_View::create();
$oAdmin_View->module($oModule)->pageTitle($sTitle);

$oForm = Admin_Form_Entity::factory('Form')
    ->controller($oAdmin_Form_Controller)
    ->action($oAdmin_Form_Controller->getPath());

$oForm->add(Admin_Form_Entity::factory('Code')->html(
    '<link rel="stylesheet" href="/modules/optimizer/assets/admin.css">'
    . '<div class="alert alert-info">'
    . htmlspecialchars(Core::_('Optimizer.css_loading_notice'), ENT_QUOTES, 'UTF-8')
    . '</div>'
));

$checked = !empty($settings['defer_css_enabled']) ? ' checked' : '';
$oForm->add(Admin_Form_Entity::factory('Code')->html(
    '<label class="optimizer-switch">'
    . '<input type="checkbox" name="defer_css_enabled" value="1"' . $checked . '>'
    . '<span class="optimizer-switch__slider" aria-hidden="true"></span>'
    . '<span class="optimizer-switch__label">'
    . htmlspecialchars(Core::_('Optimizer.defer_css_enabled'), ENT_QUOTES, 'UTF-8')
    . '</span></label>'
));

$oRow = Admin_Form_Entity::factory('Div')->class('row optimizer-css-lists');

$oCriticalColumn = Admin_Form_Entity::factory('Div')->class('col-xs-12 col-md-6');
$oCriticalColumn->add(
    Admin_Form_Entity::factory('Textarea')
        ->name('critical_styles')
        ->caption(Core::_('Optimizer.critical_styles'))
        ->value(isset($settings['critical_styles']) ? $settings['critical_styles'] : '')
        ->rows(14)
        ->divAttr(array('class' => 'form-group col-xs-12'))
);
$oCriticalColumn->add(Admin_Form_Entity::factory('Code')->html(
    '<p class="help-block">'
    . htmlspecialchars(Core::_('Optimizer.critical_styles_hint'), ENT_QUOTES, 'UTF-8')
    . '</p>'
));

$oDeferredColumn = Admin_Form_Entity::factory('Div')->class('col-xs-12 col-md-6 optimizer-css-lists__right');
$oDeferredColumn->add(
    Admin_Form_Entity::factory('Textarea')
        ->name('deferred_styles')
        ->caption(Core::_('Optimizer.deferred_styles'))
        ->value(isset($settings['deferred_styles']) ? $settings['deferred_styles'] : '')
        ->rows(14)
        ->divAttr(array('class' => 'form-group col-xs-12'))
);
$oDeferredColumn->add(Admin_Form_Entity::factory('Code')->html(
    '<p class="help-block">'
    . htmlspecialchars(Core::_('Optimizer.deferred_styles_hint'), ENT_QUOTES, 'UTF-8')
    . '</p>'
));

$oRow->add($oCriticalColumn)->add($oDeferredColumn);
$oForm->add($oRow);

$oForm->add(Admin_Form_Entity::factory('Code')->html(
    '<div class="optimizer-actions">'
    . '<button type="button" class="btn btn-blue" id="optimizer-save-css-loading">'
    . '<i class="fa fa-save"></i> '
    . htmlspecialchars(Core::_('Optimizer.save'), ENT_QUOTES, 'UTF-8')
    . '</button>'
    . '<span id="optimizer-css-loading-status" class="optimizer-status text-muted"></span>'
    . '</div>'
));

$ajaxPath = Admin_Form_Controller::correctBackendPath('/{admin}/optimizer/css.php');
$config = array(
    'path' => $ajaxPath,
    'token' => $optimizerAjaxToken,
    'saving' => Core::_('Optimizer.messages_saving'),
    'error' => Core::_('Optimizer.messages_save_error')
);

$oForm->add(Admin_Form_Entity::factory('Code')->html(
    '<script>window.OptimizerAdmin=' . json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>'
    . '<script src="/modules/optimizer/assets/admin.js"></script>'
));

ob_start();
$oForm->execute();
$content = ob_get_clean();

ob_start();
$oAdmin_View->content($content)->show();

Core_Skin::instance()
    ->answer()
    ->ajax(Core_Array::getRequest('_', FALSE))
    ->content(ob_get_clean())
    ->title($sTitle)
    ->execute();
