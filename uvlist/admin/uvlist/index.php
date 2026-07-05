<?php

require_once('../../bootstrap.php');

Core_Auth::authorization($sModule = 'uvlist');

$iAdmin_Form_Id = Uvlist_Module::ADMIN_FORM_ID;
$sAdminFormAction = '/admin/uvlist/index.php';

$oAdmin_Form = Core_Entity::factory('Admin_Form', $iAdmin_Form_Id);

$oAdmin_Form_Controller = Admin_Form_Controller::create($oAdmin_Form);
$oAdmin_Form_Controller
	->module(Core_Module::factory($sModule))
	->setUp()
	->path($sAdminFormAction)
	->title(Core::_('Uvlist.title'))
	->pageTitle(Core::_('Uvlist.title'));

$uvlist_dir_id = intval(Core_Array::getGet('uvlist_dir_id', 0));

$oAdmin_Form_Entity_Menus = Admin_Form_Entity::factory('Menus');
$oAdmin_Form_Entity_Menus
	->add(
		Admin_Form_Entity::factory('Menu')
			->name(Core::_('Uvlist.main_menu'))
			->icon('fa fa-list-ul')
			->add(
				Admin_Form_Entity::factory('Menu')
					->name(Core::_('Admin_Form.add'))
					->icon('fa fa-plus')
					->href($oAdmin_Form_Controller->getAdminActionLoadHref($oAdmin_Form_Controller->getPath(), 'edit', NULL, 1, 0))
					->onclick($oAdmin_Form_Controller->getAdminActionLoadAjax($oAdmin_Form_Controller->getPath(), 'edit', NULL, 1, 0))
			)
	)
	->add(
		Admin_Form_Entity::factory('Menu')
			->name(Core::_('Uvlist_Dir.menu'))
			->icon('fa fa-folder-open')
			->add(
				Admin_Form_Entity::factory('Menu')
					->name(Core::_('Admin_Form.add'))
					->icon('fa fa-plus')
					->href($oAdmin_Form_Controller->getAdminActionLoadHref($oAdmin_Form_Controller->getPath(), 'edit', NULL, 0, 0))
					->onclick($oAdmin_Form_Controller->getAdminActionLoadAjax($oAdmin_Form_Controller->getPath(), 'edit', NULL, 0, 0))
			)
	);

$oAdmin_Form_Controller->addEntity($oAdmin_Form_Entity_Menus);

$oAdmin_Form_Entity_Breadcrumbs = Admin_Form_Entity::factory('Breadcrumbs');
$oAdmin_Form_Entity_Breadcrumbs->add(
	Admin_Form_Entity::factory('Breadcrumb')
		->name(Core::_('Uvlist.title'))
		->href($oAdmin_Form_Controller->getAdminLoadHref($oAdmin_Form_Controller->getPath(), NULL, NULL, ''))
		->onclick($oAdmin_Form_Controller->getAdminLoadAjax($oAdmin_Form_Controller->getPath(), NULL, NULL, ''))
);

if ($uvlist_dir_id)
{
	$oDir = Core_Entity::factory('Uvlist_Dir')->find($uvlist_dir_id);

	if (!is_null($oDir->id))
	{
		$aBreadcrumbs = array();

		do {
			$additionalParams = 'uvlist_dir_id=' . intval($oDir->id);

			$aBreadcrumbs[] = Admin_Form_Entity::factory('Breadcrumb')
				->name($oDir->name)
				->href($oAdmin_Form_Controller->getAdminLoadHref($oAdmin_Form_Controller->getPath(), NULL, NULL, $additionalParams))
				->onclick($oAdmin_Form_Controller->getAdminLoadAjax($oAdmin_Form_Controller->getPath(), NULL, NULL, $additionalParams));
		} while ($oDir = $oDir->getParent());

		foreach (array_reverse($aBreadcrumbs) as $oBreadcrumb)
		{
			$oAdmin_Form_Entity_Breadcrumbs->add($oBreadcrumb);
		}
	}
}

$oAdmin_Form_Controller->addEntity($oAdmin_Form_Entity_Breadcrumbs);

$oAdmin_Form_Action = Core_Entity::factory('Admin_Form', $iAdmin_Form_Id)->Admin_Form_Actions->getByName('edit');
if ($oAdmin_Form_Action && $oAdmin_Form_Controller->getAction() == 'edit')
{
	$oControllerEdit = Admin_Form_Action_Controller::factory('Uvlist_Controller_Edit', $oAdmin_Form_Action);
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

$oDatasetDirs = new Admin_Form_Dataset_Entity(Core_Entity::factory('Uvlist_Dir'));
$oDatasetDirs
	->changeField('name', 'class', 'semi-bold')
	->addCondition(array('where' => array('parent_id', '=', $uvlist_dir_id)))
	->addCondition(array('where' => array('site_id', '=', CURRENT_SITE)));
$oAdmin_Form_Controller->addDataset($oDatasetDirs);

$oDatasetLists = new Admin_Form_Dataset_Entity(Core_Entity::factory('Uvlist'));
$oDatasetLists
	->addCondition(array('where' => array('uvlist_dir_id', '=', $uvlist_dir_id)))
	->addCondition(array('where' => array('site_id', '=', CURRENT_SITE)))
	->changeField('name', 'type', 1);
$oAdmin_Form_Controller->addDataset($oDatasetLists);

$oAdmin_Form_Controller->execute();
