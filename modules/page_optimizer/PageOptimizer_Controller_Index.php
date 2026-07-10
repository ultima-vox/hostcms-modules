<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Page Optimizer administration dashboard.
 */
class PageOptimizer_Controller_Index
{
    protected $_path = '/{admin}/page_optimizer/index.php';

    public function execute()
    {
        $message = '';
        $messageType = 'success';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->checkCsrfToken((string) Core_Array::getPost('csrf_token', ''))) {
                $message = Core::_('PageOptimizer.csrf_error');
                $messageType = 'danger';
            }
            else {
                $this->save();
                $message = Core::_('PageOptimizer.messages_success_save');
            }
        }

        $this->show($message, $messageType);
    }

    protected function save()
    {
        $siteId = defined('CURRENT_SITE') ? CURRENT_SITE : 0;
        $settings = PageOptimizer_Settings::get($siteId);

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
            'critical_css_enabled',
        );

        foreach ($booleanKeys as $key) {
            $settings[$key] = (bool) Core_Array::getPost($key, 0);
        }

        $settings['dns_prefetch'] = trim((string) Core_Array::getPost('dns_prefetch', ''));
        $settings['preconnect'] = trim((string) Core_Array::getPost('preconnect', ''));
        $settings['preload_fonts'] = trim((string) Core_Array::getPost('preload_fonts', ''));
        $settings['critical_css'] = trim((string) Core_Array::getPost('critical_css', ''));

        PageOptimizer_Settings::save($settings, $siteId);
    }

    protected function show($message = '', $messageType = 'success')
    {
        $siteId = defined('CURRENT_SITE') ? CURRENT_SITE : 0;
        $settings = PageOptimizer_Settings::get($siteId);
        $stats = PageOptimizer_Settings::getStatsSummary($siteId);
        $path = Admin_Form_Controller::correctBackendPath($this->_path);
        $csrfToken = $this->getCsrfToken();

        $enabledCount = 0;
        foreach ($settings as $key => $value) {
            if (is_bool($value) && $value) {
                $enabledCount++;
            }
        }

        echo '<div class="row">';
        echo '<div class="col-xs-12">';
        echo '<h5 class="row-title before-blue"><i class="fa fa-tachometer"></i> ' . $this->escape(Core::_('PageOptimizer.title')) . '</h5>';

        if ($message !== '') {
            echo '<div class="alert alert-' . $this->escape($messageType) . '">' . $this->escape($message) . '</div>';
        }

        echo '<div class="row">';
        $this->renderStatusCard(Core::_('PageOptimizer.status_mode'), $enabledCount === 0 ? Core::_('PageOptimizer.safe_mode') : Core::_('PageOptimizer.custom_mode'), 'fa-shield');
        $this->renderStatusCard(Core::_('PageOptimizer.status_enabled'), (string) $enabledCount, 'fa-check-square-o');
        $this->renderStatusCard(Core::_('PageOptimizer.total_saved'), $stats['total'], 'fa-compress');
        $this->renderStatusCard(Core::_('PageOptimizer.requests_saved'), (string) $stats['requests'], 'fa-exchange');
        echo '</div>';

        echo '<form id="page_optimizer_form" method="post" action="' . $this->escape($path) . '">';
        echo '<input type="hidden" name="csrf_token" value="' . $this->escape($csrfToken) . '">';

        echo '<div class="widget flat radius-bordered">';
        echo '<div class="widget-header bg-blue"><span class="widget-caption">' . $this->escape(Core::_('PageOptimizer.tab_main')) . '</span></div>';
        echo '<div class="widget-body">';

        echo '<div class="alert alert-info">' . $this->escape(Core::_('PageOptimizer.safe_mode_notice')) . '</div>';

        echo '<div class="row">';
        echo '<div class="col-md-6">';
        $this->renderCheckbox('minify_html', Core::_('PageOptimizer.minify_html'), $settings);
        $this->renderCheckbox('html_remove_comments', Core::_('PageOptimizer.html_remove_comments'), $settings);
        $this->renderCheckbox('lazy_load_images', Core::_('PageOptimizer.lazy_load_images'), $settings);
        $this->renderCheckbox('rewrite_webp', Core::_('PageOptimizer.rewrite_webp'), $settings);
        $this->renderCheckbox('rewrite_avif', Core::_('PageOptimizer.rewrite_avif'), $settings);
        echo '</div>';

        echo '<div class="col-md-6">';
        $this->renderCheckbox('combine_css', Core::_('PageOptimizer.combine_css'), $settings, true);
        $this->renderCheckbox('minify_css', Core::_('PageOptimizer.minify_css'), $settings, true);
        $this->renderCheckbox('combine_js', Core::_('PageOptimizer.combine_js'), $settings, true);
        $this->renderCheckbox('minify_js', Core::_('PageOptimizer.minify_js'), $settings, true);
        echo '</div>';
        echo '</div>';

        echo '<hr>';
        echo '<h5>' . $this->escape(Core::_('PageOptimizer.head_optimization')) . '</h5>';

        $this->renderCheckbox('dns_prefetch_enabled', Core::_('PageOptimizer.dns_prefetch_enabled'), $settings);
        $this->renderTextarea('dns_prefetch', Core::_('PageOptimizer.dns_prefetch'), $settings);

        $this->renderCheckbox('preconnect_enabled', Core::_('PageOptimizer.preconnect_enabled'), $settings);
        $this->renderTextarea('preconnect', Core::_('PageOptimizer.preconnect'), $settings);

        $this->renderCheckbox('preload_fonts_enabled', Core::_('PageOptimizer.preload_fonts_enabled'), $settings);
        $this->renderTextarea('preload_fonts', Core::_('PageOptimizer.preload_fonts'), $settings);

        $this->renderCheckbox('critical_css_enabled', Core::_('PageOptimizer.critical_css_enabled'), $settings);
        $this->renderTextarea('critical_css', Core::_('PageOptimizer.critical_css'), $settings, 8);

        echo '<div class="form-group margin-top-20">';
        echo '<button type="submit" class="btn btn-blue"><i class="fa fa-save"></i> ' . $this->escape(Core::_('PageOptimizer.save')) . '</button>';
        echo '</div>';

        echo '</div></div></form>';

        echo '<script>$(function(){var f=$("#page_optimizer_form");f.on("submit",function(e){e.preventDefault();$.post(f.attr("action"),f.serialize(),function(html){$("#page_optimizer_form").closest(".row").parent().html(html);});});});</script>';
        echo '</div></div>';
    }

    protected function renderStatusCard($title, $value, $icon)
    {
        echo '<div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">';
        echo '<div class="databox radius-bordered databox-shadowed databox-graded">';
        echo '<div class="databox-left bg-blue"><div class="databox-piechart"><i class="fa ' . $this->escape($icon) . ' fa-2x white"></i></div></div>';
        echo '<div class="databox-right"><span class="databox-number blue">' . $this->escape($value) . '</span><div class="databox-text darkgray">' . $this->escape($title) . '</div></div>';
        echo '</div></div>';
    }

    protected function renderCheckbox($name, $caption, array $settings, $experimental = false)
    {
        $checked = !empty($settings[$name]) ? ' checked' : '';
        echo '<div class="checkbox"><label><input type="checkbox" name="' . $this->escape($name) . '" value="1"' . $checked . '><span class="text">' . $this->escape($caption);
        if ($experimental) {
            echo ' <span class="label label-warning">' . $this->escape(Core::_('PageOptimizer.experimental')) . '</span>';
        }
        echo '</span></label></div>';
    }

    protected function renderTextarea($name, $caption, array $settings, $rows = 3)
    {
        echo '<div class="form-group"><label for="' . $this->escape($name) . '">' . $this->escape($caption) . '</label>';
        echo '<textarea class="form-control" id="' . $this->escape($name) . '" name="' . $this->escape($name) . '" rows="' . (int) $rows . '">' . $this->escape((string)($settings[$name] ?? '')) . '</textarea></div>';
    }

    protected function getCsrfToken()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if (empty($_SESSION['page_optimizer_csrf'])) {
            $_SESSION['page_optimizer_csrf'] = bin2hex(random_bytes(24));
        }

        return $_SESSION['page_optimizer_csrf'];
    }

    protected function checkCsrfToken($token)
    {
        $stored = $this->getCsrfToken();
        return $token !== '' && hash_equals($stored, $token);
    }

    protected function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
