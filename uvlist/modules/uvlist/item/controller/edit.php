<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

class Uvlist_Item_Controller_Edit extends Admin_Form_Action_Controller_Type_Edit
{
	public function setObject($object)
	{
		parent::setObject($object);

		$oMainTab = $this->getTab('main');
		$oAdditionalTab = $this->getTab('additional');

		$title = $this->_object->id
			? Core::_('Uvlist_Item.edit_title', $this->_object->value)
			: Core::_('Uvlist_Item.add_title');

		if (!$this->_object->id)
		{
			$this->_object->uvlist_id = intval(Core_Array::getGet('uvlist_id', 0));
			$this->_object->parent_id = intval(Core_Array::getGet('parent_id', 0));
		}

		$oAdditionalTab->delete($this->getField('uvlist_id'));
		$oAdditionalTab->delete($this->getField('parent_id'));

		$oList = Core_Entity::factory('Uvlist')->find($this->_object->uvlist_id);

		$aItems = array(' … ');
		if (!is_null($oList->id))
		{
			$aItems += $oList->getItemsTree(0, 0);
			unset($aItems[$this->_object->id]);
		}

		$oSelectParents = Admin_Form_Entity::factory('Select')
			->options($aItems)
			->name('parent_id')
			->value($this->_object->parent_id)
			->caption(Core::_('Uvlist_Item.parent_id'));

		$oMainTab->addAfter($oSelectParents, $this->getField('description'));

		$oHiddenList = Admin_Form_Entity::factory('Input')
			->type('hidden')
			->name('uvlist_id')
			->value($this->_object->uvlist_id);

		$oMainTab->add($oHiddenList);
		$this->title($title);

		return $this;
	}

	public function execute($operation = NULL)
	{
		if (!is_null($operation) && $operation != '')
		{
			$value = trim(strval(Core_Array::get($this->_formValues, 'value', '')));
			$uvlist_id = intval(Core_Array::get($this->_formValues, 'uvlist_id', Core_Array::getGet('uvlist_id', 0)));
			$parent_id = intval(Core_Array::get($this->_formValues, 'parent_id', 0));
			$id = intval(Core_Array::get($this->_formValues, 'id', 0));

			$oSame = Core_Entity::factory('Uvlist_Item');
			$oSame->queryBuilder()
				->where('uvlist_items.uvlist_id', '=', $uvlist_id)
				->where('uvlist_items.parent_id', '=', $parent_id)
				->where('uvlist_items.value', '=', $value)
				->where('uvlist_items.id', '!=', $id)
				->where('uvlist_items.deleted', '=', 0);

			if ($oSame->getCount(FALSE))
			{
				$this->addMessage(Core_Message::get(Core::_('Uvlist_Item.item_exists'), 'error'));
				return TRUE;
			}
		}

		return parent::execute($operation);
	}
}
