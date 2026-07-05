# UVList module specification

## Goal

`uvlist` is a HostCMS 7.x additional module for reusable named lists. It reproduces the functional idea of the old HostCMS List module with a clean HostCMS 7-compatible implementation.

## Scope v0.1

- list directories;
- lists inside directories;
- list items inside lists;
- nested list items;
- active flag;
- sorting field;
- standard HostCMS admin forms;
- install-time DB schema creation;
- install-time Admin_Form creation;
- ORM access through `Core_Entity::factory()`.

## Module name

- module directory: `uvlist`
- module class: `Uvlist_Module`
- list model: `Uvlist_Model`
- directory model: `Uvlist_Dir_Model`
- item model: `Uvlist_Item_Model`

The prefix is intentionally short and neutral for the module itself. Shared framework abstractions are out of scope until the first module works.

## Tables

### `uvlist_dirs`

Directory tree for lists.

Required columns:

- `id`
- `parent_id`
- `site_id`
- `user_id`
- `name`
- `description`
- `sorting`
- `deleted`

### `uvlists`

Named list containers.

Required columns:

- `id`
- `uvlist_dir_id`
- `site_id`
- `user_id`
- `name`
- `code`
- `description`
- `sorting`
- `active`
- `deleted`

### `uvlist_items`

Nested list values.

Required columns:

- `id`
- `uvlist_id`
- `parent_id`
- `user_id`
- `value`
- `code`
- `description`
- `sorting`
- `active`
- `deleted`

## Admin forms

Fixed high IDs are used to avoid collision with stock forms:

- `990020` — lists and list directories;
- `990021` — list items.

## Admin URLs

- `/admin/uvlist/index.php`
- `/admin/uvlist/item/index.php`

## ORM relationships

`Uvlist_Model`:

- belongs to `Uvlist_Dir`;
- belongs to `Site`;
- belongs to `User`;
- has many `Uvlist_Item`.

`Uvlist_Dir_Model`:

- belongs to `Site`;
- belongs to `User`;
- belongs to parent `Uvlist_Dir` through `parent_id`;
- has many child `Uvlist_Dir`;
- has many `Uvlist`.

`Uvlist_Item_Model`:

- belongs to `Uvlist`;
- belongs to parent `Uvlist_Item` through `parent_id`;
- belongs to `User`;
- has many child `Uvlist_Item`.

## Non-goals v0.1

- ModuleKit/internal framework;
- import/export;
- JSON public API;
- drag and drop;
- cross-site shared lists.

These are planned only after a stable native module exists.
