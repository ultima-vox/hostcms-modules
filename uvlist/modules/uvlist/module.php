<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * UVList module.
 *
 * @package HostCMS\Uvlist
 */
class Uvlist_Module extends Core_Module_Abstract
{
	const ADMIN_FORM_ID = 990020;
	const ADMIN_FORM_ITEM_ID = 990021;

	public $version = '0.1.1';
	public $date = '2026-07-05';

	protected $_moduleName = 'uvlist';

	public function __construct()
	{
		parent::__construct();

		$this->menu = array(
			array(
				'sorting' => 110,
				'block' => 1,
				'ico' => 'fa fa-list-ul',
				'name' => Core::_('Uvlist.menu'),
				'href' => Admin_Form_Controller::correctBackendPath('/{admin}/uvlist/index.php'),
				'onclick' => Admin_Form_Controller::correctBackendPath("$.adminLoad({path: '/{admin}/uvlist/index.php'}); return false")
			)
		);
	}

	public function install()
	{
		require_once __DIR__ . '/install/installer.php';

		$oInstaller = new Uvlist_Installer();
		$oInstaller->install();

		return $this;
	}
}
