<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Optimize.
 *
 * Output buffer optimizer: combines local CSS/JS assets and safely minifies
 * plain HTML without touching protected blocks.
 *
 * @package HostCMS 7\Optimize
 * @version 2.2
 */
class Optimize
{
	/**
	 * Tags whose content must be preserved byte-for-byte.
	 * @var array<int, string>
	 */
	protected static $_protectedTags = array('script', 'style', 'pre', 'textarea');

	/**
	 * Extracted chunks, keyed by placeholder token.
	 * @var array<string, string>
	 */
	protected static $_vault = array();

	/**
	 * Optimize an HTML page for output.
	 *
	 * @param string $str raw page markup
	 * @return string optimized markup
	 */
	public static function html($str)
	{
		self::$_vault = array();

		$siteId = defined('CURRENT_SITE') ? CURRENT_SITE : 0;
		$settings = Optimize_Settings::get($siteId);

		if (!empty($settings['combine_css'])) {
			$str = Optimize_Assets::combineCss($str, $siteId);
		}

		if (!empty($settings['combine_js'])) {
			$str = Optimize_Assets::combineJs($str, $siteId);
		}

		if (empty($settings['minify_html'])) {
			return $str;
		}

		$str = self::_extract($str);
		$str = preg_replace('/[ \t\r\n]+/', ' ', $str);
		$str = trim($str);
		$str = self::_restore($str);

		return $str;
	}

	/**
	 * Replace protected regions with placeholders and store originals.
	 *
	 * @param string $str
	 * @return string
	 */
	protected static function _extract($str)
	{
		$tagsPattern = implode('|', self::$_protectedTags);

		$pattern = '#'
			. '<!--\[if[^>]*+>.*?<!\[endif\]-->'
			. '|<!\[CDATA\[.*?\]\]>'
			. '|<(' . $tagsPattern . ')\b[^>]*+>.*?</\1\s*>'
			. '|<!--.*?-->'
			. '#siu';

		return preg_replace_callback($pattern, function ($m) {

			$whole = $m[0];
			$isPlainComment = substr($whole, 0, 4) === '<!--'
				&& substr($whole, 4, 4) !== '[if ' && substr($whole, 4, 3) !== '[if';

			if ($isPlainComment && empty($m[1])) {
				return '';
			}

			$token = "\x01OPT" . count(Optimize::$_vault) . "\x02";
			Optimize::$_vault[$token] = $whole;
			return $token;

		}, $str);
	}

	/**
	 * Put the protected chunks back into the compressed string.
	 *
	 * @param string $str
	 * @return string
	 */
	protected static function _restore($str)
	{
		return strtr($str, self::$_vault);
	}

	/**
	 * Start buffering the current page through self::html().
	 */
	public static function ob()
	{
		ob_start(array(__CLASS__, 'html'));
	}

	/**
	 * Flush the buffer started by self::ob().
	 */
	public static function clean()
	{
		if (ob_get_level() > 0) {
			ob_end_flush();
		}
	}

	public static function show()
	{
		echo 'It works!';
	}
}
