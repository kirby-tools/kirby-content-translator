import { describe, expect, it } from "vitest";
import { DEFAULT_FIELD_TYPES } from "../../../src/panel/constants";
import {
  describeMissingStrategy,
  getStrategyAvailability,
  resolveTranslatorConfig,
} from "../../../src/panel/utils/translator-config";

describe("resolveTranslatorConfig", () => {
  it("prefers options over config over defaults", () => {
    const resolvedConfig = resolveTranslatorConfig(
      { title: true, importFrom: "en", fieldTypes: ["text"] },
      { title: false, importFrom: "de" },
    );

    expect(resolvedConfig.isTitleTranslationEnabled).toBe(false);
    expect(resolvedConfig.importFrom).toBe("de");
    expect(resolvedConfig.fieldTypes).toEqual(["text"]);
  });

  it("falls back to defaults when neither options nor config are set", () => {
    const resolvedConfig = resolveTranslatorConfig({}, {});

    expect(resolvedConfig.isImportEnabled).toBe(true);
    expect(resolvedConfig.isBatchTranslationEnabled).toBe(true);
    expect(resolvedConfig.isTitleTranslationEnabled).toBe(false);
    expect(resolvedConfig.isSlugTranslationEnabled).toBe(false);
    expect(resolvedConfig.shouldConfirm).toBe(false);
    expect(resolvedConfig.importFrom).toBeUndefined();
    expect(resolvedConfig.fieldTypes).toEqual([...DEFAULT_FIELD_TYPES]);
    expect(resolvedConfig.includeFields).toEqual([]);
    expect(resolvedConfig.excludeFields).toEqual([]);
    expect(resolvedConfig.kirbyTags).toEqual({});
    expect(resolvedConfig.systemPrompt).toBeUndefined();
  });

  it("coerces loose boolean values from blueprint YAML", () => {
    expect(
      resolveTranslatorConfig({}, { title: "true" }).isTitleTranslationEnabled,
    ).toBe(true);
    expect(
      resolveTranslatorConfig({}, { title: "1" }).isTitleTranslationEnabled,
    ).toBe(true);
    expect(
      resolveTranslatorConfig({}, { title: 1 }).isTitleTranslationEnabled,
    ).toBe(true);
    expect(
      resolveTranslatorConfig({}, { title: "false" }).isTitleTranslationEnabled,
    ).toBe(false);
    expect(
      resolveTranslatorConfig({}, { title: 0 }).isTitleTranslationEnabled,
    ).toBe(false);
  });

  it("lowercases the tag types of kirbyTags", () => {
    expect(
      resolveTranslatorConfig({}, { kirbyTags: { Link: ["text"] } }).kirbyTags,
    ).toEqual({ link: ["text"] });
  });
});

describe("getStrategyAvailability", () => {
  it("treats a custom strategy as usable without a DeepL key", () => {
    const availability = getStrategyAvailability(
      { strategy: "custom" },
      "missing",
    );

    expect(availability.hasAnyStrategy).toBe(true);
    expect(availability.hasDefaultStrategy).toBe(true);
    expect(availability.hasMultipleStrategies).toBe(false);
  });

  it("requires a DeepL API key when the strategy resolves to DeepL", () => {
    expect(
      getStrategyAvailability({ strategy: "deepl" }, "missing").hasAnyStrategy,
    ).toBe(false);
    expect(
      getStrategyAvailability(
        { strategy: "deepl", DeepL: { apiKey: true } },
        "missing",
      ).hasAnyStrategy,
    ).toBe(true);
  });

  it("offers Copilot alone when the strategy resolves to AI", () => {
    const availability = getStrategyAvailability(
      { strategy: "ai", DeepL: { apiKey: true } },
      "ready",
    );

    expect(availability.hasAnyStrategy).toBe(true);
    expect(availability.hasDefaultStrategy).toBe(false);
    expect(availability.hasMultipleStrategies).toBe(false);
  });

  it("offers both strategies when DeepL has an API key and Copilot is ready", () => {
    const availability = getStrategyAvailability(
      { strategy: "deepl", DeepL: { apiKey: true } },
      "ready",
    );

    expect(availability.hasMultipleStrategies).toBe(true);
  });
});

describe("describeMissingStrategy", () => {
  it("names DeepL.apiKey, a custom strategy, and installing Copilot for `missing`", () => {
    expect(describeMissingStrategy({}, "missing")).toBe(
      'Set the "johannschopplich.content-translator.DeepL.apiKey" option or a custom "johannschopplich.content-translator.strategy", or install Kirby Copilot for AI translations.',
    );
  });

  it("names only the Copilot update for strategy ai and `outdated`", () => {
    expect(describeMissingStrategy({ strategy: "ai" }, "outdated")).toBe(
      'The "johannschopplich.content-translator.strategy" option is set to "ai", so update Kirby Copilot, as the installed version cannot run AI translations.',
    );
  });
});
