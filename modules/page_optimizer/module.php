<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

require_once __DIR__ . '/PageOptimizer_Context.php';
require_once __DIR__ . '/PageOptimizer_Settings.php';
require_once __DIR__ . '/PageOptimizer_Html.php';
require_once __DIR__ . '/PageOptimizer_Assets.php';
require_once __DIR__ . '/PageOptimizer.php';

/**
 * Page Optimizer Module for HostCMS 7.
 *
 * @package HostCMS
 * @subpackage PageOptimizer
 */
class Page_Optimizer_Module extends Core_Module_Abstract
{
    public $version = '1.4';
    public $date = '2026-07-10';

    protected $_moduleName = 'page_optimizer';

    public function __construct()
    {
        parent::__construct();

        $this->menu = array(
            array(
                'sorting' => 10,
                'block' => 1,
                'ico' => 'fa fa-tachometer',
                'name' => Core::_('PageOptimizer.menu_name'),
                'href' => Admin_Form_Controller::correctBackendPath('/{admin}/page_optimizer/index.php'),
                'onclick' => Admin_Form_Controller::correctBackendPath("$.adminLoad({path: '/{admin}/page_optimizer/index.php'}); return false")
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
            $response->setBody(PageOptimizer::process($html));
        }
    }

    public function install()
    {
        $siteId = defined('CURRENT_SITE') ? CURRENT_SITE : 0;

        if (!PageOptimizer_Settings::install($siteId)) {
            throw new Exception(
                'Page Optimizer: cannot create writable directory '
                . PageOptimizer_Settings::getDirectory()
            );
        }
    }

    public function uninstall()
    {
        PageOptimizer_Settings::uninstall();
    }
}

// Compatibility with HostCMS builds that derive the module class name
// without preserving the underscore from the module code.
if (!class_exists('PageOptimizer_Module', false)) {
    class_alias('Page_Optimizer_Module', 'PageOptimizer_Module');
}
