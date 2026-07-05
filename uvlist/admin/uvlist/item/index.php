<?php

require_once('../../../bootstrap.php');

Core_Auth::authorization($sModule = 'uvlist');

$iAdmin_Form_Id = Uvlist_Module::ADMIN_FORM_ITEM_ID;
$sAdminFormAction = '/admin/uvlist/item/index.php';

$uvlist_id = intval(Core_Array::getGet('uvlist_id', 0));
$oUvlist = Core_Entity::factory('Uvlist')->find($uvlist_id);

$oAdmin_Form = Core_Entity::factory('Admin_Form', $iAdmin_Form_Id);

$oAdmin_Form_Controller = Admin_Form_Controller::create($oAdmin_Form);
$oAdmin_Form_Controller
	->module(Core_Module::factory($sModule))
	->setUp()
	->path($sAdminFormAction)
	->title(Core::_('Uvlist_Item.title', $oUvlist->name))
	->pageTitle(Core::_('Uvlist_Item.title', $oUvlist->name));

$oAdmin_Form_Entity_Menus = Admin_Form_Entity::factory('Menus');
$oAdmin_Form_Entity_Menus->add(
	Admin_Form_Entity::factory('Menu')
		->name(Core::_('Admin_Form.add'))
		->icon('fa fa-plus')
		->href($oAdmin_Form_Controller->getAdminActionLoadHref($oAdmin_Form_Controller->getPath(), 'edit', NULL, 0, 0))
		->onclick($oAdmin_Form_Controller->getAdminActionLoadAjax($oAdmin_Form_Controller->getPath(), 'edit', NULL, 0, 0))
);
$oAdmin_Form_Controller->addEntity($oAdmin_Form_Entity_Menus);

$oAdmin_Form_Entity_Breadcrumbs = Admin_Form_Entity::factory('Breadcrumbs');
$sListDirPath = '/admin/uvlist/index.php';
$sListPath = '/admin/uvlist/item/index.php?uvlist_id=' . $uvlist_id;

$oAdmin_Form_Entity_Breadcrumbs->add(
	Admin_Form_Entity::factory('Breadcrumb')
		->name(Core::_('Uvlist.title'))
		->href($oAdmin_Form_Controller->getAdminLoadHref($sListDirPath, NULL, NULL, ''))
		->onclick($oAdmin_Form_Controller->getAdminLoadAjax($sListDirPath, NULL, NULL, ''))
);

if ($oUvlist->uvlist_dir_id)
{
	$oDir = Core_Entity::factory('Uvlist_Dir')->find($oUvlist->uvlist_dir_id);

	if (!is_null($oDir->id))
	{
		$aBreadcrumbs = array();

		do {
			$additionalParams = 'uvlist_dir_id=' . intval($oDir->id);

			$aBreadcrumbs[] = Admin_Form_Entity::factory('Breadcrumb')
				->name($oDir->name)
				->href($oAdmin_Form_Controller->getAdminLoadHref($sListDirPath, NULL, NULL, $additionalParams))
				->onclick($oAdmin_Form_Controller->getAdminLoadAjax($sListDirPath, NULL, NULL, $additionalParams));
		} while ($oDir = $oDir->getParent());

		foreach (array_reverse($aBreadcrumbs) as $oBreadcrumb)
		{
			$oAdmin_Form_Entity_Breadcrumbs->add($oBreadcrumb);
		}
	}
}

$oAdmin_Form_Entity_Breadcrumbs->add(
	Admin_Form_Entity::factory('Breadcrumb')
		->name($oUvlist->name)
		->href($oAdmin_Form_Controller->getAdminLoadHref($sListPath, NULL, NULL, ''))
		->onclick($oAdmin_Form_Controller->getAdminLoadAjax($sListPath, NULL, NULL, ''))
);
$oAdmin_Form_Controller->addEntity($oAdmin_Form_Entity_Breadcrumbs);

$oAdmin_Form_Action = Core_Entity::factory('Admin_Form', $iAdmin_Form_Id)->Admin_Form_Actions->getByName('edit');
if ($oAdmin_Form_Action && $oAdmin_Form_Controller->getAction() == 'edit')
{
	$oControllerEdit = Admin_Form_Action_Controller::factory('Uvlist_Item_Controller_Edit', $oAdmin_Form_Action);
	$oControllerEdit->addEntity($oAdmin_Form_Entity_Breadcrumbs);
	$oAdmin_Form_Controller->addAction($oControllerEdit);
}

$oAdminFormActionApply = Core_Entity::factory('Admin_Form', $iAdmin_Form_Id)->Admin_Form_Actions->getByName('apply');
if ($oAdminFormActionApply && $oAdmin_Form_Controller->getAction() == 'apply')
{
	$oAdmin_Form_Controller->addAction(
		Admin_Form_Action_Controller::factory('Admin_Form_Action_Controller_Type_Apply', $oAdminFormActionApply)
	);
}

$oAdminFormActionCopy = Core_Entity::factory('Admin_Form', $iAdmin_Form_Id)->Admin_Form_Actions->getByName('copy');
if ($oAdminFormActionCopy && $oAdmin_Form_Controller->getAction() == 'copy')
{
	$oAdmin_Form_Controller->addAction(
		Admin_Form_Action_Controller::factory('Admin_Form_Action_Controller_Type_Copy', $oAdminFormActionCopy)
	);
}

$oAdminFormActionChangeStatus = Core_Entity::factory('Admin_Form', $iAdmin_Form_Id)->Admin_Form_Actions->getByName('changeStatus');
if ($oAdminFormActionChangeStatus && $oAdmin_Form_Controller->getAction() == 'changeStatus')
{
	$oAdmin_Form_Controller->addAction(
		Admin_Form_Action_Controller::factory('Admin_Form_Action_Controller_Type_Change_Status', $oAdminFormActionChangeStatus)
	);
}

$oDataset = new Admin_Form_Dataset_Entity(Core_Entity::factory('Uvlist_Item'));
$oDataset->addCondition(array('where' => array('uvlist_id', '=', $uvlist_id)));
$oAdmin_Form_Controller->addDataset($oDataset);

$oAdmin_Form_Controller->execute();
