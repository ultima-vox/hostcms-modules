<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

class Uvlist_Controller_Edit extends Admin_Form_Action_Controller_Type_Edit
{
	public function setObject($object)
	{
		parent::setObject($object);

		$modelName = $this->_object->getModelName();
		$oMainTab = $this->getTab('main');
		$oAdditionalTab = $this->getTab('additional');
		$oSelectDirs = Admin_Form_Entity::factory('Select');

		switch ($modelName)
		{
			case 'uvlist':
				$title = $this->_object->id
					? Core::_('Uvlist.edit_title', $this->_object->name)
					: Core::_('Uvlist.add_title');

				if (!$this->_object->id)
				{
					$this->_object->uvlist_dir_id = intval(Core_Array::getGet('uvlist_dir_id', 0));
					$this->_object->site_id = CURRENT_SITE;
				}

				$oAdditionalTab->delete($this->getField('uvlist_dir_id'));

				$oSelectDirs
					->options(array(' … ') + $this->fillUvlistDir())
					->name('uvlist_dir_id')
					->value($this->_object->uvlist_dir_id)
					->caption(Core::_('Uvlist_Dir.parent_name'));

				$oMainTab->addAfter($oSelectDirs, $this->getField('description'));
				$oAdditionalTab->delete($this->getField('site_id'));

				$oUser_Controller_Edit = new User_Controller_Edit($this->_Admin_Form_Action);
				$oSelectSites = Admin_Form_Entity::factory('Select')
					->options($oUser_Controller_Edit->fillSites())
					->name('site_id')
					->value($this->_object->site_id)
					->caption(Core::_('Uvlist.site_id'));

				$oMainTab->addAfter($oSelectSites, $oSelectDirs);
			break;
			case 'uvlist_dir':
			default:
				$title = $this->_object->id
					? Core::_('Uvlist_Dir.edit_title')
					: Core::_('Uvlist_Dir.add_title');

				if (!$this->_object->id)
				{
					$this->_object->parent_id = intval(Core_Array::getGet('uvlist_dir_id', 0));
					$this->_object->site_id = CURRENT_SITE;
				}

				$oAdditionalTab->delete($this->getField('parent_id'));

				$oSelectDirs
					->options(array(' … ') + $this->fillUvlistDir(0, $this->_object->id))
					->name('parent_id')
					->value($this->_object->parent_id)
					->caption(Core::_('Uvlist_Dir.parent_name'));

				$oMainTab->addAfter($oSelectDirs, $this->getField('description'));
			break;
		}

		$this->title($title);

		return $this;
	}

	public function execute($operation = NULL)
	{
		if (!is_null($operation) && $operation != '' && $this->_object->getModelName() == 'uvlist')
		{
			$name = trim(strval(Core_Array::get($this->_formValues, 'name', '')));
			$site_id = intval(Core_Array::get($this->_formValues, 'site_id', CURRENT_SITE));
			$dir_id = intval(Core_Array::get($this->_formValues, 'uvlist_dir_id', 0));
			$id = intval(Core_Array::get($this->_formValues, 'id', 0));

			$oSame = Core_Entity::factory('Uvlist');
			$oSame->queryBuilder()
				->where('uvlists.site_id', '=', $site_id)
				->where('uvlists.uvlist_dir_id', '=', $dir_id)
				->where('uvlists.name', '=', $name)
				->where('uvlists.id', '!=', $id)
				->where('uvlists.deleted', '=', 0);

			if ($oSame->getCount(FALSE))
			{
				$this->addMessage(Core_Message::get(Core::_('Uvlist.list_exists'), 'error'));
				return TRUE;
			}
		}

		return parent::execute($operation);
	}

	public function fillUvlistDir($parent_id = 0, $exclude = FALSE, $level = 0)
	{
		$aReturn = array();
		$oDirs = Core_Entity::factory('Uvlist_Dir');
		$oDirs->queryBuilder()
			->where('uvlist_dirs.site_id', '=', CURRENT_SITE)
			->where('uvlist_dirs.parent_id', '=', intval($parent_id))
			->where('uvlist_dirs.deleted', '=', 0)
			->orderBy('uvlist_dirs.sorting', 'ASC')
			->orderBy('uvlist_dirs.name', 'ASC');

		foreach ($oDirs->findAll(FALSE) as $oDir)
		{
			if ($exclude != $oDir->id)
			{
				$aReturn[$oDir->id] = str_repeat('  ', intval($level)) . $oDir->name;
				$aReturn += $this->fillUvlistDir($oDir->id, $exclude, $level + 1);
			}
		}

		return $aReturn;
	}
}
