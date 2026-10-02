import { describe, expect, it } from "vitest";
import {
  describeMissingStrategy,
  getStrategyAvailability,
  resolveTranslatorConfig,
} from "../../../src/panel/utils/translator-config";

describe("resolveTranslatorConfig", () => {
  const config = { fieldTypes: ["text"] };

  it("prefers options over config", () => {
    const resolvedConfig = resolveTranslatorConfig(
      { ...config, title: true, importFrom: "en" },
      { title: false, importFrom: "de" },
    );

    expect(resolvedConfig.isTitleTranslationEnabled).toBe(false);
    expect(resolvedConfig.importFrom).toBe("de");
  });

  it("falls back to defaults for unset options", () => {
    const resolvedConfig = resolveTranslatorConfig(config, {});

    expect(resolvedConfig.isImportEnabled).toBe(true);
    expect(resolvedConfig.isBatchTranslationEnabled).toBe(true);
    expect(resolvedConfig.isTitleTranslationEnabled).toBe(false);
    expect(resolvedConfig.isSlugTranslationEnabled).toBe(false);
    expect(resolvedConfig.shouldConfirm).toBe(false);
    expect(resolvedConfig.importFrom).toBeUndefined();
    expect(resolvedConfig.includeFields).toEqual([]);
    expect(resolvedConfig.excludeFields).toEqual([]);
    expect(resolvedConfig.kirbyTags).toEqual({});
    expect(resolvedConfig.systemPrompt).toBeUndefined();
  });

  it("coerces loose boolean values from blueprint YAML", () => {
    expect(
      resolveTranslatorConfig(config, { title: "true" })
        .isTitleTranslationEnabled,
    ).toBe(true);
    expect(
      resolveTranslatorConfig(config, { title: "1" }).isTitleTranslationEnabled,
    ).toBe(true);
    expect(
      resolveTranslatorConfig(config, { title: 1 }).isTitleTranslationEnabled,
    ).toBe(true);
    expect(
      resolveTranslatorConfig(config, { title: "false" })
        .isTitleTranslationEnabled,
    ).toBe(false);
    expect(
      resolveTranslatorConfig(config, { title: 0 }).isTitleTranslationEnabled,
    ).toBe(false);
  });

  it("lowercases the fieldTypes a blueprint sets", () => {
    expect(
      resolveTranslatorConfig(config, { fieldTypes: ["Markdown"] }).fieldTypes,
    ).toEqual(["markdown"]);
  });

  it("lowercases the tag types of kirbyTags", () => {
    expect(
      resolveTranslatorConfig(config, { kirbyTags: { Link: ["text"] } })
        .kirbyTags,
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
