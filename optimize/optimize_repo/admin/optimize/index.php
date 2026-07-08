<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Settings screen for the "optimize" module.
 *
 * Loaded into the admin content area via the $.adminLoad menu entry
 * registered in module.php, so this outputs a plain HTML fragment (no
 * <html>/<head>/<body>) — the surrounding admin chrome is already there.
 *
 * NOTE: this couldn't be tested against a live HostCMS 7 admin center, so
 * while the settings logic (Core_Array::getPost, Optimize_Settings) is
 * solid, the markup/styling may need a small touch-up to match your
 * admin skin exactly.
 */

$siteId = defined('CURRENT_SITE') ? CURRENT_SITE : 0;

if (Core_Array::getPost('optimize_save')) {
	Optimize_Settings::saveToggles(
		Core_Array::getPost('combine_css') ? TRUE : FALSE,
		Core_Array::getPost('combine_js') ? TRUE : FALSE,
		$siteId
	);
}

$settings = Optimize_Settings::get($siteId);
$stats = $settings['stats'];

$cssSaved = max(0, $stats['css_original_bytes'] - $stats['css_minified_bytes']);
$jsSaved = max(0, $stats['js_original_bytes'] - $stats['js_minified_bytes']);
$totalSaved = $cssSaved + $jsSaved;
$totalRequestsSaved = $stats['css_requests_saved'] + $stats['js_requests_saved'];

?>
<div class="optimize-admin" style="max-width: 560px;">
	<h3>Optimize</h3>

	<form method="post" action="">
		<p>
			<label>
				<input type="checkbox" name="combine_css" value="1" <?php echo $settings['combine_css'] ? 'checked' : ''; ?>>
				Объединять и минифицировать локальные CSS-файлы
			</label>
		</p>
		<p>
			<label>
				<input type="checkbox" name="combine_js" value="1" <?php echo $settings['combine_js'] ? 'checked' : ''; ?>>
				Объединять локальные JS-файлы (консервативная минификация)
			</label>
		</p>
		<p>
			<button type="submit" name="optimize_save" value="1" class="btn btn-primary">Сохранить</button>
		</p>
	</form>

	<hr>

	<h4>Статистика</h4>
	<table class="table" style="max-width: 460px;">
		<tr>
			<td>Сэкономлено места (всего)</td>
			<td><strong><?php echo htmlspecialchars(Optimize_Settings::formatBytes($totalSaved), ENT_QUOTES); ?></strong></td>
		</tr>
		<tr>
			<td>&nbsp;&nbsp;— из них CSS</td>
			<td><?php echo htmlspecialchars(Optimize_Settings::formatBytes($cssSaved), ENT_QUOTES); ?></td>
		</tr>
		<tr>
			<td>&nbsp;&nbsp;— из них JS</td>
			<td><?php echo htmlspecialchars(Optimize_Settings::formatBytes($jsSaved), ENT_QUOTES); ?></td>
		</tr>
		<tr>
			<td>HTTP-запросов убрано за счёт объединения</td>
			<td><strong><?php echo (int) $totalRequestsSaved; ?></strong></td>
		</tr>
	</table>

	<p style="color: #777; font-size: 0.9em;">
		Счётчик растёт только при реальной пересборке бандла (когда исходные
		файлы поменялись и кэш обновляется), а не при каждом показе страницы —
		так что это накопительная экономия за всё время, а не нагрузка трафика.
	</p>
</div>
