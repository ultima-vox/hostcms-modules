<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

require_once __DIR__ . '/Optimizer_Context.php';
require_once __DIR__ . '/Optimizer_Settings.php';
require_once __DIR__ . '/Optimizer_Html.php';
require_once __DIR__ . '/Optimizer_Assets.php';
require_once __DIR__ . '/Optimizer.php';

/**
 * Optimizer module for HostCMS 7.
 */
class Optimizer_Module extends Core_Module_Abstract
{
    public $version = '1.5.3';
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

        Core_Event::attach('onAfterShowTemplate', array($this, 'onAfterShowTemplate'));
    }

    public function onAfterShowTemplate($args)
    {
        if (!Core::moduleIsActive($this->_moduleName)) {
            return;
        }

        $response = Core_Response::instance();
        $html = method_exists($response, 'getBody') ? $response->getBody() : null;

        if (!is_string($html) || $html === '') {
            return;
        }

        if (method_exists($response, 'setBody')) {
            $response->setBody(Optimizer::process($html));
        }
    }

    /**
     * Install module.
     *
     * HostCMS can invoke this method in a context where helper classes from the
     * module file have not yet been loaded, so the dependency is required here
     * explicitly before use.
     */
    public function install()
    {
        if (!class_exists('Optimizer_Settings', false)) {
            require_once __DIR__ . '/Optimizer_Settings.php';
        }

        $siteId = defined('CURRENT_SITE') ? CURRENT_SITE : 0;
        Optimizer_Settings::install($siteId);
    }

    public function uninstall()
    {
        if (!class_exists('Optimizer_Settings', false)) {
            require_once __DIR__ . '/Optimizer_Settings.php';
        }

        Optimizer_Settings::uninstall();
    }
}
