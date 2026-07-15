import { describe, expect, it } from "vitest";
import { DEFAULT_FIELD_TYPES } from "../../../src/panel/constants";
import {
  resolveInitialProvider,
  resolveTranslatorConfig,
} from "../../../src/panel/utils/translator-config";

describe("resolveTranslatorConfig", () => {
  it("prefers options over config over defaults", () => {
    const resolvedConfig = resolveTranslatorConfig(
      { title: true, importFrom: "en", fieldTypes: ["text"] },
      { title: false, importFrom: "de" },
    );

    expect(resolvedConfig.translateTitle).toBe(false);
    expect(resolvedConfig.importFrom).toBe("de");
    expect(resolvedConfig.fieldTypes).toEqual(["text"]);
  });

  it("falls back to defaults when neither options nor config are set", () => {
    const resolvedConfig = resolveTranslatorConfig({}, {});

    expect(resolvedConfig.allowImport).toBe(true);
    expect(resolvedConfig.allowBatchTranslation).toBe(true);
    expect(resolvedConfig.translateTitle).toBe(false);
    expect(resolvedConfig.translateSlug).toBe(false);
    expect(resolvedConfig.shouldConfirm).toBe(false);
    expect(resolvedConfig.importFrom).toBeUndefined();
    expect(resolvedConfig.fieldTypes).toEqual([...DEFAULT_FIELD_TYPES]);
    expect(resolvedConfig.includeFields).toEqual([]);
    expect(resolvedConfig.excludeFields).toEqual([]);
    expect(resolvedConfig.kirbyTags).toEqual({});
    expect(resolvedConfig.systemPrompt).toBeUndefined();
  });

  it("coerces loose boolean values from blueprint YAML", () => {
    expect(resolveTranslatorConfig({}, { title: "true" }).translateTitle).toBe(
      true,
    );
    expect(resolveTranslatorConfig({}, { title: "1" }).translateTitle).toBe(
      true,
    );
    expect(resolveTranslatorConfig({}, { title: 1 }).translateTitle).toBe(true);
    expect(resolveTranslatorConfig({}, { title: "false" }).translateTitle).toBe(
      false,
    );
    expect(resolveTranslatorConfig({}, { title: 0 }).translateTitle).toBe(
      false,
    );
  });
});

describe("resolveInitialProvider", () => {
  it.each([
    ["ai", { hasDefaultProvider: true, hasMultipleProviders: true }, "ai"],
    ["ai", { hasDefaultProvider: true, hasMultipleProviders: false }, "deepl"],
    ["deepl", { hasDefaultProvider: true, hasMultipleProviders: true }, "deepl"],
    [undefined, { hasDefaultProvider: false, hasMultipleProviders: false }, "ai"],
    ["invalid", { hasDefaultProvider: true, hasMultipleProviders: true }, "deepl"],
  ])(
    "resolves requested provider %j with availability %j to %j",
    (requestedProvider, availability, expectedProvider) => {
      expect(resolveInitialProvider(requestedProvider, availability)).toBe(
        expectedProvider,
      );
    },
  );
});
