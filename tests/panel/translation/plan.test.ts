import type { ModelFacts } from "../../../src/panel/translation/plan";
import { describe, expect, it } from "vitest";
import {
  planBatchLanguageTranslation,
  planImport,
  planSingleTranslation,
} from "../../../src/panel/translation/plan";

const TRANSLATABLE_MODEL: ModelFacts = {
  isHomePage: false,
  isErrorPage: false,
  isFileModel: false,
  isSiteModel: false,
};

describe("planImport", () => {
  it("patches title and slug independently", () => {
    const plan = planImport({
      ...TRANSLATABLE_MODEL,
      isTitleTranslationEnabled: false,
      isSlugTranslationEnabled: true,
      isCurrentLanguageDefault: false,
    });

    expect(plan.shouldPatchTitle).toBe(false);
    expect(plan.shouldPatchSlug).toBe(true);
  });

  it("blocks the slug when viewing the default language", () => {
    const plan = planImport({
      ...TRANSLATABLE_MODEL,
      isTitleTranslationEnabled: true,
      isSlugTranslationEnabled: true,
      isCurrentLanguageDefault: true,
    });

    expect(plan.shouldPatchTitle).toBe(true);
    expect(plan.shouldPatchSlug).toBe(false);
  });

  it.each([
    ["isHomePage", true],
    ["isErrorPage", true],
    ["isFileModel", false],
    ["isSiteModel", true],
  ] as const)("blocks the slug when %s", (modelFact, shouldStillPatchTitle) => {
    const plan = planImport({
      ...TRANSLATABLE_MODEL,
      [modelFact]: true,
      isTitleTranslationEnabled: true,
      isSlugTranslationEnabled: true,
      isCurrentLanguageDefault: false,
    });

    expect(plan.shouldPatchSlug).toBe(false);
    expect(plan.shouldPatchTitle).toBe(shouldStillPatchTitle);
  });
});

describe("planSingleTranslation", () => {
  it("requests a title translation when only the slug is enabled", () => {
    const plan = planSingleTranslation({
      ...TRANSLATABLE_MODEL,
      isTitleTranslationEnabled: false,
      isSlugTranslationEnabled: true,
      isTargetLanguageDefault: false,
      hasViewTitle: true,
    });

    expect(plan.shouldRequestTitleTranslation).toBe(true);
    expect(plan.shouldPatchTitle).toBe(false);
    expect(plan.shouldPatchSlug).toBe(true);
  });

  it("skips title and slug when the view has no title", () => {
    const plan = planSingleTranslation({
      ...TRANSLATABLE_MODEL,
      isTitleTranslationEnabled: true,
      isSlugTranslationEnabled: true,
      isTargetLanguageDefault: false,
      hasViewTitle: false,
    });

    expect(plan.shouldRequestTitleTranslation).toBe(false);
    expect(plan.shouldPatchTitle).toBe(false);
    expect(plan.shouldPatchSlug).toBe(false);
  });

  it("blocks the slug for the default target language", () => {
    const plan = planSingleTranslation({
      ...TRANSLATABLE_MODEL,
      isTitleTranslationEnabled: false,
      isSlugTranslationEnabled: true,
      isTargetLanguageDefault: true,
      hasViewTitle: true,
    });

    expect(plan.shouldRequestTitleTranslation).toBe(false);
    expect(plan.shouldPatchSlug).toBe(false);
  });
});

describe("planBatchLanguageTranslation", () => {
  it("patches the slug when only slug translation is enabled", () => {
    const plan = planBatchLanguageTranslation({
      ...TRANSLATABLE_MODEL,
      isTitleTranslationEnabled: false,
      isSlugTranslationEnabled: true,
      isTargetLanguageDefault: false,
    });

    expect(plan.shouldRequestTitleTranslation).toBe(true);
    expect(plan.shouldPatchTitle).toBe(false);
    expect(plan.shouldPatchSlug).toBe(true);
  });

  it("blocks the slug for the default target language even though the dialog filters it", () => {
    const plan = planBatchLanguageTranslation({
      ...TRANSLATABLE_MODEL,
      isTitleTranslationEnabled: true,
      isSlugTranslationEnabled: true,
      isTargetLanguageDefault: true,
    });

    expect(plan.shouldPatchTitle).toBe(true);
    expect(plan.shouldPatchSlug).toBe(false);
  });
});
