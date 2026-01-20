# Kirby Content Translator

Commercial Kirby CMS plugin for translating content between languages using DeepL or custom AI providers.

## Tech Stack

- Panel: Vue 2.7 with Composition API (`<script setup>`, composables)
- Build: kirbyup (Vite-based bundler for Kirby Panel plugins)
- Vue utilities: kirbyuse (provides `usePanel`, `useSection`, `useContent`, etc.)
- PHP: Kirby 4/5 compatible

## Commands

- `pnpm run dev` - start playground with hot reload
- `pnpm run build` - build Panel bundle
- `pnpm run test` - run Vitest tests
- `pnpm run test:types` - typecheck

## Entry Points

- Plugin ID: `johannschopplich/content-translator`
- PHP bootstrap: `index.php` (registers sections, API routes, model methods)
- Panel entry: `src/panel/index.ts` (registers Vue components via `window.panel.plugin()`)
- API routes: `src/extensions/api.php`
- Local dev: `playground/` (self-contained Kirby installation)

## Architecture

Panel extensions are registered in `src/panel/index.ts`:

- `sections`: Custom Panel sections (ContentTranslator)
- `viewButtons`: Header dropdown buttons
- `icons`: SVG icons
- `use`: Vue mixins for Kirby 4 compatibility

PHP extensions in `src/extensions/`:

- `api.php`: REST endpoints for translation operations
- `sections.php`: Section blueprints and props
- `translations.php`: i18n strings

## Search Hints

- `window.panel.plugin("johannschopplich/content-translator"` - Panel registration
- `Kirby::plugin(` - PHP plugin registration
- `translator(` - model method and global helper
- `useContentTranslator` - main translation composable
