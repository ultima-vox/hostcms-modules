<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Optimize module.
 *
 * @package HostCMS 7\Optimize
 * @version 2.3
 * @date 2026-07-08
 */
class Optimize_Module extends Core_Module
{
	/**
	 * Module version
	 * @var string
	 */
	public $version = '2.3';

	/**
	 * Module date
	 * @var date
	 */
	public $date = '2026-07-08';

	/**
	 * Module name
	 * @var string
	 */
	protected $_moduleName = 'optimize';

	/**
	 * Old template markers kept only for cleanup.
	 */
	const START_MARKER = "<?php if (Core::moduleIsActive('optimize')) Optimize::ob(); ?>";
	const END_MARKER = "<?php if (Core::moduleIsActive('optimize')) Optimize::clean(); ?>";

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->menu = array(
			array(
				'sorting' => 3,
				'block' => 1,
				'ico' => 'fa fa-magic',
				'name' => 'Optimize',
				'href' => '/admin/optimize/index.php',
				'onclick' => "$.adminLoad({path: '/admin/optimize/index.php'}); return false"
			)
		);

		$this->_autoStartBuffer();
	}

	/**
	 * Start output optimization automatically on frontend HTML requests.
	 */
	protected function _autoStartBuffer()
	{
		if (PHP_SAPI === 'cli') {
			return;
		}

		$requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
		$scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
		$accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';

		if (strpos($requestUri, '/admin/') === 0 || strpos($scriptName, '/admin/') !== FALSE) {
			return;
		}

		if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
			return;
		}

		if ($accept !== '' && stripos($accept, 'text/html') === FALSE && stripos($accept, '*/*') === FALSE) {
			return;
		}

		if (!class_exists('Optimize')) {
			return;
		}

		Optimize::ob();
	}

	/**
	 * Remove old compressor markers from every template of the current site.
	 */
	protected function _deleteCode()
	{
		$oTemplates = Core_Entity::factory('Template');

		$oTemplates->queryBuilder()
			->clear()
			->where('site_id', '=', CURRENT_SITE);

		foreach ($oTemplates->findAll() as $oTemplate) {
			$filename = CMS_FOLDER . "templates/template{$oTemplate->id}/template.htm";

			if (!is_file($filename) || !is_writable($filename)) {
				continue;
			}

			$file = file_get_contents($filename);
			$file = str_replace(self::START_MARKER . ' ', '', $file);
			$file = str_replace(' ' . self::END_MARKER, '', $file);
			$file = str_replace(self::START_MARKER, '', $file);
			$file = str_replace(self::END_MARKER, '', $file);
			$file = str_replace("<?php if(Core::moduleIsActive ('optimize')) Optimize::ob();?> ", '', $file);
			$file = str_replace(" <?php if(Core::moduleIsActive ('optimize')) Optimize::clean();?>", '', $file);

			file_put_contents($filename, $file);
		}
	}

	/**
	 * Install.
	 */
	public function install()
	{
		$this->_deleteCode();
	}

	/**
	 * Uninstall.
	 */
	public function uninstall()
	{
		$this->_deleteCode();
	}
}
