<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

class Optimize
{
	protected static $_protectedTags = array('script', 'style', 'pre', 'textarea');
	protected static $_vault = array();

	public static function html($str)
	{
		self::$_vault = array();

		$siteId = defined('CURRENT_SITE') ? CURRENT_SITE : 0;
		$settings = Optimize_Settings::get($siteId);

		if (!empty($settings['combine_css'])) {
			$str = Optimize_Assets::combineCss($str, $siteId, !empty($settings['minify_css']));
		}

		if (!empty($settings['combine_js'])) {
			$str = Optimize_Assets::combineJs($str, $siteId, !empty($settings['minify_js']));
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

			$token = chr(1) . 'OPT' . count(Optimize::$_vault) . chr(2);
			Optimize::$_vault[$token] = $whole;
			return $token;
		}, $str);
	}

	protected static function _restore($str)
	{
		return strtr($str, self::$_vault);
	}

	public static function ob()
	{
		ob_start(array(__CLASS__, 'html'));
	}

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
