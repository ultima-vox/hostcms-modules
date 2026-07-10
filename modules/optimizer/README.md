# Optimizer — модуль оптимизации страниц для HostCMS 7

Модуль серверной постобработки HTML-страниц. Все преобразования выключены по умолчанию.

## Структура

```text
admin/optimizer/index.php
modules/optimizer/module.php
modules/optimizer/Optimizer.php
modules/optimizer/Optimizer_Context.php
modules/optimizer/Optimizer_Html.php
modules/optimizer/Optimizer_Settings.php
modules/optimizer/Optimizer_Assets.php
modules/optimizer/i18n/ru.php
```

## Установка

1. Скопируйте `modules/optimizer` в каталог `/modules/`.
2. Скопируйте `admin/optimizer` в фактический каталог центра администрирования.
3. Убедитесь, что PHP-процесс может записывать в `/upload/`.
4. В разделе «Модули» добавьте модуль с кодом `optimizer`.
5. Выполните установку и активируйте модуль.
6. Откройте пункт `Optimizer` в меню центра администрирования.

Метод `install()` создаёт каталог:

```text
/upload/optimizer_cache/
```

и начальный файл безопасных настроек.

## Обновление с page_optimizer

Старый модуль `page_optimizer` необходимо отключить и удалить из списка модулей. Затем удалить старые каталоги:

```text
/modules/page_optimizer/
/<admin-directory>/page_optimizer/
```

После этого установить новый модуль с кодом `optimizer`. Старые настройки из `/upload/page_optimizer_cache/` автоматически не переносятся.

## Удаление

Метод `uninstall()` удаляет `/upload/optimizer_cache/` вместе с настройками и сгенерированными файлами.
