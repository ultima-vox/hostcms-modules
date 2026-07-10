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
 * @version 7.x
 */
class PageOptimizer_Module extends Core_Module_Abstract
{
    /**
     * Module version.
     *
     * @var string
     */
    public $version = '1.2';

    /**
     * Module date.
     *
     * @var string
     */
    public $date = '2026-07-10';

    /**
     * Module name.
     *
     * @var string
     */
    protected $_moduleName = 'page_optimizer';

    /**
     * Constructor.
     */
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

    /**
     * Process completed frontend HTML response.
     *
     * @param mixed $args Event arguments
     * @return void
     */
    public function onAfterShowTemplate($args)
    {
        if (!Core::moduleIsActive($this->_moduleName)) {
            return;
        }

        $response = Core_Response::instance();
        $html = method_exists($response, 'getBody') ? $response->getBody() : NULL;

        if (!is_string($html) || $html === '') {
            return;
        }

        if (method_exists($response, 'setBody')) {
            $response->setBody(PageOptimizer::process($html));
        }
    }

    /**
     * Install module.
     *
     * The current version stores settings in the module cache directory and
     * therefore does not require database schema changes.
     *
     * @return void
     */
    public function install()
    {
    }

    /**
     * Uninstall module.
     *
     * @return void
     */
    public function uninstall()
    {
    }
}
