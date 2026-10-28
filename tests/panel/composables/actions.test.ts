import type { PanelLanguageInfo } from "kirby-types";
import type { useContentTranslator } from "../../../src/panel/composables/translation";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { ref } from "vue";
import { useTranslationActions } from "../../../src/panel/composables/actions";

// Assigned per test and read lazily by the mocks below.
let openFieldsDialog: ReturnType<typeof vi.fn>;
let openTextDialog: ReturnType<typeof vi.fn>;
let panelLanguage: PanelLanguageInfo;
let panelView: { path: string };

const ENGLISH = {
  code: "en",
  name: "English",
  default: true,
} as PanelLanguageInfo;
const FRENCH = {
  code: "fr",
  name: "Français",
  default: false,
} as PanelLanguageInfo;
const GERMAN = {
  code: "de",
  name: "Deutsch",
  default: false,
} as PanelLanguageInfo;

vi.mock("kirbyuse", async () => {
  const { baseKirbyuseMock } = await import("../helpers/mock-kirbyuse");
  return {
    ...baseKirbyuseMock(),
    usePanel: () => ({
      t: (key: string, data?: Record<string, unknown>) =>
        data ? `${key} ${JSON.stringify(data)}` : key,
      // Stands in for the `HtmlString` Kirby renders as trusted HTML.
      th: (key: string, data?: Record<string, unknown>) =>
        `html(${key} ${JSON.stringify(data)})`,
      api: { get: async () => ({ config: { DeepL: { apiKey: true } } }) },
      events: { on: vi.fn() },
      get language() {
        return panelLanguage;
      },
      get view() {
        return panelView;
      },
      languages: [ENGLISH, FRENCH, GERMAN],
      plugins: { thirdParty: {} },
    }),
    useDialog: () => ({ openFieldsDialog, openTextDialog }),
  };
});

function createTranslator({ shouldConfirm = false } = {}) {
  return {
    shouldConfirm: ref(shouldConfirm),
    strategyName: ref<"deepl" | "ai">("ai"),
    importModelContent: vi.fn(async () => {}),
    translateModelContent: vi.fn(async () => {}),
    batchTranslateModelContent: vi.fn(async () => {}),
    getCascadeHelp: vi.fn(async () => "1 page is also translated."),
  } as unknown as ReturnType<typeof useContentTranslator>;
}

describe("useTranslationActions", () => {
  beforeEach(() => {
    const storage = new Map<string, string>();
    vi.stubGlobal("localStorage", {
      getItem: (key: string) => storage.get(key) ?? null,
      setItem: (key: string, value: string) => storage.set(key, value),
    });
    openFieldsDialog = vi.fn(async () => ({ languages: ["de"] }));
    openTextDialog = vi.fn(async () => true);
    panelLanguage = FRENCH;
    panelView = { path: "pages/example" };
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("handleTranslate translates from the sourceLanguage into panel.language", async () => {
    const translator = createTranslator();

    await useTranslationActions(translator).handleTranslate(ENGLISH);

    expect(translator.translateModelContent).toHaveBeenCalledWith(
      "pages/example",
      FRENCH,
      ENGLISH,
    );
  });

  it("handleTranslate translates the view and panel.language of the click after the editor switches both during the dialog", async () => {
    const translator = createTranslator();
    panelLanguage = { ...FRENCH };
    openFieldsDialog = vi.fn(async () => {
      panelView.path = "pages/other";
      // Kirby switches the language by mutating `panel.language` in place.
      Object.assign(panelLanguage, GERMAN);
      return {};
    });

    await useTranslationActions(translator).handleTranslate(ENGLISH);

    expect(translator.translateModelContent).toHaveBeenCalledWith(
      "pages/example",
      FRENCH,
      ENGLISH,
    );
  });

  it("handleTranslate sets strategyName to the strategyName of the dialog", async () => {
    const translator = createTranslator();

    await useTranslationActions(translator).handleTranslate();

    expect(translator.strategyName.value).toBe("deepl");
  });

  it("handleTranslate passes the getCascadeHelp text into the help field of the dialog", async () => {
    await useTranslationActions(createTranslator()).handleTranslate();

    expect(openFieldsDialog.mock.calls[0]![0].fields.help.text).toBe(
      "1 page is also translated.",
    );
  });

  it("handleTranslate opens no dialog for a cascade while panel.language is the default language", async () => {
    panelLanguage = ENGLISH;

    await useTranslationActions(createTranslator()).handleTranslate();

    expect(openFieldsDialog).not.toHaveBeenCalled();
  });

  it("handleImport imports from the sourceLanguage into the panel.languages entry of panel.language", async () => {
    const translator = createTranslator();
    panelLanguage = { ...FRENCH };

    await useTranslationActions(translator).handleImport(GERMAN);

    const [path, targetLanguage, sourceLanguage] = vi.mocked(
      translator.importModelContent,
    ).mock.calls[0]!;
    expect(path).toBe("pages/example");
    expect(targetLanguage).toBe(FRENCH);
    expect(sourceLanguage).toBe(GERMAN);
  });

  it("handleImport imports from the sourceLanguage after an accepted importConfirmation", async () => {
    const translator = createTranslator({ shouldConfirm: true });

    await useTranslationActions(translator).handleImport(GERMAN);

    expect(translator.importModelContent).toHaveBeenCalledWith(
      "pages/example",
      FRENCH,
      GERMAN,
    );
  });

  it("handleImport imports into the view and panel.language of the click after the editor switches both during the importConfirmation", async () => {
    const translator = createTranslator({ shouldConfirm: true });
    panelLanguage = { ...FRENCH };
    openTextDialog = vi.fn(async () => {
      panelView.path = "pages/other";
      Object.assign(panelLanguage, ENGLISH);
      return true;
    });

    await useTranslationActions(translator).handleImport(GERMAN);

    expect(translator.importModelContent).toHaveBeenCalledWith(
      "pages/example",
      FRENCH,
      GERMAN,
    );
  });

  it("handleImport names the sourceLanguage Deutsch in the importConfirmation", async () => {
    await useTranslationActions(
      createTranslator({ shouldConfirm: true }),
    ).handleImport(GERMAN);

    expect(openTextDialog).toHaveBeenCalledWith(
      'html(johannschopplich.content-translator.dialog.importConfirmation {"language":"Deutsch"})',
    );
  });

  it("handleImport names the default language English in the importConfirmation without a sourceLanguage", async () => {
    const translator = createTranslator({ shouldConfirm: true });

    await useTranslationActions(translator).handleImport();

    expect(openTextDialog).toHaveBeenCalledWith(
      'html(johannschopplich.content-translator.dialog.importConfirmation {"language":"English"})',
    );
  });

  it("handleImport imports nothing after a declined importConfirmation", async () => {
    const translator = createTranslator({ shouldConfirm: true });
    openTextDialog = vi.fn(async () => false);

    await useTranslationActions(translator).handleImport(GERMAN);

    expect(translator.importModelContent).not.toHaveBeenCalled();
  });

  it("handleBatchTranslate translates into the languages of the dialog", async () => {
    const translator = createTranslator();

    await useTranslationActions(translator).handleBatchTranslate();

    expect(translator.batchTranslateModelContent).toHaveBeenCalledWith(
      "pages/example",
      [GERMAN],
    );
  });

  it("handleBatchTranslate translates the view of the click after the editor opens another view during the dialog", async () => {
    const translator = createTranslator();
    openFieldsDialog = vi.fn(async () => {
      panelView.path = "pages/other";
      return { languages: ["de"] };
    });

    await useTranslationActions(translator).handleBatchTranslate();

    expect(translator.batchTranslateModelContent).toHaveBeenCalledWith(
      "pages/example",
      [GERMAN],
    );
  });

  it("handleBatchTranslate shows only the batchHelp without a cascade", async () => {
    const translator = createTranslator();
    vi.mocked(translator.getCascadeHelp).mockResolvedValue(undefined);

    await useTranslationActions(translator).handleBatchTranslate();

    expect(openFieldsDialog.mock.calls[0]![0].fields.languages.help).toBe(
      'johannschopplich.content-translator.dialog.batchHelp {"language":"English"}',
    );
  });

  it("handleBatchTranslate appends the getCascadeHelp text to the batchHelp", async () => {
    await useTranslationActions(createTranslator()).handleBatchTranslate();

    expect(openFieldsDialog.mock.calls[0]![0].fields.languages.help).toBe(
      'johannschopplich.content-translator.dialog.batchHelp {"language":"English"} 1 page is also translated.',
    );
  });

  it("handleBatchTranslate translates nothing after a cancelled dialog", async () => {
    const translator = createTranslator();
    openFieldsDialog = vi.fn(async () => undefined);

    await useTranslationActions(translator).handleBatchTranslate();

    expect(translator.batchTranslateModelContent).not.toHaveBeenCalled();
  });
});
