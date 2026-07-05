<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * UVList module.
 *
 * @package HostCMS\Uvlist
 */
class Uvlist_Module extends Core_Module
{
	/**
	 * Backend form ID: lists/directories.
	 */
	const ADMIN_FORM_ID = 990020;

	/**
	 * Backend form ID: list items.
	 */
	const ADMIN_FORM_ITEM_ID = 990021;

	/**
	 * Module version.
	 * @var string
	 */
	public $version = '0.1.0';

	/**
	 * Module date.
	 * @var string
	 */
	public $date = '2026-07-05';

	/**
	 * Module name.
	 * @var string
	 */
	protected $_moduleName = 'uvlist';

	/**
	 * Get module menu.
	 *
	 * @return array
	 */
	public function getMenu()
	{
		$sPath = Admin_Form_Controller::correctBackendPath('/{admin}/uvlist/index.php');

		$this->menu = array(
			array(
				'sorting' => 110,
				'block' => 1,
				'ico' => 'fa fa-list-ul',
				'name' => Core::_('Uvlist.menu'),
				'href' => $sPath,
				'onclick' => "$.adminLoad({path: '{$sPath}'}); return false"
			)
		);

		return parent::getMenu();
	}

	/**
	 * Install module.
	 *
	 * @return self
	 */
	public function install()
	{
		$sSqlPath = __DIR__ . '/install/install.sql';

		if (is_file($sSqlPath))
		{
			$sSql = file_get_contents($sSqlPath);

			foreach ($this->_splitSql($sSql) as $sQuery)
			{
				Sql_Controller::instance()->execute($sQuery);
			}
		}

		return $this;
	}

	/**
	 * Split SQL script by semicolon.
	 *
	 * @param string $sql SQL script
	 * @return array
	 */
	protected function _splitSql($sql)
	{
		$aReturn = array();
		$aParts = explode(';', strval($sql));

		foreach ($aParts as $sPart)
		{
			$sPart = trim($sPart);

			if ($sPart !== '')
			{
				$aReturn[] = $sPart;
			}
		}

		return $aReturn;
	}
}
