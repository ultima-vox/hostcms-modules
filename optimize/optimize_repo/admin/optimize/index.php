<?php

require_once('../../bootstrap.php');

Core_Auth::authorization($sModule = 'optimize');

$sAdminFormAction = '/admin/optimize/index.php';
$oAdmin_Form = Core_Entity::factory('Admin_Form')->getByGuid('OPTIMIZE-SETTINGS-FORM');

if (!$oAdmin_Form) {
    $oAdmin_Form = Core_Entity::factory('Admin_Form');
    $oAdmin_Form->name = 'Optimize';
    $oAdmin_Form->guid = 'OPTIMIZE-SETTINGS-FORM';
    $oAdmin_Form->save();
}

$oAdmin_Form_Controller = Admin_Form_Controller::create($oAdmin_Form);
$oAdmin_Form_Controller
    ->module(Core_Module::factory($sModule))
    ->setUp()
    ->path($sAdminFormAction)
    ->title('Optimize')
    ->pageTitle('Optimize');

$siteId = defined('CURRENT_SITE') ? CURRENT_SITE : 0;
$settings = Optimize_Settings::get($siteId);
$statsSummary = Optimize_Settings::getStatsSummary($siteId);

$oAdmin_Form_Entity_Breadcrumbs = Admin_Form_Entity::factory('Breadcrumbs');
$oAdmin_Form_Entity_Breadcrumbs->add(
    Admin_Form_Entity::factory('Breadcrumb')
        ->name('Optimize')
        ->href($oAdmin_Form_Controller->getAdminLoadHref($oAdmin_Form_Controller->getPath(), NULL, NULL, ''))
        ->onclick($oAdmin_Form_Controller->getAdminLoadAjax($oAdmin_Form_Controller->getPath(), NULL, NULL, ''))
);
$oAdmin_Form_Controller->addEntity($oAdmin_Form_Entity_Breadcrumbs);

$oAdmin_View = Admin_View::create();
$oAdmin_View
    ->module(Core_Module::factory($sModule))
    ->pageTitle('Optimize');

$oAdmin_Form_Entity_Form = Admin_Form_Entity::factory('Form')
    ->controller($oAdmin_Form_Controller)
    ->action($oAdmin_Form_Controller->getPath())
    ->enctype('multipart/form-data');

$oMainTab = Admin_Form_Entity::factory('Tab')
    ->name('main')
    ->caption('Настройки');

$oMainTab
    ->add(
        Admin_Form_Entity::factory('Div')->class('row')->add(
            Admin_Form_Entity::factory('Checkbox')
                ->name('minify_html')
                ->class('optimize-toggle')
                ->value(!empty($settings['minify_html']) ? 1 : 0)
                ->caption('Минифицировать HTML')
                ->divAttr(array('class' => 'form-group col-xs-12 optimize-switch-field'))
        )
    )
    ->add(
        Admin_Form_Entity::factory('Div')->class('row')->add(
            Admin_Form_Entity::factory('Checkbox')
                ->name('combine_css')
                ->class('optimize-toggle')
                ->value(!empty($settings['combine_css']) ? 1 : 0)
                ->caption('Объединять и минифицировать локальные CSS-файлы')
                ->divAttr(array('class' => 'form-group col-xs-12 optimize-switch-field'))
        )
    )
    ->add(
        Admin_Form_Entity::factory('Div')->class('row')->add(
            Admin_Form_Entity::factory('Checkbox')
                ->name('combine_js')
                ->class('optimize-toggle')
                ->value(!empty($settings['combine_js']) ? 1 : 0)
                ->caption('Объединять локальные JS-файлы')
                ->divAttr(array('class' => 'form-group col-xs-12 optimize-switch-field'))
        )
    )
    ->add(
        Admin_Form_Entity::factory('Code')->html(
            '<div class="optimize-stats-grid">'
            . '<div><span>Всего</span><strong data-optimize-stat="total">' . htmlspecialchars($statsSummary['total'], ENT_QUOTES) . '</strong></div>'
            . '<div><span>CSS</span><strong data-optimize-stat="css">' . htmlspecialchars($statsSummary['css'], ENT_QUOTES) . '</strong></div>'
            . '<div><span>JS</span><strong data-optimize-stat="js">' . htmlspecialchars($statsSummary['js'], ENT_QUOTES) . '</strong></div>'
            . '<div><span>Запросов убрано</span><strong data-optimize-stat="requests">' . (int) $statsSummary['requests'] . '</strong></div>'
            . '</div>'
            . '<p class="optimize-note">Статистика обновляется только при реальной пересборке CSS/JS-бандла.</p>'
        )
    );

$oTabs = Admin_Form_Entity::factory('Tabs');
$oTabs->add($oMainTab);
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
    ->title('Optimize')
    ->execute();
