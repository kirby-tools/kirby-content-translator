# Kirby Tools Utils

Shared utility classes for [Kirby Tools plugins](https://kirby.tools). This package provides common model and field resolution logic used across multiple plugins to avoid ambiguous class resolution when multiple plugins are installed together.

## Classes

### `ModelResolver`

Resolves Kirby CMS models from different identifier formats:

- **`resolveFromId`**: Resolves a model from a model ID (e.g., `site`, `page-id`, `file-id`)
- **`resolveFromPath`**: Resolves a model from a Panel view path (e.g., `site`, `pages/xxx`, `pages/xxx/files/yyy`)

### `FieldResolver`

Resolves blueprint fields from Kirby models:

- **`resolveModelFields`**: Creates a Kirby Form from the model's blueprint fields and returns field properties (excluding `title`, `slug`, and field values)

## Used By

- [kirby-content-translator](https://kirby.tools/content-translator)
- [kirby-copilot](https://kirby.tools/copilot)
- [kirby-minimap](https://kirby.tools/minimap)

## License

[AGPL](./LICENSE) License © 2026-PRESENT [Johann Schopplich](https://github.com/johannschopplich)
