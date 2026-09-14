# Kirby Content Translator

Translation of Kirby content between a site's languages inside the Panel and from PHP: content is split into translation units, passes a strategy, and lands in the target language.

## Language

### Runs

**Import**:
Filling the current language with another language's eligible content, untranslated.
_Avoid_: sync, copy

**Single-language translation**:
Translating the current view's content into its language. Fields are left in the form as unsaved changes for review, while the title and slug are saved directly.
_Avoid_: per-language translation

**Batch translation**:
Translating the default language into several secondary languages in one run, each saved directly.
_Avoid_: bulk translation

**Batch outcome**:
What became of one target language in a batch translation: saved, held back by the user's own unsaved changes, locked by another user, failed, or not started because a lock stopped the run.
_Avoid_: skipped, status

**Invalid field**:
A field that fails its blueprint validation in a language a batch translation saved.
_Avoid_: violation, form error

### Fields

**Eligible field**:
A top-level field the configuration admits to import and translation: its type is enabled, it is not excluded, and its blueprint does not opt it out of translation.
_Avoid_: translatable field, syncable field

**Coverage**:
The share of eligible, source-filled fields that hold content in a secondary language.
_Avoid_: completion, progress

### Texts

**Translation unit**:
One text a strategy receives: a field value, a block's text, a structure cell, or a KirbyTag attribute.
_Avoid_: segment, text segment, fragment

**KirbyTag placeholder**:
The stand-in for a KirbyTag inside a translation unit, which a translation must return unchanged.
_Avoid_: placeholder (bare), tag marker

**Untranslatable text**:
Text a provider could only corrupt – blank, numeric, a bare URL, or nothing but KirbyTags – which never reaches a strategy.
_Avoid_: skipped text

**Rejection**:
A translation unit that keeps its source text because its strategy returned no usable translation for it.
_Avoid_: drop, failed unit

### Strategies

**Strategy**:
The way a translation is carried out: DeepL, AI through Kirby Copilot, or a custom one.
_Avoid_: provider, backend

**Provider**:
The external service behind a strategy: DeepL, or the AI provider Kirby Copilot is configured with.
_Avoid_: engine, strategy
