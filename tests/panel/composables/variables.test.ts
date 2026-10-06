import type { PanelLanguage } from "kirby-types";
import type { Mock } from "vitest";
import type {
  PluginConfig,
  TranslatorOptions,
  VariablesResponse,
} from "../../../src/panel/types";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { PLUGIN_CONTEXT_API_ROUTE } from "../../../src/panel/constants";

// Assigned in `beforeEach` and read lazily by the mocks below.
let panel: ReturnType<typeof createPanelStub>;
let openFieldsDialog: Mock<(options: any) => Promise<any>>;
let escapeHTML: Mock<(text: string) => string>;
let pluginConfig: Partial<PluginConfig>;
let variablesResponses: VariablesResponse[];
let translateTexts: (texts: string[], targetLanguage: string) => string[];

const ENGLISH = { code: "en", name: "English", default: true } as PanelLanguage;
const GERMAN = { code: "de", name: "Deutsch", default: false } as PanelLanguage;
const FRENCH = {
  code: "fr",
  name: "Français",
  default: false,
} as PanelLanguage;

const SOURCE_VARIABLES = {
  "cart.title": "Shopping cart",
  "cart.items": "{count} items in your cart",
  "cart.count": ["No items", "One item", "{count} items"],
  greeting: "Hello {{ name }}",
  footer: "Imprint",
};

const TARGET_VARIABLES = {
  "cart.title": "",
  "cart.count": ["No items", "One item", "{count} items"],
  greeting: "Hello {{ name }}",
  footer: "Impressum",
  legacy: "Alt",
};

vi.mock("kirbyuse", async () => {
  const { baseKirbyuseMock } = await import("../helpers/mock-kirbyuse");
  return {
    ...baseKirbyuseMock(),
    isLocalDev: () => true,
    usePanel: () => panel,
    useApi: () => panel.api,
    useDialog: () => ({ openFieldsDialog }),
    useHelpers: () => ({ string: { escapeHTML } }),
  };
});

function createPanelStub() {
  return {
    t: vi.fn((key: string, data?: Record<string, unknown>) =>
      data ? `${key} ${JSON.stringify(data)}` : key,
    ),
    languages: [ENGLISH, GERMAN, FRENCH],
    language: ENGLISH,
    translation: { code: "en" },
    view: { path: "languages/de", isLoading: false, reload: vi.fn() },
    dialog: { open: vi.fn() },
    api: {
      get: vi.fn(async (route: string) => {
        if (route === PLUGIN_CONTEXT_API_ROUTE) {
          return {
            config: {
              DeepL: { apiKey: true },
              fieldTypes: [],
              ...pluginConfig,
            },
            kirbyTagTypes: ["link"],
          };
        }

        // The last response stands for every later read.
        return variablesResponses.length > 1
          ? variablesResponses.shift()
          : variablesResponses[0];
      }),
      post: vi.fn(
        async (
          _route: string,
          payload: { texts: string[]; targetLanguage: string },
        ) => ({
          texts: translateTexts(payload.texts, payload.targetLanguage),
        }),
      ),
      patch: vi.fn(
        async (_path: string, _payload: Record<string, unknown>) => ({}),
      ),
    },
    notification: {
      open: vi.fn(),
      success: vi.fn(),
      error: vi.fn(),
      close: vi.fn(),
    },
    plugins: { thirdParty: {} },
  };
}

function variables(
  source: Record<string, unknown>,
  targets: Record<string, Record<string, unknown>>,
  reservedKeys: string[] = [],
): VariablesResponse {
  return {
    en: { variables: source, reservedKeys: [] },
    ...Object.fromEntries(
      Object.entries(targets).map(([code, target]) => [
        code,
        { variables: target, reservedKeys },
      ]),
    ),
  };
}

function sentTexts() {
  return panel.api.post.mock.calls.flatMap(([, payload]) => payload.texts);
}

function dropPlaceholders(text: string) {
  return text.replace(/<c\d+\/>\s*/g, "");
}

function savedVariables(code = "de") {
  const call = panel.api.patch.mock.calls.find(
    ([path]) => path === `languages/${code}`,
  );
  return call?.[1].translations;
}

function lastNotification() {
  return panel.notification.open.mock.calls.at(-1)![0];
}

/**
 * Imports a fresh composable per test: `vi.resetModules()` in `beforeEach`
 * clears the cached plugin context and the global `isTranslating` state.
 */
async function translateLanguageVariables(
  language = GERMAN,
  options: TranslatorOptions = {},
) {
  const { useVariableTranslation } =
    await import("../../../src/panel/composables/variables");
  await useVariableTranslation(options).translateLanguageVariables(language);
}

async function batchTranslateLanguageVariables() {
  const { useVariableTranslation } =
    await import("../../../src/panel/composables/variables");
  await useVariableTranslation().batchTranslateLanguageVariables();
}

describe("useVariableTranslation", () => {
  beforeEach(async () => {
    vi.resetModules();
    panel = createPanelStub();
    // Vue reads `window.navigator` as it loads, so the composable loads first.
    await import("../../../src/panel/composables/variables");
    // `usePluginContext` requests the plugin context through `window.panel`.
    vi.stubGlobal("window", { panel });
    const storage = new Map<string, string>();
    vi.stubGlobal("localStorage", {
      getItem: (key: string) => storage.get(key) ?? null,
      setItem: (key: string, value: string) => storage.set(key, value),
    });
    openFieldsDialog = vi.fn(async () => ({}));
    escapeHTML = vi.fn((text: string) => text);
    pluginConfig = {};
    variablesResponses = [
      variables(SOURCE_VARIABLES, { de: TARGET_VARIABLES, fr: {} }),
    ];
    translateTexts = (texts, targetLanguage) =>
      texts.map((text) => `[${targetLanguage}]${text}`);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  describe("translateLanguageVariables", () => {
    it("translates the missing, blank, and identical variables and keeps the others", async () => {
      await translateLanguageVariables();

      expect(savedVariables()).toEqual({
        "cart.title": "[de]Shopping cart",
        "cart.items": "[de]{count} items in your cart",
        "cart.count": ["[de]No items", "[de]One item", "[de]{count} items"],
        greeting: "[de]Hello {{ name }}",
        footer: "Impressum",
        legacy: "Alt",
      });
      expect(panel.notification.success).toHaveBeenCalledWith(
        "johannschopplich.content-translator.notification.variablesTranslated",
      );
    });

    it("translates from the default language", async () => {
      panel.languages = [FRENCH, ENGLISH, GERMAN];
      panel.language = FRENCH;

      await translateLanguageVariables();

      expect(panel.api.post.mock.calls[0]![1]).toMatchObject({
        sourceLanguage: "en",
        targetLanguage: "de",
      });
    });

    it("opens the dialog with the count of pending variables and the escaped language names", async () => {
      escapeHTML.mockImplementation((text) => `escaped(${text})`);

      await translateLanguageVariables();

      expect(openFieldsDialog.mock.calls[0]![0].fields.help.text).toBe(
        'johannschopplich.content-translator.dialog.variablesHelp {"count":4,"total":5,"source":"escaped(English)","target":"escaped(Deutsch)"}',
      );
    });

    it("names a single pending variable in the singular", async () => {
      panel.t.mockImplementation(
        (key: string, data: Record<string, unknown> = {}) =>
          key.endsWith("dialog.variablesHelp")
            ? `{count} of {total} variable is missing | {count} of {total} variables are missing`.replace(
                /\{(\w+)\}/g,
                (_, name) => String(data[name]),
              )
            : key,
      );
      variablesResponses = [
        variables(
          { "cart.title": "Shopping cart", footer: "Imprint" },
          { de: { footer: "Impressum" } },
        ),
      ];

      await translateLanguageVariables();

      expect(openFieldsDialog.mock.calls[0]![0].fields.help.text).toBe(
        "1 of 2 variable is missing",
      );
    });

    it("translates the KirbyTag attributes of options.kirbyTags", async () => {
      variablesResponses = [
        variables(
          { terms: "Accept the (link: /terms text: terms)" },
          { de: {} },
        ),
      ];

      await translateLanguageVariables(GERMAN, {
        kirbyTags: { link: ["text"] },
      });

      expect(sentTexts()).toEqual(["Accept the <c0/>", "terms"]);
    });

    it("leaves out a key Kirby refuses to save in the target language", async () => {
      variablesResponses = [
        variables({ menu: "Menu", footer: "Imprint" }, { de: {} }, ["menu"]),
      ];

      await translateLanguageVariables();

      expect(sentTexts()).toEqual(["Imprint"]);
    });

    it("names the variables that were not translated", async () => {
      translateTexts = (texts) =>
        texts.map((text) => `[de]${dropPlaceholders(text)}`);

      await translateLanguageVariables();

      expect(lastNotification().message).toBe(
        'johannschopplich.content-translator.notification.variablesPartiallyTranslated {"untranslated":3,"total":4,"fields":"cart.items, cart.count, greeting"}',
      );
    });

    it("names a key holding the plural separator in full", async () => {
      variablesResponses = [
        variables(
          { "cart | items": "{count} items", "cart.title": "Shopping cart" },
          { de: {} },
        ),
      ];
      translateTexts = (texts) =>
        texts.map((text) => `[de]${dropPlaceholders(text)}`);

      await translateLanguageVariables();

      expect(lastNotification().message).toContain('"fields":"cart | items"');
    });

    it("saves nothing when no variable could be translated", async () => {
      variablesResponses = [
        variables({ "cart.items": "{count} items in your cart" }, { de: {} }),
      ];
      translateTexts = (texts) => texts.map(() => "[de]");

      await translateLanguageVariables();

      expect(panel.api.patch).not.toHaveBeenCalled();
      expect(lastNotification()).toMatchObject({
        message:
          'johannschopplich.content-translator.notification.variablesNoneTranslated {"total":1}',
        theme: "negative",
      });
    });

    it("merges onto the variables saved while the provider translates and keeps a pending variable another editor saved", async () => {
      const source = { "cart.title": "Shopping cart", footer: "Imprint" };
      variablesResponses = [
        variables(source, { de: {} }),
        variables(source, { de: { legal: "AGB", "cart.title": "Warenkorb" } }),
      ];

      await translateLanguageVariables();

      expect(savedVariables()).toEqual({
        legal: "AGB",
        "cart.title": "Warenkorb",
        footer: "[de]Imprint",
      });
      expect(panel.notification.success).toHaveBeenCalledWith(
        "johannschopplich.content-translator.notification.variablesTranslated",
      );
    });

    it("saves a pending variable another editor deleted meanwhile", async () => {
      const source = { "cart.title": "Shopping cart" };
      variablesResponses = [
        variables(source, { de: { "cart.title": "" } }),
        variables(source, { de: {} }),
      ];

      await translateLanguageVariables();

      expect(savedVariables()).toEqual({ "cart.title": "[de]Shopping cart" });
    });

    it("saves nothing and tells that every variable has its own translation when another editor saved them meanwhile", async () => {
      const source = { "cart.title": "Shopping cart" };
      variablesResponses = [
        variables(source, { de: {} }),
        variables(source, { de: { "cart.title": "Warenkorb" } }),
      ];

      await translateLanguageVariables();

      expect(sentTexts()).toEqual(["Shopping cart"]);
      expect(panel.api.patch).not.toHaveBeenCalled();
      expect(panel.notification.success).not.toHaveBeenCalled();
      expect(lastNotification()).toMatchObject({
        message:
          'johannschopplich.content-translator.notification.variablesAllTranslated {"target":"Deutsch"}',
        theme: "info",
      });
    });

    it("reloads the view of the language it saved", async () => {
      await translateLanguageVariables();

      expect(panel.view.reload).toHaveBeenCalled();
    });

    it("leaves a view the editor opened during the run as it is", async () => {
      translateTexts = (texts) => {
        panel.view.path = "languages/fr";
        return texts.map((text) => `[de]${text}`);
      };

      await translateLanguageVariables();

      expect(panel.view.reload).not.toHaveBeenCalled();
      expect(panel.view.isLoading).toBe(false);
    });

    it("keeps the view loading while it translates", async () => {
      let isLoadingDuringRun: boolean | undefined;
      translateTexts = (texts) => {
        isLoadingDuringRun = panel.view.isLoading;
        return texts.map((text) => `[de]${text}`);
      };

      await translateLanguageVariables();

      expect(isLoadingDuringRun).toBe(true);
    });

    it("translates nothing while the view is loading", async () => {
      panel.view.isLoading = true;

      await translateLanguageVariables();

      expect(openFieldsDialog).not.toHaveBeenCalled();
      expect(panel.api.post).not.toHaveBeenCalled();
    });

    it("shows error.language.notFound for a language another tab deleted", async () => {
      variablesResponses = [variables(SOURCE_VARIABLES, {})];

      await translateLanguageVariables();

      expect(panel.notification.error).toHaveBeenCalledWith(
        "error.language.notFound",
      );
    });

    it("tells that every variable has its own translation instead of opening the dialog", async () => {
      variablesResponses = [
        variables(SOURCE_VARIABLES, {
          de: {
            footer: "Impressum",
            "cart.title": "Warenkorb",
            "cart.items": "{count} Artikel",
            "cart.count": ["Keine"],
            greeting: "Hallo {{ name }}",
          },
        }),
      ];

      await translateLanguageVariables();

      expect(openFieldsDialog).not.toHaveBeenCalled();
      expect(panel.api.post).not.toHaveBeenCalled();
      expect(lastNotification()).toMatchObject({
        message:
          'johannschopplich.content-translator.notification.variablesAllTranslated {"target":"Deutsch"}',
        theme: "info",
      });
    });

    it("tells that the default language has no variables to translate", async () => {
      variablesResponses = [
        variables({ "support.url": "https://example.com/support" }, { de: {} }),
      ];

      await translateLanguageVariables();

      expect(openFieldsDialog).not.toHaveBeenCalled();
      expect(lastNotification().message).toBe(
        'johannschopplich.content-translator.notification.variablesNothingToTranslate {"source":"English"}',
      );
    });

    it("translates nothing after a cancelled dialog", async () => {
      openFieldsDialog = vi.fn(async () => undefined);

      await translateLanguageVariables();

      expect(panel.api.post).not.toHaveBeenCalled();
      expect(panel.api.patch).not.toHaveBeenCalled();
    });

    it("shows the error of a refused save", async () => {
      panel.api.patch.mockRejectedValue(
        new Error("You are not allowed to update the language"),
      );

      await translateLanguageVariables();

      expect(panel.notification.error).toHaveBeenCalledWith(
        "You are not allowed to update the language",
      );
      expect(panel.view.isLoading).toBe(false);
    });
  });

  describe("batchTranslateLanguageVariables", () => {
    beforeEach(() => {
      panel.view.path = "languages/en";
      openFieldsDialog = vi.fn(async () => ({ languages: ["de", "fr"] }));
    });

    it("opens the batch dialog with the variablesBatchHelp and the escaped name of the default language", async () => {
      escapeHTML.mockImplementation((text) => `escaped(${text})`);

      await batchTranslateLanguageVariables();

      expect(openFieldsDialog.mock.calls[0]![0].fields.languages.help).toBe(
        'johannschopplich.content-translator.dialog.variablesBatchHelp {"language":"escaped(English)"}',
      );
    });

    it("translates the variables into every selected language", async () => {
      await batchTranslateLanguageVariables();

      expect(savedVariables("de")).toMatchObject({
        "cart.title": "[de]Shopping cart",
        footer: "Impressum",
      });
      expect(savedVariables("fr")).toMatchObject({
        "cart.title": "[fr]Shopping cart",
        footer: "[fr]Imprint",
      });
      expect(panel.notification.success).toHaveBeenCalledWith(
        "johannschopplich.content-translator.notification.variablesBatchTranslated",
      );
    });

    it("reports a failed language and the variables a saved language kept untranslated", async () => {
      translateTexts = (texts, targetLanguage) => {
        if (targetLanguage === "fr") throw new Error("DeepL is unavailable");
        return texts.map((text) =>
          text.includes("in your cart")
            ? `[de]${dropPlaceholders(text)}`
            : `[de]${text}`,
        );
      };

      await batchTranslateLanguageVariables();

      expect(savedVariables("de")).toBeDefined();
      expect(savedVariables("fr")).toBeUndefined();
      expect(panel.dialog.open.mock.calls[0]![0].props).toEqual({
        message:
          'johannschopplich.content-translator.batchReport.message {"saved":1,"total":2}',
        details: [
          {
            label: "Deutsch",
            message: [
              'johannschopplich.content-translator.batchReport.variableNotTranslated {"field":"cart.items","reason":"johannschopplich.content-translator.rejection.variablePlaceholderMismatch"}',
            ],
          },
          {
            label: "Français",
            message: [
              'johannschopplich.content-translator.batchReport.failed {"message":"DeepL is unavailable"}',
            ],
          },
        ],
      });
    });

    it("reports a language another tab deleted and saves the others", async () => {
      variablesResponses = [
        variables(SOURCE_VARIABLES, { de: TARGET_VARIABLES }),
      ];

      await batchTranslateLanguageVariables();

      expect(savedVariables("de")).toBeDefined();
      expect(panel.dialog.open.mock.calls[0]![0].props.details).toEqual([
        {
          label: "Français",
          message: [
            'johannschopplich.content-translator.batchReport.failed {"message":"error.language.notFound"}',
          ],
        },
      ]);
    });

    it("ends the loading state of the view after the run", async () => {
      let isLoadingDuringRun: boolean | undefined;
      translateTexts = (texts, targetLanguage) => {
        isLoadingDuringRun = panel.view.isLoading;
        return texts.map((text) => `[${targetLanguage}]${text}`);
      };

      await batchTranslateLanguageVariables();

      expect(isLoadingDuringRun).toBe(true);
      expect(panel.view.isLoading).toBe(false);
    });

    it("translates nothing while the view is loading", async () => {
      panel.view.isLoading = true;

      await batchTranslateLanguageVariables();

      expect(openFieldsDialog).not.toHaveBeenCalled();
      expect(panel.api.post).not.toHaveBeenCalled();
    });

    it("ends the loading state of the view after a failed read", async () => {
      panel.api.get.mockImplementation(async (route: string) => {
        if (route === PLUGIN_CONTEXT_API_ROUTE) {
          return {
            config: { DeepL: { apiKey: true }, fieldTypes: [] },
            kirbyTagTypes: [],
          };
        }
        throw new Error("The server is unreachable");
      });

      await batchTranslateLanguageVariables();

      expect(panel.notification.error).toHaveBeenCalledWith(
        "The server is unreachable",
      );
      expect(panel.view.isLoading).toBe(false);
    });

    it("lists the selected languages in the Panel's language when every variable has its own translation", async () => {
      panel.translation.code = "de";
      variablesResponses = [
        variables(
          { footer: "Imprint" },
          { de: { footer: "Impressum" }, fr: { footer: "Mentions légales" } },
        ),
      ];

      await batchTranslateLanguageVariables();

      expect(lastNotification().message).toBe(
        'johannschopplich.content-translator.notification.variablesAllTranslated {"target":"Deutsch und Français"}',
      );
    });

    it("names the variables a language kept untranslated", async () => {
      translateTexts = (texts, targetLanguage) =>
        texts.map((text) =>
          targetLanguage === "fr"
            ? `[fr]${dropPlaceholders(text)}`
            : `[de]${text}`,
        );

      await batchTranslateLanguageVariables();

      expect(lastNotification().message).toBe(
        'johannschopplich.content-translator.notification.variablesBatchPartiallyTranslated {"languages":"Français (cart.items, cart.count, greeting)"}',
      );
    });
  });
});
