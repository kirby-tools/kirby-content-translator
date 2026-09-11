import { describe, expect, it } from "vitest";
import { DEFAULT_FIELD_TYPES } from "../../../src/panel/constants";
import {
  describeMissingStrategy,
  getProviderAvailability,
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
});

describe("getProviderAvailability", () => {
  it("treats a custom strategy as a usable backend without a DeepL key", () => {
    const availability = getProviderAvailability(
      { strategy: "custom" },
      "missing",
    );

    expect(availability.hasAnyProvider).toBe(true);
    expect(availability.hasDefaultProvider).toBe(true);
    expect(availability.hasMultipleProviders).toBe(false);
  });

  it("requires a DeepL API key when the strategy resolves to DeepL", () => {
    expect(
      getProviderAvailability({ strategy: "deepl" }, "missing").hasAnyProvider,
    ).toBe(false);
    expect(
      getProviderAvailability(
        { strategy: "deepl", DeepL: { apiKey: true } },
        "missing",
      ).hasAnyProvider,
    ).toBe(true);
  });

  it("offers Copilot alone when the strategy resolves to AI", () => {
    const availability = getProviderAvailability(
      { strategy: "ai", DeepL: { apiKey: true } },
      "ready",
    );

    expect(availability.hasAnyProvider).toBe(true);
    expect(availability.hasDefaultProvider).toBe(false);
    expect(availability.hasMultipleProviders).toBe(false);
  });

  it("offers both providers when a usable backend and Copilot are available", () => {
    const availability = getProviderAvailability(
      { strategy: "deepl", DeepL: { apiKey: true } },
      "ready",
    );

    expect(availability.hasMultipleProviders).toBe(true);
  });
});

describe("describeMissingStrategy", () => {
  it("names the DeepL.apiKey and strategy options and the Copilot install without Copilot", () => {
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
