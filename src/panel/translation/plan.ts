/**
 * Pure title/slug decision planners for the three Panel translation flows.
 *
 * The flows share one predicate core but compose it differently on purpose –
 * do not unify them.
 *
 * Facts must be assembled at method-call time, never at initialization: the
 * view button component assigns `fields` and friends from a lazy API fetch
 * after `initializeConfig` ran.
 */

export interface ModelFacts {
  isHomePage: boolean;
  isErrorPage: boolean;
  isFileModel: boolean;
  isSiteModel: boolean;
}

export interface TitleSlugConfigFacts {
  isTitleTranslationEnabled: boolean;
  isSlugTranslationEnabled: boolean;
}

export interface ImportFacts extends ModelFacts, TitleSlugConfigFacts {
  /**
   * The slug guard keys on the language being patched (the currently viewed
   * one), not the import source – changing the default language's slug would
   * rename the page folder.
   * @see https://github.com/kirby-tools/kirby-content-translator/issues/5
   */
  isCurrentLanguageDefault: boolean;
}

export interface ImportPlan {
  /** The title is patched verbatim from the source model – never translated. */
  shouldPatchTitle: boolean;
  /** The slug is derived from the untranslated source title. */
  shouldPatchSlug: boolean;
}

export interface SingleTranslationFacts
  extends ModelFacts, TitleSlugConfigFacts {
  /**
   * Default-language slugs are never patched – the page folder would be
   * renamed.
   * @see https://github.com/kirby-tools/kirby-content-translator/issues/5
   */
  isTargetLanguageDefault: boolean;
  /** `panel.view.title` is nullable – no title means nothing to translate. */
  hasViewTitle: boolean;
}

export interface SingleTranslationPlan {
  /** Whether the executor translates the view title at all. */
  shouldRequestTitleTranslation: boolean;
  shouldPatchTitle: boolean;
  /** The slug is derived from the translated title. */
  shouldPatchSlug: boolean;
}

export interface BatchLanguageFacts extends ModelFacts, TitleSlugConfigFacts {
  /**
   * Default-language slugs are never patched (folder rename, issue #5) –
   * checked even though the batch dialog already filters the default language
   * out of the selection. The planner must not trust its callers' filtering.
   */
  isTargetLanguageDefault: boolean;
}

export interface BatchLanguagePlan {
  /** Whether the executor translates the default language's title at all. */
  shouldRequestTitleTranslation: boolean;
  shouldPatchTitle: boolean;
  /** The slug is derived from the translated title. */
  shouldPatchSlug: boolean;
}

export function planImport(facts: ImportFacts): ImportPlan {
  return {
    shouldPatchTitle: isTitleEligible(facts),
    shouldPatchSlug:
      isSlugEligibleModel(facts) && !facts.isCurrentLanguageDefault,
  };
}

export function planSingleTranslation(
  facts: SingleTranslationFacts,
): SingleTranslationPlan {
  const isTitlePatchEligible = isTitleEligible(facts) && facts.hasViewTitle;
  const isSlugPatchEligible =
    isSlugEligibleModel(facts) &&
    !facts.isTargetLanguageDefault &&
    facts.hasViewTitle;

  return {
    shouldRequestTitleTranslation: isTitlePatchEligible || isSlugPatchEligible,
    shouldPatchTitle: isTitlePatchEligible,
    shouldPatchSlug: isSlugPatchEligible,
  };
}

export function planBatchLanguageTranslation(
  facts: BatchLanguageFacts,
): BatchLanguagePlan {
  const isTitlePatchEligible = isTitleEligible(facts);
  const isSlugPatchEligible =
    isSlugEligibleModel(facts) && !facts.isTargetLanguageDefault;

  return {
    shouldRequestTitleTranslation: isTitlePatchEligible || isSlugPatchEligible,
    shouldPatchTitle: isTitlePatchEligible,
    shouldPatchSlug: isSlugPatchEligible,
  };
}

function isTitleEligible(facts: ModelFacts & TitleSlugConfigFacts) {
  return facts.isTitleTranslationEnabled && !facts.isFileModel;
}

function isSlugEligibleModel(facts: ModelFacts & TitleSlugConfigFacts) {
  return (
    facts.isSlugTranslationEnabled &&
    !facts.isHomePage &&
    !facts.isErrorPage &&
    !facts.isFileModel &&
    !facts.isSiteModel
  );
}
