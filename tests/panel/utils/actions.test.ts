import type { TranslatorActionOptions } from "../../../src/panel/utils/actions";
import { describe, expect, it } from "vitest";
import {
  hasDefaultLanguageInfo,
  resolveTranslatorActions,
} from "../../../src/panel/utils/actions";

function options(
  overrides: Partial<TranslatorActionOptions> = {},
): TranslatorActionOptions {
  return {
    languages: ["en", "de", "fr"],
    language: "de",
    defaultLanguage: "en",
    isImportEnabled: true,
    isBatchTranslationEnabled: true,
    hasAnyStrategy: true,
    isDisabled: false,
    t: (key) => key.split(".").at(-1)!,
    ...overrides,
  };
}

function summarize(actions: ReturnType<typeof resolveTranslatorActions>) {
  return actions.map((group) =>
    group.map(({ type, from, disabled }) => ({ type, from, disabled })),
  );
}

describe("resolveTranslatorActions", () => {
  it("offers an import and a translation from the default language in a secondary language", () => {
    expect(summarize(resolveTranslatorActions(options()))).toEqual([
      [{ type: "import", from: undefined, disabled: false }],
      [{ type: "translate", from: "en", disabled: false }],
    ]);
  });

  it("offers only the batch translation in the default language", () => {
    expect(
      summarize(resolveTranslatorActions(options({ language: "en" }))),
    ).toEqual([[], [{ type: "batch", from: undefined, disabled: false }]]);
  });

  it("disables the import and the translation in the default language without batch", () => {
    expect(
      summarize(
        resolveTranslatorActions(
          options({ language: "en", isBatchTranslationEnabled: false }),
        ),
      ),
    ).toEqual([
      [{ type: "import", from: undefined, disabled: true }],
      [{ type: "translate", from: "en", disabled: true }],
    ]);
  });

  it("offers an import from every other language with importFrom all", () => {
    expect(
      summarize(resolveTranslatorActions(options({ importFrom: "all" }))),
    ).toEqual([
      [
        { type: "import", from: "en", disabled: false },
        { type: "import", from: "fr", disabled: false },
      ],
      [{ type: "translate", from: undefined, disabled: false }],
    ]);
  });

  it("offers no translation without a strategy", () => {
    expect(
      summarize(resolveTranslatorActions(options({ hasAnyStrategy: false }))),
    ).toEqual([[{ type: "import", from: undefined, disabled: false }], []]);
  });

  it("disables every action with isDisabled", () => {
    const actions = resolveTranslatorActions(
      options({ importFrom: "all", language: "en", isDisabled: true }),
    ).flat();

    expect(actions).toHaveLength(4);
    expect(actions.every((action) => action.disabled)).toBe(true);
  });
});

describe("hasDefaultLanguageInfo", () => {
  it("explains the default language once batch translation is off", () => {
    expect(
      hasDefaultLanguageInfo(
        options({ language: "en", isBatchTranslationEnabled: false }),
      ),
    ).toBe(true);
    expect(hasDefaultLanguageInfo(options({ language: "en" }))).toBe(false);
  });

  it("skips the explanation with importFrom all", () => {
    expect(
      hasDefaultLanguageInfo(
        options({
          language: "en",
          isBatchTranslationEnabled: false,
          importFrom: "all",
        }),
      ),
    ).toBe(false);
  });
});
