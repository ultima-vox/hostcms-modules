<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * UVList item model.
 *
 * @package HostCMS\Uvlist
 */
class Uvlist_Item_Model extends Core_Entity
{
	public $img = 1;

	protected $_nameColumn = 'value';

	protected $_hasMany = array(
		'uvlist_item' => array('foreign_key' => 'parent_id')
	);

	protected $_belongsTo = array(
		'uvlist' => array(),
		'uvlist_item' => array('foreign_key' => 'parent_id'),
		'user' => array()
	);

	protected $_sorting = array(
		'uvlist_items.sorting' => 'ASC',
		'uvlist_items.value' => 'ASC'
	);

	protected $_preloadValues = array(
		'parent_id' => 0,
		'active' => 1,
		'sorting' => 0
	);

	public function __construct($id = NULL)
	{
		parent::__construct($id);

		if (is_null($id) && !$this->loaded())
		{
			$oUser = Core_Auth::getCurrentUser();
			$this->_preloadValues['user_id'] = is_null($oUser) ? 0 : $oUser->id;
		}
	}

	public function getParent()
	{
		return $this->parent_id
			? Core_Entity::factory('Uvlist_Item')->find($this->parent_id)
			: NULL;
	}

	public function changeStatus()
	{
		$this->active = 1 - $this->active;
		return $this->save();
	}

	public function delete($primaryKey = NULL)
	{
		if (is_null($primaryKey))
		{
			$primaryKey = $this->getPrimaryKey();
		}

		$this->id = $primaryKey;
		$this->Uvlist_Items->deleteAll(FALSE);

		return parent::delete($primaryKey);
	}
}
