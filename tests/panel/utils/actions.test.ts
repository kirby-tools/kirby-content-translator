import type { TranslatorActionOptions } from "../../../src/panel/utils/actions";
import { describe, expect, it } from "vitest";
import { resolveTranslatorActions } from "../../../src/panel/utils/actions";

function options(
  overrides: Partial<TranslatorActionOptions> = {},
): TranslatorActionOptions {
  return {
    languageCodes: ["en", "de", "fr"],
    viewLanguageCode: "de",
    defaultLanguageCode: "en",
    isImportEnabled: true,
    isBatchTranslationEnabled: true,
    hasAnyStrategy: true,
    isDisabled: false,
    t: () => "",
    ...overrides,
  };
}

function summarize({ groups }: ReturnType<typeof resolveTranslatorActions>) {
  return groups.map((group) =>
    group.map(({ type, sourceLanguageCode, isDisabled }) => ({
      type,
      sourceLanguageCode,
      isDisabled,
    })),
  );
}

describe("resolveTranslatorActions", () => {
  it("offers an import and a translation from the default language in a secondary language", () => {
    expect(summarize(resolveTranslatorActions(options()))).toEqual([
      [{ type: "import", sourceLanguageCode: undefined, isDisabled: false }],
      [{ type: "translate", sourceLanguageCode: "en", isDisabled: false }],
    ]);
  });

  it("offers only the batch translation in the default language", () => {
    expect(
      summarize(resolveTranslatorActions(options({ viewLanguageCode: "en" }))),
    ).toEqual([
      [],
      [{ type: "batch", sourceLanguageCode: undefined, isDisabled: false }],
    ]);
  });

  it("disables the import and the translation in the default language with isBatchTranslationEnabled off", () => {
    expect(
      summarize(
        resolveTranslatorActions(
          options({ viewLanguageCode: "en", isBatchTranslationEnabled: false }),
        ),
      ),
    ).toEqual([
      [{ type: "import", sourceLanguageCode: undefined, isDisabled: true }],
      [{ type: "translate", sourceLanguageCode: "en", isDisabled: true }],
    ]);
  });

  it("offers an import from every other language with importFrom all", () => {
    expect(
      summarize(resolveTranslatorActions(options({ importFrom: "all" }))),
    ).toEqual([
      [
        { type: "import", sourceLanguageCode: "en", isDisabled: false },
        { type: "import", sourceLanguageCode: "fr", isDisabled: false },
      ],
      [{ type: "translate", sourceLanguageCode: undefined, isDisabled: false }],
    ]);
  });

  it("offers only the translation from the default language with isImportEnabled off", () => {
    expect(
      summarize(resolveTranslatorActions(options({ isImportEnabled: false }))),
    ).toEqual([
      [],
      [{ type: "translate", sourceLanguageCode: "en", isDisabled: false }],
    ]);
  });

  it("offers no translation without a strategy", () => {
    expect(
      summarize(resolveTranslatorActions(options({ hasAnyStrategy: false }))),
    ).toEqual([
      [{ type: "import", sourceLanguageCode: undefined, isDisabled: false }],
      [],
    ]);
  });

  it("disables every action with isDisabled", () => {
    const actions = resolveTranslatorActions(
      options({ importFrom: "all", viewLanguageCode: "en", isDisabled: true }),
    ).groups.flat();

    expect(actions).toHaveLength(4);
    expect(actions.every((action) => action.isDisabled)).toBe(true);
  });

  it("disables the import and the translation of a secondary language with isDisabled", () => {
    expect(
      summarize(resolveTranslatorActions(options({ isDisabled: true }))),
    ).toEqual([
      [{ type: "import", sourceLanguageCode: undefined, isDisabled: true }],
      [{ type: "translate", sourceLanguageCode: "en", isDisabled: true }],
    ]);
  });

  it("sets hasDefaultLanguageInfo for the default language only with isBatchTranslationEnabled off", () => {
    expect(
      resolveTranslatorActions(
        options({ viewLanguageCode: "en", isBatchTranslationEnabled: false }),
      ).hasDefaultLanguageInfo,
    ).toBe(true);
    expect(
      resolveTranslatorActions(options({ viewLanguageCode: "en" }))
        .hasDefaultLanguageInfo,
    ).toBe(false);
  });

  it("leaves hasDefaultLanguageInfo off with importFrom all", () => {
    expect(
      resolveTranslatorActions(
        options({
          viewLanguageCode: "en",
          isBatchTranslationEnabled: false,
          importFrom: "all",
        }),
      ).hasDefaultLanguageInfo,
    ).toBe(false);
  });
});
