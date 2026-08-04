# Kirby Content Translator

Commercial Kirby CMS plugin for translating content between languages using DeepL or custom AI providers.

## Commands

- `composer test` – PHPUnit
- `composer csfix` – php-cs-fixer, which lives in `tools/phpcs/vendor/bin/`, not `vendor/bin/`
- `pnpm run test --run` – Vitest
- `pnpm run test:types` – typecheck
- `pnpm run lint` – ESLint

## Conventions

- `__PLAYGROUND__` is a build-time constant from `kirbyup.config.js`, declared to TypeScript in `src/env.d.ts`.
- `playground/site/plugins/kirby-copilot` is a symlink to the sibling copilot checkout, for developing the two together.
- Test methods are snake_case and named after the behavior they pin; data providers are camelCase.

## Search Hints

- `window.panel.plugin("johannschopplich/content-translator"` – Panel registration
- `Kirby::plugin(` – PHP plugin registration
- `translator(` – model method and global helper
- `useContentTranslator` – main translation composable
