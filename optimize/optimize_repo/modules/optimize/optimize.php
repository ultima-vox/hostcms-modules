<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Optimize.
 *
 * Output buffer compressor: strips HTML comments and collapses
 * repeated whitespace in the outgoing page markup.
 *
 * Refactored for HostCMS 7 (see https://hostcms.ru/api7/).
 *
 * Unlike the original implementation, this version never touches the
 * *content* of <script>, <style>, <pre>, <textarea> or <![CDATA[ ]]>
 * blocks, and never runs a single greedy /\/\*.*\*\// pass over the
 * whole document. Those are the two changes that matter most: a
 * regex has no idea where a JS string, a regex literal or a JSON
 * value ends, so any regex that "removes comments" or "collapses
 * whitespace" across the entire page can just as easily eat a piece
 * of inline JSON, a `for (;;)` loop or a URL that happens to contain
 * `//`. By carving those blocks out first and gluing them back
 * untouched afterwards, the compressor only ever rewrites plain HTML
 * markup and text, which is safe to squeeze.
 *
 * @package HostCMS 7\Optimize
 * @version 2.0
 * @copyright HostCMS module
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
	 * Compress an HTML page for output.
	 *
	 * Registered as the ob_start() callback, so it receives the whole
	 * buffered page in one call.
	 *
	 * @param string $str raw page markup
	 * @return string compressed markup
	 */
	public static function html($str)
	{
		self::$_vault = array();

		$siteId = defined('CURRENT_SITE') ? CURRENT_SITE : 0;
		$settings = Optimize_Settings::get($siteId);

		// 0. Combine local CSS/JS first, while <link>/<script src> tags
		// are still plain tags in the raw markup. This has to happen
		// before the extraction step below, which would otherwise
		// swallow whole <script>...</script> elements (including their
		// attributes) into the vault and hide them from the combiner.
		if (!empty($settings['combine_css'])) {
			$str = Optimize_Assets::combineCss($str, $siteId);
		}

		if (!empty($settings['combine_js'])) {
			$str = Optimize_Assets::combineJs($str, $siteId);
		}

		// 1. Pull out everything that must not be rewritten:
		// <script>...</script>, <style>...</style>, <pre>...</pre>,
		// <textarea>...</textarea>, <![CDATA[ ]]> and conditional
		// comments (<!--[if ...]> ... <![endif]-->). Ordinary HTML
		// comments are dropped right here instead of being protected,
		// since removing them is safe and intentional.
		$str = self::_extract($str);

		// 2. Collapse runs of whitespace. Only plain markup/text is
		// left in $str at this point, so this can never merge two
		// tokens that were separated by a real newline inside a
		// <script>/<style>/<pre> block.
		$str = preg_replace('/[ \t\r\n]+/', ' ', $str);
		$str = trim($str);

		// 3. Put the protected chunks back exactly as they were.
		$str = self::_restore($str);

		return $str;
	}

	/**
	 * Replace protected regions with single-line placeholders and
	 * store their original content in the vault.
	 *
	 * @param string $str
	 * @return string
	 */
	protected static function _extract($str)
	{
		$tagsPattern = implode('|', self::$_protectedTags);

		$pattern = '#'
			. '<!--\[if[^>]*+>.*?<!\[endif\]-->'  // IE conditional comments
			. '|<!\[CDATA\[.*?\]\]>'               // CDATA sections
			. '|<(' . $tagsPattern . ')\b[^>]*+>.*?</\1\s*>' // protected tags with content
			. '|<!--.*?-->'                          // plain comments -> dropped
			. '#siu';

		return preg_replace_callback($pattern, function ($m) {

			$whole = $m[0];

			// Plain HTML comment (not conditional, not CDATA, no tag
			// capture group) -> just delete it.
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
	 * Put the vaulted chunks back into the compressed string.
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
	 *
	 * Guarded so a mismatched call (e.g. the module was deactivated
	 * mid-request, or the template markers ended up out of order)
	 * cannot throw a "failed to delete buffer" notice.
	 */
	public static function clean()
	{
		if (ob_get_level() > 0) {
			ob_end_flush();
		}
	}

	public static function show()
	{
		echo "It works!";
	}
}
