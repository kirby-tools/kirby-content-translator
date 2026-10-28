# Kirby Tools Utils

Shared utility classes for [Kirby Tools plugins](https://kirby.tools). This package provides common model and field resolution logic used across multiple plugins to avoid ambiguous class resolution when multiple plugins are installed together.

## Requirements

- Kirby 6
- PHP 8.3+

Kirby 4 and 5 plugins stay on `^0.1`.

## Installation

```bash
composer require johannschopplich/kirby-tools-utils
```

All classes live in the `KirbyTools\Utils` namespace:

```php
use KirbyTools\Utils\FieldResolver;
```

## Classes

### `ModelResolver`

Resolves Kirby CMS models from different identifier formats:

- **`resolveFromId`**: Resolves a model from a model ID (e.g., `site`, `page-id`, `file-id`)
- **`resolveFromPath`**: Resolves a model from a Panel view path (e.g., `site`, `pages/xxx`, `pages/xxx/files/yyy`)

### `FieldResolver`

Resolves blueprint fields from Kirby models:

- **`resolveModelFields`**: Creates a Kirby Form from the model's blueprint fields and returns the exported field properties (excluding `title`, `slug`, and field values), keyed by the lowercased field name. Custom blueprint keys such as `copilot` are not exported; read them from `$model->blueprint()->fields()`, whose keys keep the field name's casing

### `QueryResolver`

Resolves Kirby queries written into blueprint values:

- **`resolve`**: Replaces every `{{ ... }}` placeholder in a string with its query result against the model, and returns any non-string value untouched

### `FieldNormalizer`

Normalizes blueprint field definitions to their base types:

- **`resolveBaseType`**: Resolves a custom field type to its base type: the type of the nearest core field class among its registered class and that class's parents. Core types, `markdown`, and types Kirby can't resolve are returned as is
- **`normalizeFields`**: Normalizes a fields array, recursing into nested fields and `fieldsets[*].tabs[*].fields`

## Used By

- [kirby-copilot](https://kirby.tools/copilot)
- [kirby-content-translator](https://kirby.tools/content-translator)
- [kirby-seo-audit](https://kirby.tools/seo-audit)
- [kirby-serp-preview](https://kirby.tools/serp-preview)

## License

[AGPL](./LICENSE) License © 2026-PRESENT [Johann Schopplich](https://github.com/johannschopplich)
