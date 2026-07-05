<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * UVList directory model.
 *
 * @package HostCMS\Uvlist
 */
class Uvlist_Dir_Model extends Core_Entity
{
	public $img = 0;

	protected $_hasMany = array(
		'uvlist' => array(),
		'uvlist_dir' => array('foreign_key' => 'parent_id')
	);

	protected $_belongsTo = array(
		'uvlist_dir' => array('foreign_key' => 'parent_id'),
		'site' => array(),
		'user' => array()
	);

	protected $_sorting = array(
		'uvlist_dirs.sorting' => 'ASC',
		'uvlist_dirs.name' => 'ASC'
	);

	protected $_preloadValues = array(
		'parent_id' => 0,
		'sorting' => 0
	);

	public function __construct($id = NULL)
	{
		parent::__construct($id);

		if (is_null($id) && !$this->loaded())
		{
			$oUser = Core_Auth::getCurrentUser();
			$this->_preloadValues['user_id'] = is_null($oUser) ? 0 : $oUser->id;
			$this->_preloadValues['site_id'] = defined('CURRENT_SITE') ? CURRENT_SITE : 0;
		}
	}

	public function getParent()
	{
		return $this->parent_id
			? Core_Entity::factory('Uvlist_Dir')->find($this->parent_id)
			: NULL;
	}

	public function delete($primaryKey = NULL)
	{
		if (is_null($primaryKey))
		{
			$primaryKey = $this->getPrimaryKey();
		}

		$this->id = $primaryKey;
		$this->Uvlists->deleteAll(FALSE);
		$this->Uvlist_Dirs->deleteAll(FALSE);

		return parent::delete($primaryKey);
	}
}
