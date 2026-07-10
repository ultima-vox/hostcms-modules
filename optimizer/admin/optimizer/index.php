<?php

require_once('../../bootstrap.php');

Core_Auth::authorization($sModule = 'optimizer');

$oModule = Core_Module::factory($sModule);

$sAdminFormAction = '/admin/optimizer/index.php';
$sTitle = Core::_('Optimizer.title');
$siteId = defined('CURRENT_SITE') ? CURRENT_SITE : 0;

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (empty($_SESSION['optimizer_ajax_token'])) {
    $_SESSION['optimizer_ajax_token'] = bin2hex(random_bytes(24));
}

$optimizerAjaxToken = (string) $_SESSION['optimizer_ajax_token'];

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
    'image_generate_webp',
    'image_generate_avif',
    'dns_prefetch_enabled',
    'preconnect_enabled',
    'preload_fonts_enabled'
);

$integerRanges = array(
    'lazy_load_skip_first' => array(0, 20),
    'image_webp_quality' => array(1, 100),
    'image_avif_quality' => array(1, 100),
    'image_batch_limit' => array(1, 200),
    'image_max_source_mb' => array(1, 200)
);

$textKeys = array(
    'image_exclude_classes',
    'image_scan_paths',
    'dns_prefetch',
    'preconnect',
    'preload_fonts'
);

function optimizerJsonResponse(array $data)
{
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function optimizerCheckToken($expectedToken)
{
    $token = (string) Core_Array::getPost('token', '');
    return $token !== '' && hash_equals($expectedToken, $token);
}

function optimizerEnabledCount(array $settings)
{
    $count = 0;

    foreach ($settings as $value) {
        if (is_bool($value) && $value) {
            $count++;
        }
    }

    return $count;
}

function optimizerSummary(array $settings)
{
    $enabledCount = optimizerEnabledCount($settings);

    return array(
        'enabledCount' => $enabledCount,
        'mode' => $enabledCount === 0
            ? Core::_('Optimizer.safe_mode')
            : Core::_('Optimizer.custom_mode')
    );
}

if ((int) Core_Array::getPost('ajax_save', 0) === 1) {
    if (!optimizerCheckToken($optimizerAjaxToken)) {
        optimizerJsonResponse(array(
            'success' => false,
            'message' => Core::_('Optimizer.csrf_error')
        ));
    }

    $name = (string) Core_Array::getPost('name', '');
    $value = Core_Array::getPost('value', '');

    if (!in_array($name, $booleanKeys, true)
        && !array_key_exists($name, $integerRanges)
        && !in_array($name, $textKeys, true)) {
        optimizerJsonResponse(array(
            'success' => false,
            'message' => Core::_('Optimizer.messages_save_error')
        ));
    }

    $settings = Optimizer_Settings::get($siteId);

    if (in_array($name, $booleanKeys, true)) {
        $settings[$name] = (bool) ((int) $value);
    }
    elseif (array_key_exists($name, $integerRanges)) {
        $range = $integerRanges[$name];
        $settings[$name] = max($range[0], min($range[1], (int) $value));
    }
    else {
        $settings[$name] = trim((string) $value);
    }

    $success = Optimizer_Settings::save($settings, $siteId);
    $summary = optimizerSummary($settings);

    optimizerJsonResponse(array(
        'success' => $success,
        'message' => $success
            ? Core::_('Optimizer.messages_success_save')
            : Core::_('Optimizer.messages_save_error'),
        'enabledCount' => $summary['enabledCount'],
        'mode' => $summary['mode'],
        'value' => isset($settings[$name]) ? $settings[$name] : null
    ));
}

if ((int) Core_Array::getPost('ajax_save_critical_css', 0) === 1) {
    if (!optimizerCheckToken($optimizerAjaxToken)) {
        optimizerJsonResponse(array(
            'success' => false,
            'message' => Core::_('Optimizer.csrf_error')
        ));
    }

    $settings = Optimizer_Settings::get($siteId);
    $settings['critical_css_enabled'] = (bool) ((int) Core_Array::getPost('critical_css_enabled', 0));
    $settings['critical_css'] = trim((string) Core_Array::getPost('critical_css', ''));

    $success = Optimizer_Settings::save($settings, $siteId);
    $summary = optimizerSummary($settings);

    optimizerJsonResponse(array(
        'success' => $success,
        'message' => $success
            ? Core::_('Optimizer.critical_css_saved')
            : Core::_('Optimizer.messages_save_error'),
        'enabledCount' => $summary['enabledCount'],
        'mode' => $summary['mode']
    ));
}

if ((int) Core_Array::getPost('ajax_generate_images', 0) === 1) {
    if (!optimizerCheckToken($optimizerAjaxToken)) {
        optimizerJsonResponse(array(
            'success' => false,
            'message' => Core::_('Optimizer.csrf_error')
        ));
    }

    $settings = Optimizer_Settings::get($siteId);
    $capabilities = Optimizer_Image_Generator::getCapabilities();

    if (empty($settings['image_generate_webp']) && empty($settings['image_generate_avif'])) {
        optimizerJsonResponse(array(
            'success' => false,
            'message' => Core::_('Optimizer.image_generation_none')
        ));
    }

    if ((!empty($settings['image_generate_webp']) && empty($capabilities['webp']))
        && (!empty($settings['image_generate_avif']) && empty($capabilities['avif']))) {
        optimizerJsonResponse(array(
            'success' => false,
            'message' => Core::_('Optimizer.image_generation_unsupported')
        ));
    }

    $files = Optimizer_Image_Scanner::scan($settings['image_scan_paths']);
    $pending = array();

    foreach ($files as $file) {
        if (Optimizer_Image_Generator::needsGenerationForSettings($file, $settings)) {
            $pending[] = $file;
        }
    }

    $totalPending = count($pending);
    $limit = max(1, min(200, (int) $settings['image_batch_limit']));
    $batch = array_slice($pending, 0, $limit);
    $summary = array(
        'generated' => 0,
        'skipped' => 0,
        'failed' => 0,
        'errors' => array()
    );

    foreach ($batch as $file) {
        $result = Optimizer_Image_Generator::generate($file, $settings);
        $summary['generated'] += (int) $result['generated'];
        $summary['skipped'] += (int) $result['skipped'];
        $summary['failed'] += (int) $result['failed'];

        if (!empty($result['errors'])) {
            $summary['errors'] = array_merge($summary['errors'], $result['errors']);
        }
    }

    $remaining = max(0, $totalPending - count($batch));

    optimizerJsonResponse(array(
        'success' => true,
        'message' => $remaining > 0
            ? Core::_('Optimizer.image_generation_batch_done')
            : Core::_('Optimizer.image_generation_done'),
        'found' => count($files),
        'processed' => count($batch),
        'remaining' => $remaining,
        'generated' => $summary['generated'],
        'skipped' => $summary['skipped'],
        'failed' => $summary['failed'],
        'errors' => array_slice($summary['errors'], 0, 10)
    ));
}

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

$settings = Optimizer_Settings::get($siteId);
$statsSummary = Optimizer_Settings::getStatsSummary($siteId);
$imageCapabilities = Optimizer_Image_Generator::getCapabilities();

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

$enabledCount = optimizerEnabledCount($settings);

$statusHtml = '<div class="alert alert-info">'
    . htmlspecialchars(Core::_('Optimizer.safe_mode_notice'), ENT_QUOTES, 'UTF-8')
    . '</div><p><strong>'
    . htmlspecialchars(Core::_('Optimizer.status_mode'), ENT_QUOTES, 'UTF-8')
    . ':</strong> <span id="optimizer-status-mode">'
    . htmlspecialchars($enabledCount === 0 ? Core::_('Optimizer.safe_mode') : Core::_('Optimizer.custom_mode'), ENT_QUOTES, 'UTF-8')
    . '</span> &nbsp; <strong>'
    . htmlspecialchars(Core::_('Optimizer.status_enabled'), ENT_QUOTES, 'UTF-8')
    . ':</strong> <span id="optimizer-status-enabled">' . (int) $enabledCount
    . '</span> &nbsp; <strong>'
    . htmlspecialchars(Core::_('Optimizer.total_saved'), ENT_QUOTES, 'UTF-8')
    . ':</strong> '
    . htmlspecialchars($statsSummary['total'], ENT_QUOTES, 'UTF-8')
    . ' &nbsp; <span id="optimizer-save-status" class="text-muted"></span></p>';

$oForm->add(Admin_Form_Entity::factory('Code')->html($statusHtml));

$adminCss = '<style>'
    . '.optimizer-dashboard{margin-top:10px}'
    . '.optimizer-section{margin-bottom:28px}'
    . '.optimizer-section__title{margin:0 0 14px;padding-bottom:10px;border-bottom:1px solid #e5e5e5;font-size:16px;font-weight:600}'
    . '.optimizer-switch{display:flex;align-items:center;gap:10px;margin:0 0 14px;cursor:pointer;user-select:none}'
    . '.optimizer-switch input{position:absolute;opacity:0;pointer-events:none}'
    . '.optimizer-switch__slider{position:relative;flex:0 0 42px;width:42px;height:22px;border-radius:999px;background:#cfcfcf;transition:background-color .2s ease,box-shadow .2s ease}'
    . '.optimizer-switch__slider:after{content:"";position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.25);transition:transform .2s ease}'
    . '.optimizer-switch input:checked+.optimizer-switch__slider{background:#53a93f}'
    . '.optimizer-switch input:checked+.optimizer-switch__slider:after{transform:translateX(20px)}'
    . '.optimizer-switch input:focus-visible+.optimizer-switch__slider{box-shadow:0 0 0 3px rgba(83,169,63,.22)}'
    . '.optimizer-switch input:disabled+.optimizer-switch__slider,.optimizer-switch input:disabled~.optimizer-switch__label{opacity:.5;cursor:not-allowed}'
    . '.optimizer-switch__label{font-weight:400;line-height:1.35}'
    . '.optimizer-critical-actions{display:flex;align-items:center;gap:12px;margin-top:12px}'
    . '.optimizer-critical-status{min-height:20px}'
    . '.optimizer-dashboard .ace_editor{min-height:360px}'
    . '.optimizer-critical-disabled{opacity:.65}'
    . '@media(max-width:991px){.optimizer-dashboard__right{margin-top:20px}}'
    . '</style>';
$oForm->add(Admin_Form_Entity::factory('Code')->html($adminCss));

function optimizerAddSwitch($parent, $name, $caption, array $settings)
{
    $checked = !empty($settings[$name]) ? ' checked' : '';
    $html = '<label class="optimizer-switch">'
        . '<input type="checkbox" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="1"' . $checked . '>'
        . '<span class="optimizer-switch__slider" aria-hidden="true"></span>'
        . '<span class="optimizer-switch__label">' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '</span>'
        . '</label>';

    $parent->add(Admin_Form_Entity::factory('Code')->html($html));
}

function optimizerAddTextarea($tab, $name, $caption, array $settings, $rows = 5, $hint = '')
{
    $html = '<div class="form-group">'
        . '<label for="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8')
        . '</label>'
        . '<textarea class="form-control optimizer-live-setting" id="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
        . '" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
        . '" rows="' . (int) $rows . '">'
        . htmlspecialchars(isset($settings[$name]) ? $settings[$name] : '', ENT_QUOTES, 'UTF-8')
        . '</textarea>';

    if ($hint !== '') {
        $html .= '<p class="help-block">' . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    $html .= '</div>';
    $tab->add(Admin_Form_Entity::factory('Code')->html($html));
}

function optimizerAddNumber($tab, $name, $caption, array $settings, $min, $max, $hint = '')
{
    $html = '<div class="form-group">'
        . '<label for="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8')
        . '</label>'
        . '<input type="number" class="form-control optimizer-live-setting" id="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
        . '" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
        . '" min="' . (int) $min . '" max="' . (int) $max . '" value="'
        . (int) (isset($settings[$name]) ? $settings[$name] : $min) . '">';

    if ($hint !== '') {
        $html .= '<p class="help-block">' . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    $html .= '</div>';
    $tab->add(Admin_Form_Entity::factory('Code')->html($html));
}

$oMainTab = Admin_Form_Entity::factory('Tab')
    ->name('main')
    ->caption(Core::_('Optimizer.tab_main'));

$oDashboardRow = Admin_Form_Entity::factory('Div')->class('row optimizer-dashboard');
$oDashboardLeft = Admin_Form_Entity::factory('Div')->class('col-xs-12 col-md-5 optimizer-dashboard__left');
$oDashboardRight = Admin_Form_Entity::factory('Div')->class('col-xs-12 col-md-7 optimizer-dashboard__right');
$oDashboardRow->add($oDashboardLeft)->add($oDashboardRight);
$oMainTab->add($oDashboardRow);

$oDashboardLeft->add(Admin_Form_Entity::factory('Code')->html(
    '<div class="optimizer-section"><h4 class="optimizer-section__title">'
    . htmlspecialchars(Core::_('Optimizer.section_html'), ENT_QUOTES, 'UTF-8')
    . '</h4>'
));
optimizerAddSwitch($oDashboardLeft, 'minify_html', Core::_('Optimizer.minify_html'), $settings);
optimizerAddSwitch($oDashboardLeft, 'html_remove_comments', Core::_('Optimizer.html_remove_comments'), $settings);
$oDashboardLeft->add(Admin_Form_Entity::factory('Code')->html('</div>'));

$oDashboardLeft->add(Admin_Form_Entity::factory('Code')->html(
    '<div class="optimizer-section"><h4 class="optimizer-section__title">'
    . htmlspecialchars(Core::_('Optimizer.section_css'), ENT_QUOTES, 'UTF-8')
    . '</h4>'
));
optimizerAddSwitch($oDashboardLeft, 'combine_css', Core::_('Optimizer.combine_css'), $settings);
optimizerAddSwitch($oDashboardLeft, 'minify_css', Core::_('Optimizer.minify_css'), $settings);
$oDashboardLeft->add(Admin_Form_Entity::factory('Code')->html('</div>'));

$oDashboardLeft->add(Admin_Form_Entity::factory('Code')->html(
    '<div class="optimizer-section"><h4 class="optimizer-section__title">'
    . htmlspecialchars(Core::_('Optimizer.section_js'), ENT_QUOTES, 'UTF-8')
    . '</h4>'
));
optimizerAddSwitch($oDashboardLeft, 'combine_js', Core::_('Optimizer.combine_js'), $settings);
optimizerAddSwitch($oDashboardLeft, 'minify_js', Core::_('Optimizer.minify_js'), $settings);
$oDashboardLeft->add(Admin_Form_Entity::factory('Code')->html('</div>'));

$oDashboardRight->add(Admin_Form_Entity::factory('Code')->html(
    '<div class="optimizer-section"><h4 class="optimizer-section__title">'
    . htmlspecialchars(Core::_('Optimizer.section_critical_css'), ENT_QUOTES, 'UTF-8')
    . '</h4>'
));
optimizerAddSwitch($oDashboardRight, 'critical_css_enabled', Core::_('Optimizer.critical_css_enabled'), $settings);

$oCriticalCss = Admin_Form_Entity::factory('Textarea')
    ->value(isset($settings['critical_css']) ? $settings['critical_css'] : '')
    ->rows(18)
    ->caption(Core::_('Optimizer.critical_css'))
    ->name('critical_css')
    ->syntaxHighlighter(defined('SYNTAX_HIGHLIGHTING') ? SYNTAX_HIGHLIGHTING : TRUE)
    ->syntaxHighlighterMode('css')
    ->divAttr(array('class' => 'form-group col-xs-12 optimizer-critical-editor'));

$oDashboardRight->add(
    Admin_Form_Entity::factory('Div')->class('row')->add($oCriticalCss)
);

$criticalActions = '<div class="optimizer-critical-actions">'
    . '<button type="button" class="btn btn-blue" id="optimizer-save-critical-css">'
    . '<i class="fa fa-save"></i> '
    . htmlspecialchars(Core::_('Optimizer.save'), ENT_QUOTES, 'UTF-8')
    . '</button>'
    . '<span id="optimizer-critical-status" class="optimizer-critical-status text-muted"></span>'
    . '</div></div>';
$oDashboardRight->add(Admin_Form_Entity::factory('Code')->html($criticalActions));

$oImagesTab = Admin_Form_Entity::factory('Tab')
    ->name('images')
    ->caption(Core::_('Optimizer.tab_images'));
$oImagesTab->add(Admin_Form_Entity::factory('Code')->html('<h4>' . htmlspecialchars(Core::_('Optimizer.section_images'), ENT_QUOTES, 'UTF-8') . '</h4>'));
optimizerAddSwitch($oImagesTab, 'lazy_load_images', Core::_('Optimizer.lazy_load_images'), $settings);
optimizerAddNumber($oImagesTab, 'lazy_load_skip_first', Core::_('Optimizer.lazy_load_skip_first'), $settings, 0, 20, Core::_('Optimizer.lazy_load_skip_first_hint'));
optimizerAddTextarea($oImagesTab, 'image_exclude_classes', Core::_('Optimizer.image_exclude_classes'), $settings, 5, Core::_('Optimizer.image_exclude_classes_hint'));
optimizerAddSwitch($oImagesTab, 'rewrite_webp', Core::_('Optimizer.rewrite_webp'), $settings);
optimizerAddSwitch($oImagesTab, 'rewrite_avif', Core::_('Optimizer.rewrite_avif'), $settings);

$capabilityHtml = '<hr><h4>' . htmlspecialchars(Core::_('Optimizer.section_image_generation'), ENT_QUOTES, 'UTF-8') . '</h4>'
    . '<div class="alert alert-' . (!empty($imageCapabilities['webp']) || !empty($imageCapabilities['avif']) ? 'success' : 'danger') . '">'
    . '<strong>' . htmlspecialchars(Core::_('Optimizer.image_support_webp'), ENT_QUOTES, 'UTF-8') . ':</strong> '
    . htmlspecialchars(!empty($imageCapabilities['webp']) ? Core::_('Optimizer.image_support_yes') : Core::_('Optimizer.image_support_no'), ENT_QUOTES, 'UTF-8')
    . ' &nbsp; <strong>' . htmlspecialchars(Core::_('Optimizer.image_support_avif'), ENT_QUOTES, 'UTF-8') . ':</strong> '
    . htmlspecialchars(!empty($imageCapabilities['avif']) ? Core::_('Optimizer.image_support_yes') : Core::_('Optimizer.image_support_no'), ENT_QUOTES, 'UTF-8')
    . '<br><span class="text-muted">GD: ' . (!empty($imageCapabilities['gd']) ? 'да' : 'нет')
    . ', Imagick: ' . (!empty($imageCapabilities['imagick']) ? 'да' : 'нет') . '</span>'
    . '</div>';
$oImagesTab->add(Admin_Form_Entity::factory('Code')->html($capabilityHtml));

optimizerAddSwitch($oImagesTab, 'image_generate_webp', Core::_('Optimizer.image_generate_webp'), $settings);
optimizerAddSwitch($oImagesTab, 'image_generate_avif', Core::_('Optimizer.image_generate_avif'), $settings);
optimizerAddNumber($oImagesTab, 'image_webp_quality', Core::_('Optimizer.image_webp_quality'), $settings, 1, 100);
optimizerAddNumber($oImagesTab, 'image_avif_quality', Core::_('Optimizer.image_avif_quality'), $settings, 1, 100);
optimizerAddTextarea($oImagesTab, 'image_scan_paths', Core::_('Optimizer.image_scan_paths'), $settings, 5, Core::_('Optimizer.image_scan_paths_hint'));
optimizerAddNumber($oImagesTab, 'image_batch_limit', Core::_('Optimizer.image_batch_limit'), $settings, 1, 200, Core::_('Optimizer.image_batch_limit_hint'));
optimizerAddNumber($oImagesTab, 'image_max_source_mb', Core::_('Optimizer.image_max_source_mb'), $settings, 1, 200, Core::_('Optimizer.image_max_source_mb_hint'));

$generatorHtml = '<div class="form-group margin-top-20">'
    . '<button type="button" class="btn btn-blue" id="optimizer-generate-images">'
    . '<i class="fa fa-picture-o"></i> '
    . htmlspecialchars(Core::_('Optimizer.image_generation_start'), ENT_QUOTES, 'UTF-8')
    . '</button> '
    . '<button type="button" class="btn btn-default" id="optimizer-stop-generation" disabled>'
    . '<i class="fa fa-stop"></i> '
    . htmlspecialchars(Core::_('Optimizer.image_generation_stop'), ENT_QUOTES, 'UTF-8')
    . '</button>'
    . '<div id="optimizer-generation-status" class="alert alert-info margin-top-10">'
    . htmlspecialchars(Core::_('Optimizer.image_generation_ready'), ENT_QUOTES, 'UTF-8')
    . '</div></div>';
$oImagesTab->add(Admin_Form_Entity::factory('Code')->html($generatorHtml));

$oNetworkTab = Admin_Form_Entity::factory('Tab')
    ->name('network')
    ->caption(Core::_('Optimizer.tab_network'));
$oNetworkTab->add(Admin_Form_Entity::factory('Code')->html('<h4>' . htmlspecialchars(Core::_('Optimizer.section_dns'), ENT_QUOTES, 'UTF-8') . '</h4>'));
optimizerAddSwitch($oNetworkTab, 'dns_prefetch_enabled', Core::_('Optimizer.dns_prefetch_enabled'), $settings);
optimizerAddTextarea($oNetworkTab, 'dns_prefetch', Core::_('Optimizer.dns_prefetch'), $settings, 5);
$oNetworkTab->add(Admin_Form_Entity::factory('Code')->html('<hr><h4>' . htmlspecialchars(Core::_('Optimizer.section_preconnect'), ENT_QUOTES, 'UTF-8') . '</h4>'));
optimizerAddSwitch($oNetworkTab, 'preconnect_enabled', Core::_('Optimizer.preconnect_enabled'), $settings);
optimizerAddTextarea($oNetworkTab, 'preconnect', Core::_('Optimizer.preconnect'), $settings, 5);

$oFontsTab = Admin_Form_Entity::factory('Tab')
    ->name('fonts')
    ->caption(Core::_('Optimizer.tab_fonts'));
$oFontsTab->add(Admin_Form_Entity::factory('Code')->html('<h4>' . htmlspecialchars(Core::_('Optimizer.section_fonts'), ENT_QUOTES, 'UTF-8') . '</h4>'));
optimizerAddSwitch($oFontsTab, 'preload_fonts_enabled', Core::_('Optimizer.preload_fonts_enabled'), $settings);
optimizerAddTextarea($oFontsTab, 'preload_fonts', Core::_('Optimizer.preload_fonts'), $settings, 6);

$oTabs = Admin_Form_Entity::factory('Tabs');
$oTabs
    ->add($oMainTab)
    ->add($oImagesTab)
    ->add($oNetworkTab)
    ->add($oFontsTab);
$oForm->add($oTabs);

$ajaxPath = Admin_Form_Controller::correctBackendPath('/{admin}/optimizer/index.php');
$script = '<script>(function(){'
    . 'var root=document.getElementById("id_content");if(!root){return;}'
    . 'var status=root.querySelector("#optimizer-save-status");var timers={};var generationStopped=false;'
    . 'function setStatus(text,isError){if(!status){return;}status.textContent=text;status.className=isError?"text-danger":"text-muted";}'
    . 'function post(body){return fetch(' . json_encode($ajaxPath) . ',{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8","X-Requested-With":"XMLHttpRequest"},body:body.toString()}).then(function(response){if(!response.ok){throw new Error("HTTP "+response.status);}return response.json();});}'
    . 'function updateSummary(data){var mode=root.querySelector("#optimizer-status-mode");var enabled=root.querySelector("#optimizer-status-enabled");if(mode&&data.mode){mode.textContent=data.mode;}if(enabled&&data.enabledCount!==undefined){enabled.textContent=data.enabledCount;}}'
    . 'function saveField(field){var name=field.name;if(!name){return;}var value=field.type==="checkbox"?(field.checked?"1":"0"):field.value;var body=new URLSearchParams();body.set("ajax_save","1");body.set("token",' . json_encode($optimizerAjaxToken) . ');body.set("name",name);body.set("value",value);setStatus(' . json_encode(Core::_('Optimizer.messages_saving')) . ',false);post(body).then(function(data){if(!data.success){throw new Error(data.message||"Save failed");}if(field.type==="number"&&data.value!==null){field.value=data.value;}updateSummary(data);setStatus(data.message,false);}).catch(function(error){setStatus(error.message||' . json_encode(Core::_('Optimizer.messages_save_error')) . ',true);});}'
    . 'function criticalTextarea(){return root.querySelector("textarea[name=critical_css]");}'
    . 'function criticalAce(textarea){try{var group=textarea?textarea.closest(".form-group"):null;var node=group?group.querySelector(".ace_editor"):null;return node&&window.ace?window.ace.edit(node):null;}catch(e){return null;}}'
    . 'function criticalCodeMirror(textarea){try{if(textarea&&textarea.nextElementSibling&&textarea.nextElementSibling.CodeMirror){return textarea.nextElementSibling.CodeMirror;}if(textarea&&textarea.CodeMirror){return textarea.CodeMirror;}}catch(e){}return null;}'
    . 'function criticalCssValue(){var textarea=criticalTextarea();if(!textarea){return "";}var aceEditor=criticalAce(textarea);if(aceEditor){return aceEditor.getValue();}var codeMirror=criticalCodeMirror(textarea);if(codeMirror){return codeMirror.getValue();}return textarea.value;}'
    . 'function setCriticalEditorEnabled(enabled){var textarea=criticalTextarea();var wrapper=root.querySelector(".optimizer-critical-editor");if(textarea){textarea.disabled=!enabled;}var aceEditor=criticalAce(textarea);if(aceEditor){aceEditor.setReadOnly(!enabled);aceEditor.renderer.$cursorLayer.element.style.display=enabled?"":"none";}var codeMirror=criticalCodeMirror(textarea);if(codeMirror){codeMirror.setOption("readOnly",!enabled);codeMirror.refresh();}if(wrapper){wrapper.classList.toggle("optimizer-critical-disabled",!enabled);}}'
    . 'function saveCriticalCss(){var button=root.querySelector("#optimizer-save-critical-css");var box=root.querySelector("#optimizer-critical-status");var toggle=root.querySelector("input[name=critical_css_enabled]");var body=new URLSearchParams();body.set("ajax_save_critical_css","1");body.set("token",' . json_encode($optimizerAjaxToken) . ');body.set("critical_css_enabled",toggle&&toggle.checked?"1":"0");body.set("critical_css",criticalCssValue());if(button){button.disabled=true;}if(box){box.textContent=' . json_encode(Core::_('Optimizer.messages_saving')) . ';box.className="optimizer-critical-status text-muted";}post(body).then(function(data){if(!data.success){throw new Error(data.message||"Save failed");}updateSummary(data);if(box){box.textContent=data.message;box.className="optimizer-critical-status text-success";}}).catch(function(error){if(box){box.textContent=error.message||' . json_encode(Core::_('Optimizer.messages_save_error')) . ';box.className="optimizer-critical-status text-danger";}}).then(function(){if(button){button.disabled=false;}});}'
    . 'function renderGeneration(data){var box=root.querySelector("#optimizer-generation-status");if(!box){return;}var text=data.message||"";if(data.found!==undefined){text+=" Найдено: "+data.found+". Обработано: "+data.processed+". Создано: "+data.generated+". Пропущено: "+data.skipped+". Ошибок: "+data.failed+". Осталось: "+data.remaining+".";}if(data.errors&&data.errors.length){text+=" "+data.errors.join("; ");}box.textContent=text;box.className="alert "+(data.success&&data.failed===0?"alert-success":"alert-warning")+" margin-top-10";}'
    . 'function finishGeneration(){var start=root.querySelector("#optimizer-generate-images");var stop=root.querySelector("#optimizer-stop-generation");if(start){start.disabled=false;}if(stop){stop.disabled=true;}}'
    . 'function runGeneration(){if(generationStopped){finishGeneration();return;}var body=new URLSearchParams();body.set("ajax_generate_images","1");body.set("token",' . json_encode($optimizerAjaxToken) . ');post(body).then(function(data){if(!data.success){throw new Error(data.message||"Generation failed");}renderGeneration(data);if(data.remaining>0&&data.failed===0&&!generationStopped){setTimeout(runGeneration,250);}else{finishGeneration();}}).catch(function(error){renderGeneration({success:false,message:error.message||"Generation failed"});finishGeneration();});}'
    . 'root.querySelectorAll(".optimizer-switch input[type=checkbox][name]").forEach(function(field){if(field.name==="critical_css_enabled"){field.addEventListener("change",function(){setCriticalEditorEnabled(field.checked);var box=root.querySelector("#optimizer-critical-status");if(box){box.textContent="";}});return;}field.addEventListener("change",function(){saveField(field);});});'
    . 'root.querySelectorAll(".optimizer-live-setting[name]").forEach(function(field){field.addEventListener("input",function(){clearTimeout(timers[field.name]);timers[field.name]=setTimeout(function(){saveField(field);},700);});field.addEventListener("blur",function(){clearTimeout(timers[field.name]);saveField(field);});});'
    . 'var criticalToggle=root.querySelector("input[name=critical_css_enabled]");setTimeout(function(){setCriticalEditorEnabled(!!(criticalToggle&&criticalToggle.checked));},0);setTimeout(function(){setCriticalEditorEnabled(!!(criticalToggle&&criticalToggle.checked));},300);'
    . 'var criticalButton=root.querySelector("#optimizer-save-critical-css");if(criticalButton){criticalButton.addEventListener("click",saveCriticalCss);}'
    . 'var start=root.querySelector("#optimizer-generate-images");var stop=root.querySelector("#optimizer-stop-generation");if(start){start.addEventListener("click",function(){generationStopped=false;start.disabled=true;if(stop){stop.disabled=false;}var box=root.querySelector("#optimizer-generation-status");if(box){box.textContent=' . json_encode(Core::_('Optimizer.image_generation_running')) . ';box.className="alert alert-info margin-top-10";}runGeneration();});}if(stop){stop.addEventListener("click",function(){generationStopped=true;finishGeneration();});}'
    . '})();</script>';
$oForm->add(Admin_Form_Entity::factory('Code')->html($script));

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
