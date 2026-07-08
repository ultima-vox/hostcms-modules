<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Optimize module.
 *
 * @package HostCMS 7\Optimize
 * @version 2.0
 * @date 2026-07-07
 * @see https://hostcms.ru/api7/
 */
class Optimize_Module extends Core_Module
{
	/**
	 * Module version
	 * @var string
	 */
	public $version = '2.0';

	/**
	 * Module date
	 * @var date
	 */
	public $date = '2026-07-07';

	/**
	 * Module name
	 * @var string
	 */
	protected $_moduleName = 'optimize';

	/**
	 * Marker inserted right after <!DOCTYPE ... in every patched template.
	 * @var string
	 */
	const START_MARKER = "<?php if (Core::moduleIsActive('optimize')) Optimize::ob(); ?>";

	/**
	 * Marker inserted right before </html> in every patched template.
	 * @var string
	 */
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
	}

	/**
	 * Insert compressor markers into every template of the current site.
	 *
	 * Idempotent: templates that already contain the marker are left
	 * untouched, so calling install() twice (or reactivating the
	 * module) never duplicates the injected code.
	 */
	protected function _insertCode()
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

			if (strpos($file, self::START_MARKER) !== FALSE) {
				continue; // already patched
			}

			$file = preg_replace(
				'/<!DOCTYPE/i',
				self::START_MARKER . ' <!DOCTYPE',
				$file,
				1
			);

			$file = preg_replace(
				'/<\/html\s*>/i',
				'</html> ' . self::END_MARKER,
				$file,
				1
			);

			file_put_contents($filename, $file);
		}
	}

	/**
	 * Remove compressor markers from every template of the current site.
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
			// also clean up markers inserted by module v1.0, in case of an upgrade
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
		$this->_insertCode();
	}

	/**
	 * Uninstall.
	 */
	public function uninstall()
	{
		$this->_deleteCode();
	}
}
