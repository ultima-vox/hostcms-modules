<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

class PageOptimizer_Controller_Edit extends Admin_Form_Controller
{
    protected $_moduleName = 'page_optimizer';

    public function __construct()
    {
        parent::__construct();

        $this->setTitle(Core::_('PageOptimizer.title'));
    }

    public function show()
    {
        $siteId = CURRENT_SITE;
        $settings = PageOptimizer_Settings::get($siteId);
        $stats = PageOptimizer_Settings::getStatsSummary($siteId);

        // Простая форма для начала
        $oMainTab = Admin_Form_Entity::factory('Tab')
            ->caption(Core::_('PageOptimizer.tab_main'))
            ->add(Admin_Form_Entity::factory('Div')->class('row'));

        $oMainTab->add(
            Admin_Form_Entity::factory('Checkbox')
                ->name('minify_html')
                ->caption(Core::_('PageOptimizer.minify_html'))
                ->value($settings['minify_html'] ? 1 : 0)
        );

        $oMainTab->add(
            Admin_Form_Entity::factory('Checkbox')
                ->name('combine_css')
                ->caption(Core::_('PageOptimizer.combine_css'))
                ->value($settings['combine_css'] ? 1 : 0)
        );

        $oMainTab->add(
            Admin_Form_Entity::factory('Checkbox')
                ->name('combine_js')
                ->caption(Core::_('PageOptimizer.combine_js'))
                ->value($settings['combine_js'] ? 1 : 0)
        );

        $oMainTab->add(
            Admin_Form_Entity::factory('Checkbox')
                ->name('lazy_load_images')
                ->caption(Core::_('PageOptimizer.lazy_load_images'))
                ->value($settings['lazy_load_images'] ? 1 : 0)
        );

        // Статистика
        $oMainTab->add(
            Admin_Form_Entity::factory('Code')
                ->html('<h4>' . Core::_('PageOptimizer.stats') . '</h4>' .
                    '<p>' . Core::_('PageOptimizer.total_saved') . ': <strong>' . $stats['total'] . '</strong></p>' .
                    '<p>CSS: ' . $stats['css'] . ' | JS: ' . $stats['js'] . ' | Requests saved: ' . $stats['requests'] . '</p>')
        );

        $oMainTab->add(
            Admin_Form_Entity::factory('Button')
                ->name('save')
                ->caption(Core::_('Admin_Form.save'))
                ->class('btn btn-primary')
                ->onclick("$(\'#page_optimizer_form\').submit(); return false;")
        );

        $this->addTab($oMainTab);

        parent::show();
    }

    public function save()
    {
        $siteId = CURRENT_SITE;
        $settings = PageOptimizer_Settings::get($siteId);

        $settings['minify_html']      = (bool) Core_Array::getPost('minify_html', 0);
        $settings['combine_css']      = (bool) Core_Array::getPost('combine_css', 0);
        $settings['combine_js']       = (bool) Core_Array::getPost('combine_js', 0);
        $settings['lazy_load_images'] = (bool) Core_Array::getPost('lazy_load_images', 0);

        PageOptimizer_Settings::save($settings, $siteId);

        $this->addMessage(Core::_('Admin_Form.messages_success_save'));
        $this->redirect();
    }
}