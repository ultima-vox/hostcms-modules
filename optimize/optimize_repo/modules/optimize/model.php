<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Optimize model.
 *
 * @package HostCMS 7\Optimize
 * @version 2.0
 * @see https://hostcms.ru/api7/classes/Core-Entity.html
 */
class Optimize_Model extends Core_Entity
{
	/**
	 * Backend property
	 * @var mixed
	 */
	public $img = 1;

	/**
	 * One-to-many or many-to-many relations
	 * @var array
	 */
	protected $_hasMany = array(
		'optimize_item' => array(),
		'optimize_group' => array(),
	);

	/**
	 * Belongs to relations
	 * @var array
	 */
	protected $_belongsTo = array(
		'site' => array(),
		'structure' => array(),
	);

	/**
	 * Constructor.
	 */
	public function __construct($id = NULL)
	{
		parent::__construct($id);

		if (is_null($id)) {
			$this->_preloadValues['site_id'] = defined('CURRENT_SITE') ? CURRENT_SITE : 0;
		}
	}

	public function config()
	{
	}
}
