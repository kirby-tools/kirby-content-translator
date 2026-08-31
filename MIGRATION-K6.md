# Kirby 6 Migration Checklist

Everything in this plugin that only works because the Panel still runs Vue 2 or
still ships a Kirby 5 alias. Each entry names the site, what breaks, and what to
put there instead. Nothing here can be fixed ahead of the Vue 3 switch – the
spellings that survive both versions have already been changed in place.

Read top to bottom while migrating, then delete the file.

## Vue 3

- **`LanguagesViewWrapper`** – `Views/LanguagesViewWrapper.ts`. Patches Vue 2
  vnode internals of Kirby's `k-languages-view`, and `index.ts:21` installs it
  behind `isKirby5()`, which returns true on Kirby 6 as well. Vue 3 warns about
  the duplicate registration, assigns anyway, and the whole Languages view
  throws at render – not just the coverage panel. Kirby 6 offers no
  vnode-patching seam: re-register `k-languages-view` as a Vue 3 component that
  renders the original through `<component :is>` plus a sibling, or ask the
  Kirby team for a real extension point.
- **`slot="options"`** – `Sections/ContentTranslator.vue:109`. Vue 2 slot
  syntax; Vue 3 ignores it, so the section header loses its licensing buttons.
  Replace with `<template #options v-if="licenseStatus !== undefined">`.
- **`.native` modifier** – `Shared/TranslationTreeNode.vue:91`. Vue 3 drops the
  modifier and the click guard with it. Move the handler onto a real element.

## Renamed Panel Components

- **`k-dropdown-content`** – `ViewButtons/ContentTranslatorDropdownButton.vue:100,106`.
  Kirby 6 keeps the tag only as a deprecated alias of `Dropdown.vue`. Rename to
  `<k-dropdown>`; the `open`/`close`/`toggle` methods and the `align-x`,
  `navigate` and `theme` props are unchanged.

## Version Branches

- **`isKirby5()`** – `index.ts:21`, `legacy.ts:8`,
  `ViewButtons/ContentTranslatorDropdownButton.vue:54,85`. Kirby 6 is the Vue 3
  break, so every branch goes with the migration.
