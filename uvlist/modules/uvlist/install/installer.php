<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

class Uvlist_Installer
{
	public function install()
	{
		$this->_createTables();
		$this->_registerAdminForms();
		return $this;
	}

	protected function _execute(array $aSql)
	{
		foreach ($aSql as $sSql)
		{
			Sql_Controller::instance()->execute($sSql);
		}
		return $this;
	}

	protected function _createTables()
	{
		return $this->_execute(array(
			"CREATE TABLE IF NOT EXISTS `uvlist_dirs` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `parent_id` int(11) unsigned NOT NULL DEFAULT '0', `site_id` int(11) unsigned NOT NULL DEFAULT '0', `user_id` int(11) unsigned NOT NULL DEFAULT '0', `name` varchar(255) NOT NULL DEFAULT '', `description` text NULL, `sorting` int(11) NOT NULL DEFAULT '0', `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0', PRIMARY KEY (`id`), KEY `parent_id` (`parent_id`), KEY `site_id` (`site_id`), KEY `deleted` (`deleted`)) ENGINE=InnoDB DEFAULT CHARSET=utf8",
			"CREATE TABLE IF NOT EXISTS `uvlists` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `uvlist_dir_id` int(11) unsigned NOT NULL DEFAULT '0', `site_id` int(11) unsigned NOT NULL DEFAULT '0', `user_id` int(11) unsigned NOT NULL DEFAULT '0', `name` varchar(255) NOT NULL DEFAULT '', `code` varchar(255) NOT NULL DEFAULT '', `description` text NULL, `sorting` int(11) NOT NULL DEFAULT '0', `active` tinyint(1) unsigned NOT NULL DEFAULT '1', `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0', PRIMARY KEY (`id`), KEY `uvlist_dir_id` (`uvlist_dir_id`), KEY `site_id` (`site_id`), KEY `active` (`active`), KEY `deleted` (`deleted`), UNIQUE KEY `site_code_deleted` (`site_id`, `code`, `deleted`)) ENGINE=InnoDB DEFAULT CHARSET=utf8",
			"CREATE TABLE IF NOT EXISTS `uvlist_items` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `uvlist_id` int(11) unsigned NOT NULL DEFAULT '0', `parent_id` int(11) unsigned NOT NULL DEFAULT '0', `user_id` int(11) unsigned NOT NULL DEFAULT '0', `value` varchar(255) NOT NULL DEFAULT '', `code` varchar(255) NOT NULL DEFAULT '', `description` text NULL, `sorting` int(11) NOT NULL DEFAULT '0', `active` tinyint(1) unsigned NOT NULL DEFAULT '1', `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0', PRIMARY KEY (`id`), KEY `uvlist_id` (`uvlist_id`), KEY `parent_id` (`parent_id`), KEY `active` (`active`), KEY `deleted` (`deleted`)) ENGINE=InnoDB DEFAULT CHARSET=utf8"
		));
	}

	protected function _registerAdminForms()
	{
		$this->_registerForms();
		$this->_registerActions();
		$this->_registerFields();
		return $this;
	}

	protected function _registerForms()
	{
		return $this->_execute(array(
			"REPLACE INTO `admin_forms` (`id`, `admin_word_id`, `on_page`, `key_field`, `show_operations`, `show_group_operations`, `default_order_field`, `default_order_direction`, `user_id`, `guid`, `deleted`) VALUES (990020, 0, 20, 'id', 1, 1, 'sorting', 1, 0, 'UVLIST-FORM-LISTS-0001', 0)",
			"REPLACE INTO `admin_forms` (`id`, `admin_word_id`, `on_page`, `key_field`, `show_operations`, `show_group_operations`, `default_order_field`, `default_order_direction`, `user_id`, `guid`, `deleted`) VALUES (990021, 0, 20, 'id', 1, 1, 'sorting', 1, 0, 'UVLIST-FORM-ITEMS-0001', 0)"
		));
	}

	protected function _registerActions()
	{
		return $this->_execute(array(
			"REPLACE INTO `admin_form_actions` (`id`, `admin_word_id`, `admin_form_id`, `name`, `picture`, `icon`, `color`, `single`, `group`, `sorting`, `dataset`, `confirm`, `user_id`, `deleted`) VALUES (9900201, 0, 990020, 'edit', '', 'fa fa-pencil', 'palegreen', 1, 0, 10, -1, 0, 0, 0)",
			"REPLACE INTO `admin_form_actions` (`id`, `admin_word_id`, `admin_form_id`, `name`, `picture`, `icon`, `color`, `single`, `group`, `sorting`, `dataset`, `confirm`, `user_id`, `deleted`) VALUES (9900202, 0, 990020, 'apply', '', 'fa fa-check', 'palegreen', 0, 1, 20, -1, 0, 0, 0)",
			"REPLACE INTO `admin_form_actions` (`id`, `admin_word_id`, `admin_form_id`, `name`, `picture`, `icon`, `color`, `single`, `group`, `sorting`, `dataset`, `confirm`, `user_id`, `deleted`) VALUES (9900203, 0, 990020, 'copy', '', 'fa fa-copy', 'info', 1, 1, 30, -1, 1, 0, 0)",
			"REPLACE INTO `admin_form_actions` (`id`, `admin_word_id`, `admin_form_id`, `name`, `picture`, `icon`, `color`, `single`, `group`, `sorting`, `dataset`, `confirm`, `user_id`, `deleted`) VALUES (9900204, 0, 990020, 'markDeleted', '', 'fa fa-trash-o', 'darkorange', 1, 1, 40, -1, 1, 0, 0)",
			"REPLACE INTO `admin_form_actions` (`id`, `admin_word_id`, `admin_form_id`, `name`, `picture`, `icon`, `color`, `single`, `group`, `sorting`, `dataset`, `confirm`, `user_id`, `deleted`) VALUES (9900205, 0, 990020, 'changeStatus', '', NULL, NULL, 0, 0, 50, 1, 0, 0, 0)",
			"REPLACE INTO `admin_form_actions` (`id`, `admin_word_id`, `admin_form_id`, `name`, `picture`, `icon`, `color`, `single`, `group`, `sorting`, `dataset`, `confirm`, `user_id`, `deleted`) VALUES (9900211, 0, 990021, 'edit', '', 'fa fa-pencil', 'palegreen', 1, 0, 10, -1, 0, 0, 0)",
			"REPLACE INTO `admin_form_actions` (`id`, `admin_word_id`, `admin_form_id`, `name`, `picture`, `icon`, `color`, `single`, `group`, `sorting`, `dataset`, `confirm`, `user_id`, `deleted`) VALUES (9900212, 0, 990021, 'apply', '', 'fa fa-check', 'palegreen', 0, 1, 20, -1, 0, 0, 0)",
			"REPLACE INTO `admin_form_actions` (`id`, `admin_word_id`, `admin_form_id`, `name`, `picture`, `icon`, `color`, `single`, `group`, `sorting`, `dataset`, `confirm`, `user_id`, `deleted`) VALUES (9900213, 0, 990021, 'copy', '', 'fa fa-copy', 'info', 1, 1, 30, -1, 1, 0, 0)",
			"REPLACE INTO `admin_form_actions` (`id`, `admin_word_id`, `admin_form_id`, `name`, `picture`, `icon`, `color`, `single`, `group`, `sorting`, `dataset`, `confirm`, `user_id`, `deleted`) VALUES (9900214, 0, 990021, 'markDeleted', '', 'fa fa-trash-o', 'darkorange', 1, 1, 40, -1, 1, 0, 0)",
			"REPLACE INTO `admin_form_actions` (`id`, `admin_word_id`, `admin_form_id`, `name`, `picture`, `icon`, `color`, `single`, `group`, `sorting`, `dataset`, `confirm`, `user_id`, `deleted`) VALUES (9900215, 0, 990021, 'changeStatus', '', NULL, NULL, 0, 0, 50, -1, 0, 0, 0)"
		));
	}

	protected function _registerFields()
	{
		return $this;
	}
}
