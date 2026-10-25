# Kirby Content Translator

Translation of Kirby content between a site's languages inside the Panel and from PHP: content is split into translation units, passes a strategy, and lands in the target language.

## Language

### Runs

**Import**:
Filling the current language with another language's eligible content, untranslated.
_Avoid_: sync, copy

**Single-language translation**:
Translating the current view's content into its language. Fields are left in the form as unsaved changes for review, while the title, the slug, and the cascade are saved directly.
_Avoid_: per-language translation

**Batch translation**:
Translating the default language's content or language variables into several secondary languages in one run, each saved directly.
_Avoid_: bulk translation

**Batch outcome**:
What became of one target language of a batch translation – for one model, or for the language variables – or of one cascaded model of a single-language translation: saved or failed, and for a model also locked by another user, not started because a lock on its model stopped it, or held back.
_Avoid_: skipped, status

**Held back**:
A model ruled out in a target language before the run starts: by unsaved changes – in that language, or in the default language a cascaded model is translated from – or by another user who is editing a cascaded model.
_Avoid_: skipped

**Invalid field**:
A field that fails its blueprint validation in a language a batch translation saved.
_Avoid_: violation, form error

**Variable translation**:
Translating the language variables of the default language into one secondary language, or into several as a batch translation from the default language's view: the variables with text to translate that are missing or blank in the target language, or still identical to the default language, saved directly.
_Avoid_: string translation, variable sync

### Models

**Host**:
The model whose view starts a run.
_Avoid_: parent, owner

**Cascade**:
The models a run translates along with its host, named by the host's blueprint and always saved directly.
_Avoid_: related models, children, linked models

### Fields

**Eligible field**:
A top-level field the configuration admits to import and translation: its type is enabled, it is not excluded, and its blueprint does not opt it out of translation.
_Avoid_: translatable field, syncable field

**Coverage**:
The share of eligible, source-filled fields that hold content in a secondary language.
_Avoid_: completion, progress

### Texts

**Translation unit**:
One text a strategy receives: a field value, a block's text, a structure cell, a KirbyTag attribute, or a text of a language variable.
_Avoid_: segment, text segment, fragment

**KirbyTag placeholder**:
The stand-in for a KirbyTag inside a translation unit, which a translation must return unchanged.
_Avoid_: placeholder (bare), tag marker

**Language variable**:
A key and its text, or its `tc()` forms, that a language holds for templates to print with `t()`, `tt()`, and `tc()`; _variable_ for short.
_Avoid_: translation, translation string, string

**Variable placeholder**:
A `{…}` slot in a language variable that Kirby fills in at runtime, which a translation must return unchanged.
_Avoid_: template tag, placeholder (bare)

**Untranslatable text**:
Text a provider could only corrupt – blank, numeric, a bare URL, or nothing but KirbyTags and variable placeholders – which never reaches a strategy.
_Avoid_: skipped text

**Rejection**:
A translation unit that keeps its source text because its strategy returned no usable translation for it; in a language variable, the whole variable is not translated.
_Avoid_: drop, failed unit

### Strategies

**Strategy**:
The way a translation is carried out: DeepL, AI through Kirby Copilot, or a custom one.
_Avoid_: provider, backend

**Provider**:
The external service behind a strategy: DeepL, or the AI provider Kirby Copilot is configured with.
_Avoid_: engine, strategy
