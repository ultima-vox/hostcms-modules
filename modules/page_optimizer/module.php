<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Page Optimizer Module for HostCMS 7.
 */
class PageOptimizer_Module extends Core_Module
{
    public $version = '1.1';
    public $date = '2026-07-10';

    protected $_moduleName = 'page_optimizer';

    public function __construct()
    {
        parent::__construct();

        $this->menu = array(
            array(
                'sorting' => 10,
                'block'   => 1,
                'ico'     => 'fa fa-tachometer',
                'name'    => Core::_('PageOptimizer.menu_name'),
                'href'    => Admin_Form_Controller::correctBackendPath('/{admin}/page_optimizer/index.php'),
                'onclick' => Admin_Form_Controller::correctBackendPath("$.adminLoad({path: '/{admin}/page_optimizer/index.php'}); return false")
            )
        );

        $this->_registerEvents();
    }

    protected function _registerEvents()
    {
        Core_Event::attach('onAfterShowTemplate', array($this, 'onAfterShowTemplate'));
    }

    public function onAfterShowTemplate($args)
    {
        if (!Core::moduleIsActive('page_optimizer')) {
            return;
        }

        $response = Core_Response::instance();
        $html = method_exists($response, 'getBody') ? $response->getBody() : null;

        if (empty($html) || !is_string($html)) {
            return;
        }

        $optimized = PageOptimizer::process($html);

        if (method_exists($response, 'setBody')) {
            $response->setBody($optimized);
        }
    }

    public function install() {}
    public function uninstall() {}
}
