# optimize — HostCMS 7 module

Server-side output optimizer for HostCMS 7:

- Strips HTML comments and collapses whitespace in page markup, while
  leaving `<script>`, `<style>`, `<pre>`, `<textarea>` and inline JSON
  completely untouched (see `modules/optimize/optimize.php`).
- Finds locally-referenced `<link rel="stylesheet">` / `<script src="...">`
  tags in the rendered page and combines consecutive local ones into a
  single cached, minified bundle — no template changes required
  (`modules/optimize/assets.php`).
- Per-site settings (enable/disable CSS/JS combining) and cumulative
  savings stats, stored as JSON with file locking
  (`modules/optimize/settings.php`).
- Admin settings screen with toggles and a savings counter
  (`admin/optimize/index.php`).

## Install

1. Copy `modules/optimize/` and `admin/optimize/` into your HostCMS
   installation root (same layout as in this repo).
2. Activate the module in **Модули** in the admin center. This patches
   every template of the current site, injecting:
   - `<?php if (Core::moduleIsActive('optimize')) Optimize::ob(); ?>`
     right after `<!DOCTYPE`
   - `<?php if (Core::moduleIsActive('optimize')) Optimize::clean(); ?>`
     right before `</html>`
3. Open the module's settings screen (menu item "Optimize" in the admin
   center) to toggle CSS/JS combining and see cumulative savings.

## Notes

- Generated bundles are cached under `upload/optimize_cache/` and keyed by
  a hash of each source file's path + mtime, so editing a source file
  automatically invalidates the cache.
- The admin screen's markup wasn't tested against a live HostCMS 7 admin
  skin — the settings logic is solid, but styling may need a small
  touch-up to match your theme.
- See `https://hostcms.ru/api7/` for the underlying HostCMS 7 API this
  module was refactored against.
