import type {
  KirbyTagRules,
  TranslationStrategy,
  TranslationUnit,
} from "../../../src/panel/translation/types";
import type { LanguageVariables } from "../../../src/panel/translation/variables";
import { describe, expect, it } from "vitest";
import {
  planVariableTranslation,
  translateVariables,
} from "../../../src/panel/translation/variables";

const NO_KIRBY_TAGS: KirbyTagRules = { types: [], attributes: {} };

describe("planVariableTranslation", () => {
  it.each([
    ["missing", "Shopping cart", undefined],
    ["blank", "Shopping cart", "  "],
    ["a plural of blank forms", ["No items", "{count} items"], ["", " "]],
    ["still identical", "Shopping cart", "Shopping cart"],
    [
      "a plural still identical",
      ["No items", "{count} items"],
      ["No items", "{count} items"],
    ],
  ])(
    "takes a variable that is %s in the target language as pending",
    (_, sourceValue, targetValue) => {
      const plan = planVariableTranslation(
        { "cart.items": sourceValue },
        { "cart.items": targetValue },
        { reservedKeys: [], kirbyTags: NO_KIRBY_TAGS },
      );

      expect(plan.pending).toEqual({ "cart.items": sourceValue });
    },
  );

  it.each([
    ["translated", "Shopping cart", "Warenkorb"],
    [
      "a plural with a translated form",
      ["No items", "{count} items"],
      ["Keine Artikel", ""],
    ],
  ])(
    "keeps a variable that is %s in the target language",
    (_, sourceValue, targetValue) => {
      const plan = planVariableTranslation(
        { "cart.items": sourceValue },
        { "cart.items": targetValue },
        { reservedKeys: [], kirbyTags: NO_KIRBY_TAGS },
      );

      expect(plan.translatableKeys).toEqual(["cart.items"]);
      expect(plan.pending).toEqual({});
    },
  );

  it.each([
    ["a lone variable placeholder", "cart.empty", "{count}"],
    ["an empty plural", "cart.tags", []],
    ["a number", "cart.limit", 10],
    ["a URL", "support.url", "https://example.com/support"],
    ["a numeric key", "404", "Not found"],
    ["an empty key", "", "Empty"],
    ["a reserved key", "menu", "Menu"],
  ])("leaves out a variable with %s", (_, key, value) => {
    const plan = planVariableTranslation(
      { [key]: value },
      {},
      { reservedKeys: ["menu"], kirbyTags: NO_KIRBY_TAGS },
    );

    expect(plan.translatableKeys).toEqual([]);
  });
});

describe("translateVariables", () => {
  it("translates a plural form by form and keeps a form that is not a string", async () => {
    const { translatedVariables } = await translate(
      { "cart.count": ["No items", 5, "{count} items"] },
      (text) => `[de]${text}`,
    );

    expect(translatedVariables).toEqual({
      "cart.count": ["[de]No items", 5, "[de]{count} items"],
    });
  });

  it("hands variable placeholders to the strategy as KirbyTag placeholders", async () => {
    const { sentTexts, translatedVariables } = await translate(
      {
        invite: "{name} invited {count} people",
        greeting: "Hello {{ name }}",
        badge: "Made with {< heart >}",
      },
      (text) => `[de]${text}`,
    );

    expect(sentTexts).toEqual([
      "<c0/> invited <c1/> people",
      "Hello <c0/>",
      "Made with <c0/>",
    ]);
    expect(translatedVariables).toEqual({
      invite: "[de]{name} invited {count} people",
      greeting: "[de]Hello {{ name }}",
      badge: "[de]Made with {< heart >}",
    });
  });

  it("restores variable placeholders by number after the translation reorders them", async () => {
    const { translatedVariables } = await translate(
      { invite: "{name} invited {count} people" },
      () => "<c1/> Personen wurden von <c0/> eingeladen",
    );

    expect(translatedVariables).toEqual({
      invite: "{count} Personen wurden von {name} eingeladen",
    });
  });

  it("numbers variable placeholders after the KirbyTags of a variable", async () => {
    const { sentTexts, translatedVariables } = await translate(
      { terms: "Accept the (link: /terms text: terms) of {site}" },
      (text) => `[de]${text}`,
      { types: ["link"], attributes: { link: ["text"] } },
    );

    expect(sentTexts).toEqual(["Accept the <c0/> of <c1/>", "terms"]);
    expect(translatedVariables).toEqual({
      terms: "[de]Accept the (link: /terms text: [de]terms) of {site}",
    });
  });

  it("leaves out a variable whose forms lose their placeholders and names it once", async () => {
    const { translatedVariables, result } = await translate(
      {
        "cart.title": "Shopping cart",
        "cart.count": ["{count} item", "{count} items"],
      },
      (text) => `[de]${text.replace(/<c\d+\/>\s*/g, "")}`,
    );

    expect(translatedVariables).toEqual({ "cart.title": "[de]Shopping cart" });
    expect(result).toEqual({
      translatableCount: 2,
      translatedCount: 1,
      rejections: [
        expect.objectContaining({
          fieldKey: "cart.count",
          reason: "variable placeholder mismatch",
        }),
      ],
    });
  });
});

async function translate(
  pendingVariables: LanguageVariables,
  translateText: (text: string) => string,
  kirbyTags = NO_KIRBY_TAGS,
) {
  const sentTexts: string[] = [];
  const strategy: TranslationStrategy = {
    execute: async (units: TranslationUnit[]) =>
      units.map(({ text }) => {
        sentTexts.push(text);
        return translateText(text);
      }),
  };

  const { translatedVariables, result } = await translateVariables(
    pendingVariables,
    {
      strategy,
      sourceLanguage: { code: "en", name: "English" },
      targetLanguage: { code: "de", name: "Deutsch" },
      kirbyTags,
    },
  );

  return { sentTexts, translatedVariables, result };
}
