<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

require_once __DIR__ . '/bootstrap.php';

/**
 * Optimizer module for HostCMS 7 marketplace build.
 */
class Optimizer_Module extends Core_Module_Abstract
{
    public $version = '1.9.0';
    public $date = '2026-07-10';

    protected $_moduleName = 'optimizer';

    public function __construct()
    {
        parent::__construct();

        $this->menu = array(
            array(
                'sorting' => 10,
                'block' => 1,
                'ico' => 'fa fa-tachometer',
                'name' => Core::_('Optimizer.menu_name'),
                'href' => Admin_Form_Controller::correctBackendPath('/{admin}/optimizer/index.php'),
                'onclick' => Admin_Form_Controller::correctBackendPath("$.adminLoad({path: '/{admin}/optimizer/index.php'}); return false")
            )
        );

        if (Core::moduleIsActive($this->_moduleName)) {
            Optimizer::startOutputBuffer();
        }
    }

    public function install()
    {
        $siteId = defined('CURRENT_SITE') ? CURRENT_SITE : 0;
        Optimizer_Settings::install($siteId);
    }

    public function uninstall()
    {
        Optimizer_Settings::uninstall();
    }
}
