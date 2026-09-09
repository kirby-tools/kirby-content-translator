# Kirby Content Translator

Commercial Kirby CMS plugin for translating content between languages using DeepL or custom AI providers.

## Commands

```bash
composer test          # PHPUnit
composer csfix         # php-cs-fixer, lives in tools/phpcs/vendor/bin/, not vendor/bin/
pnpm run test --run    # Vitest
pnpm run test:types    # typecheck
pnpm run lint          # ESLint
```

## Conventions

- `__PLAYGROUND__` is a build-time constant defined in `kirbyup.config.js`.
- `playground/site/plugins/kirby-copilot` is a symlink to the sibling copilot checkout, for developing the two together.
