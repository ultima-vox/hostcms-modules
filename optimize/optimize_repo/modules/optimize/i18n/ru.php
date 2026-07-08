<?php

return array(
    'model_name' => 'HTML-оптимизатор',
    'menu_name' => 'HTML-оптимизатор',
    'settings_tab' => 'Настройки',

    'section_html' => 'HTML',
    'section_css' => 'CSS',
    'section_js' => 'JavaScript',
    'section_fonts' => 'Шрифты',
    'section_images' => 'Изображения',
    'section_hints' => 'Сетевые подсказки',

    'minify_html' => 'Минифицировать HTML',
    'combine_css' => 'Объединять локальные CSS-файлы',
    'minify_css' => 'Минифицировать CSS-бандл',
    'critical_css_enabled' => 'Выводить Critical CSS',
    'critical_css' => 'Critical CSS',
    'critical_css_hint' => 'Вставляйте только CSS-код, без тега style.',

    'combine_js' => 'Объединять локальные JS-файлы',
    'minify_js' => 'Минифицировать JS-бандл',

    'preload_fonts_enabled' => 'Preload шрифтов',
    'preload_fonts' => 'URL шрифтов для preload',
    'preload_fonts_hint' => 'Один URL на строку. Теги link rel=preload будут сгенерированы автоматически.',

    'lazy_load_images' => 'Lazy Load изображений',
    'lazy_load_exclude' => 'Исключения Lazy Load',
    'lazy_load_exclude_hint' => 'Одна строка — один фрагмент тега img: class, id, src или data-атрибут.',
    'rewrite_webp' => 'WebP rewrite',
    'rewrite_avif' => 'AVIF rewrite',

    'dns_prefetch_enabled' => 'DNS Prefetch',
    'dns_prefetch' => 'Домены для DNS Prefetch',
    'preconnect_enabled' => 'Preconnect',
    'preconnect' => 'Домены для Preconnect',
    'resource_hint_hint' => 'Один домен на строку, без HTML-тегов.',

    'stats_total' => 'Всего',
    'stats_css' => 'CSS',
    'stats_js' => 'JS',
    'stats_requests' => 'Запросов убрано',
    'stats_note' => 'Статистика обновляется только при реальной пересборке CSS/JS-бандла.',
    'invalid_setting' => 'Недопустимый параметр'
);
