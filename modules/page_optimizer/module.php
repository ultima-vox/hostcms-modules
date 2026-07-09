<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Page Optimizer Module for HostCMS 7
 * New generation optimization module
 */
class PageOptimizer_Module extends Core_Module
{
    public $version = '1.0';
    public $date = '2026-07-09';

    protected $_moduleName = 'page_optimizer';

    public function __construct()
    {
        parent::__construct();

        $this->menu = array(
            array(
                'sorting' => 10,
                'block'   => 1,
                'ico'     => 'fa fa-tachometer-alt',
                'name'    => Core::_('PageOptimizer.menu_name'),
                'href'    => '/admin/page_optimizer/index.php',
                'onclick' => "$.adminLoad({path: '/admin/page_optimizer/index.php'}); return false"
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