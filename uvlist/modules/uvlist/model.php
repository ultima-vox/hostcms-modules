<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * UVList model.
 *
 * @package HostCMS\Uvlist
 */
class Uvlist_Model extends Core_Entity
{
	public $img = 1;
	public $items = 0;

	protected $_hasMany = array(
		'uvlist_item' => array()
	);

	protected $_belongsTo = array(
		'uvlist_dir' => array(),
		'user' => array(),
		'site' => array()
	);

	protected $_sorting = array(
		'uvlists.sorting' => 'ASC',
		'uvlists.name' => 'ASC'
	);

	protected $_preloadValues = array(
		'uvlist_dir_id' => 0,
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
			$this->_preloadValues['site_id'] = defined('CURRENT_SITE') ? CURRENT_SITE : 0;
		}
	}

	public function copy()
	{
		$newObject = parent::copy();
		$aMap = array();

		$aItems = $this->Uvlist_Items->findAll(FALSE);
		foreach ($aItems as $oItem)
		{
			$oNewItem = clone $oItem;
			$newObject->add($oNewItem);
			$aMap[$oItem->id] = $oNewItem->id;
		}

		$aNewItems = $newObject->Uvlist_Items->findAll(FALSE);
		foreach ($aNewItems as $oItem)
		{
			$oItem->parent_id = Core_Array::get($aMap, $oItem->parent_id, 0);
			$oItem->save();
		}

		return $newObject;
	}

	public function getByCode($code, $site_id = NULL)
	{
		$site_id = is_null($site_id)
			? (defined('CURRENT_SITE') ? CURRENT_SITE : 0)
			: intval($site_id);

		$this->queryBuilder()
			->clear()
			->where('code', '=', strval($code))
			->where('site_id', '=', $site_id)
			->where('deleted', '=', 0)
			->limit(1);

		$aObjects = $this->findAll(FALSE);

		return isset($aObjects[0]) ? $aObjects[0] : NULL;
	}

	public function getByNameAndSite($name, $site_id)
	{
		$this->queryBuilder()
			->clear()
			->where('name', '=', strval($name))
			->where('site_id', '=', intval($site_id))
			->where('deleted', '=', 0)
			->limit(1);

		$aObjects = $this->findAll(FALSE);

		return isset($aObjects[0]) ? $aObjects[0] : NULL;
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

	public function itemsBadge($oAdmin_Form_Field, $oAdmin_Form_Controller)
	{
		$count = $this->Uvlist_Items->getCount();

		$count && Core::factory('Core_Html_Entity_Span')
			->class('badge badge-ico badge-azure white')
			->value($count < 100 ? $count : '∞')
			->title($count)
			->execute();
	}

	public function getItemsTree($parent_id = 0, $level = 0)
	{
		$oItems = $this->Uvlist_Items;
		$oItems->queryBuilder()
			->where('uvlist_items.parent_id', '=', intval($parent_id))
			->where('uvlist_items.active', '=', 1)
			->where('uvlist_items.deleted', '=', 0)
			->orderBy('uvlist_items.sorting', 'ASC')
			->orderBy('uvlist_items.value', 'ASC');

		$aReturn = array();
		foreach ($oItems->findAll(FALSE) as $oItem)
		{
			$aReturn[$oItem->id] = str_repeat('  ', intval($level)) . $oItem->value;
			$aReturn += $this->getItemsTree($oItem->id, $level + 1);
		}

		return $aReturn;
	}
}
