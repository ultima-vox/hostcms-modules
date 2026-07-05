CREATE TABLE IF NOT EXISTS `uvlist_dirs` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`parent_id` int(11) unsigned NOT NULL DEFAULT '0',
	`site_id` int(11) unsigned NOT NULL DEFAULT '0',
	`user_id` int(11) unsigned NOT NULL DEFAULT '0',
	`name` varchar(255) NOT NULL DEFAULT '',
	`description` text NULL,
	`sorting` int(11) NOT NULL DEFAULT '0',
	`deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (`id`),
	KEY `parent_id` (`parent_id`),
	KEY `site_id` (`site_id`),
	KEY `deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `uvlists` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`uvlist_dir_id` int(11) unsigned NOT NULL DEFAULT '0',
	`site_id` int(11) unsigned NOT NULL DEFAULT '0',
	`user_id` int(11) unsigned NOT NULL DEFAULT '0',
	`name` varchar(255) NOT NULL DEFAULT '',
	`code` varchar(255) NOT NULL DEFAULT '',
	`description` text NULL,
	`sorting` int(11) NOT NULL DEFAULT '0',
	`active` tinyint(1) unsigned NOT NULL DEFAULT '1',
	`deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (`id`),
	KEY `uvlist_dir_id` (`uvlist_dir_id`),
	KEY `site_id` (`site_id`),
	KEY `active` (`active`),
	KEY `deleted` (`deleted`),
	UNIQUE KEY `site_code_deleted` (`site_id`, `code`, `deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `uvlist_items` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`uvlist_id` int(11) unsigned NOT NULL DEFAULT '0',
	`parent_id` int(11) unsigned NOT NULL DEFAULT '0',
	`user_id` int(11) unsigned NOT NULL DEFAULT '0',
	`value` varchar(255) NOT NULL DEFAULT '',
	`code` varchar(255) NOT NULL DEFAULT '',
	`description` text NULL,
	`sorting` int(11) NOT NULL DEFAULT '0',
	`active` tinyint(1) unsigned NOT NULL DEFAULT '1',
	`deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (`id`),
	KEY `uvlist_id` (`uvlist_id`),
	KEY `parent_id` (`parent_id`),
	KEY `active` (`active`),
	KEY `deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

REPLACE INTO `admin_forms` (`id`, `admin_word_id`, `on_page`, `key_field`, `show_operations`, `show_group_operations`, `default_order_field`, `default_order_direction`, `user_id`, `guid`, `deleted`) VALUES
(990020, 0, 20, 'id', 1, 1, 'sorting', 1, 0, 'UVLIST-FORM-LISTS-0001', 0),
(990021, 0, 20, 'id', 1, 1, 'sorting', 1, 0, 'UVLIST-FORM-ITEMS-0001', 0);

REPLACE INTO `admin_form_actions` (`id`, `admin_word_id`, `admin_form_id`, `name`, `picture`, `icon`, `color`, `single`, `group`, `sorting`, `dataset`, `confirm`, `user_id`, `deleted`) VALUES
(9900201, 0, 990020, 'edit', '/admin/images/edit.gif', 'fa fa-pencil', 'palegreen', 1, 0, 10, -1, 0, 0, 0),
(9900202, 0, 990020, 'apply', '/admin/images/apply.gif', 'fa fa-check', 'palegreen', 0, 1, 20, -1, 0, 0, 0),
(9900203, 0, 990020, 'copy', '/admin/images/copy.gif', 'fa fa-copy', 'info', 1, 1, 30, -1, 1, 0, 0),
(9900204, 0, 990020, 'markDeleted', '/admin/images/delete.gif', 'fa fa-trash-o', 'darkorange', 1, 1, 40, -1, 1, 0, 0),
(9900205, 0, 990020, 'changeStatus', '', NULL, NULL, 0, 0, 50, 1, 0, 0, 0),
(9900211, 0, 990021, 'edit', '/admin/images/edit.gif', 'fa fa-pencil', 'palegreen', 1, 0, 10, -1, 0, 0, 0),
(9900212, 0, 990021, 'apply', '/admin/images/apply.gif', 'fa fa-check', 'palegreen', 0, 1, 20, -1, 0, 0, 0),
(9900213, 0, 990021, 'copy', '/admin/images/copy.gif', 'fa fa-copy', 'info', 1, 1, 30, -1, 1, 0, 0),
(9900214, 0, 990021, 'markDeleted', '/admin/images/delete.gif', 'fa fa-trash-o', 'darkorange', 1, 1, 40, -1, 1, 0, 0),
(9900215, 0, 990021, 'changeStatus', '', NULL, NULL, 0, 0, 50, -1, 0, 0, 0);

REPLACE INTO `admin_form_fields` (`id`, `admin_form_id`, `admin_word_id`, `name`, `sorting`, `ico`, `type`, `format`, `allow_sorting`, `allow_filter`, `editable`, `filter_type`, `class`, `width`, `image`, `link`, `onclick`, `list`, `user_id`, `deleted`) VALUES
(99002001, 990020, 0, 'img', 10, 'fa fa-bars', 7, '', 0, 0, 0, 0, 'text-center', '25px', '0=/admin/images/folder.gif==fa fa-folder-open-o\r\n1=/admin/images/list.gif==fa fa-list-ul', '', '', '', 0, 0),
(99002002, 990020, 0, 'name', 20, '', 1, '', 1, 1, 1, 0, '', '', '', '', '', '', 0, 0),
(99002003, 990020, 0, 'code', 30, '', 1, '', 1, 1, 1, 0, '', '180px', '', '', '', '', 0, 0),
(99002004, 990020, 0, 'items', 40, 'fa fa-list', 7, '', 0, 0, 0, 0, 'text-center', '80px', '', '/admin/uvlist/item/index.php?uvlist_id={id}', '$.adminLoad({path: \'/admin/uvlist/item/index.php\', additionalParams: \'uvlist_id={id}\'}); return false', '', 0, 0),
(99002005, 990020, 0, 'sorting', 50, '', 1, '', 1, 1, 1, 0, 'text-center', '90px', '', '', '', '', 0, 0),
(99002006, 990020, 0, 'active', 60, '', 7, '', 1, 1, 1, 1, 'text-center', '80px', '0=/admin/images/not_check.gif==fa fa-ban\r\n1=/admin/images/check.gif==fa fa-check', '', '', '', 0, 0),
(99002007, 990020, 0, 'id', 70, '', 1, '', 1, 1, 0, 0, 'text-center', '70px', '', '', '', '', 0, 0),
(99002101, 990021, 0, 'img', 10, 'fa fa-bars', 7, '', 0, 0, 0, 0, 'text-center', '25px', '1=/admin/images/page.gif==fa fa-file-text-o', '', '', '', 0, 0),
(99002102, 990021, 0, 'value', 20, '', 1, '', 1, 1, 1, 0, '', '', '', '', '', '', 0, 0),
(99002103, 990021, 0, 'code', 30, '', 1, '', 1, 1, 1, 0, '', '180px', '', '', '', '', 0, 0),
(99002104, 990021, 0, 'sorting', 40, '', 1, '', 1, 1, 1, 0, 'text-center', '90px', '', '', '', '', 0, 0),
(99002105, 990021, 0, 'active', 50, '', 7, '', 1, 1, 1, 1, 'text-center', '80px', '0=/admin/images/not_check.gif==fa fa-ban\r\n1=/admin/images/check.gif==fa fa-check', '', '', '', 0, 0),
(99002106, 990021, 0, 'id', 60, '', 1, '', 1, 1, 0, 0, 'text-center', '70px', '', '', '', '', 0, 0);
