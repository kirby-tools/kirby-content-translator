import type { PluginConfig } from "../../../src/panel/types";
import { describe, expect, it, vi } from "vitest";
import { DEFAULT_FIELD_TYPES } from "../../../src/panel/constants";
import {
  getProviderAvailability,
  resolveTranslatorConfig,
} from "../../../src/panel/utils/translator-config";

// Assigned per test and read lazily by the `kirbyuse` mock below.
let thirdPartyPlugins: Record<string, unknown> = {};

vi.mock("kirbyuse", () => ({
  usePanel: () => ({ plugins: { thirdParty: thirdPartyPlugins } }),
}));

function resolveAvailability(config: PluginConfig, hasCopilot = false) {
  thirdPartyPlugins = hasCopilot ? { copilot: { apiVersion: 2 } } : {};
  return getProviderAvailability(config);
}

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
    const availability = resolveAvailability({ strategy: "custom" });

    expect(availability.hasAnyProvider).toBe(true);
    expect(availability.hasDefaultProvider).toBe(true);
    expect(availability.hasMultipleProviders).toBe(false);
  });

  it("requires a DeepL API key when the strategy resolves to DeepL", () => {
    expect(resolveAvailability({ strategy: "deepl" }).hasAnyProvider).toBe(
      false,
    );
    expect(
      resolveAvailability({ strategy: "deepl", DeepL: { apiKey: true } })
        .hasAnyProvider,
    ).toBe(true);
  });

  it("offers Copilot alone when the strategy resolves to AI", () => {
    const availability = resolveAvailability(
      { strategy: "ai", DeepL: { apiKey: true } },
      true,
    );

    expect(availability.hasAnyProvider).toBe(true);
    expect(availability.hasDefaultProvider).toBe(false);
    expect(availability.hasMultipleProviders).toBe(false);
  });

  it("offers both providers when a usable backend and Copilot are available", () => {
    const availability = resolveAvailability(
      { strategy: "deepl", DeepL: { apiKey: true } },
      true,
    );

    expect(availability.hasMultipleProviders).toBe(true);
  });
});
