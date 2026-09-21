import type { PanelLanguageInfo } from "kirby-types";
import type { useContentTranslator } from "../../../src/panel/composables/translation";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { ref } from "vue";
import { useTranslationActions } from "../../../src/panel/composables/actions";

// Assigned per test and read lazily by the mocks below.
let openFieldsDialog: ReturnType<typeof vi.fn>;
let openTextDialog: ReturnType<typeof vi.fn>;

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
      language: FRENCH,
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
    // `usePluginContext` requests the plugin context through `window.panel`.
    vi.stubGlobal("window", {
      panel: {
        api: { get: async () => ({ config: { DeepL: { apiKey: true } } }) },
      },
    });
    const storage = new Map<string, string>();
    vi.stubGlobal("localStorage", {
      getItem: (key: string) => storage.get(key) ?? null,
      setItem: (key: string, value: string) => storage.set(key, value),
    });
    openFieldsDialog = vi.fn(async () => ({ languages: ["de"] }));
    openTextDialog = vi.fn(async () => true);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("handleTranslate translates from the sourceLanguage into panel.language", async () => {
    const translator = createTranslator();

    await useTranslationActions(translator).handleTranslate(ENGLISH);

    expect(translator.translateModelContent).toHaveBeenCalledWith(
      FRENCH,
      ENGLISH,
    );
  });

  it("handleTranslate sets strategyName to the strategyName of the dialog", async () => {
    const translator = createTranslator();

    await useTranslationActions(translator).handleTranslate();

    expect(translator.strategyName.value).toBe("deepl");
  });

  it("handleTranslate translates nothing before initialization resolves", async () => {
    const translator = createTranslator();
    let resolveInitialization!: () => void;
    const initialization = new Promise<void>((resolve) => {
      resolveInitialization = resolve;
    });

    const translation = useTranslationActions(
      translator,
      initialization,
    ).handleTranslate();
    await Promise.resolve();

    expect(translator.translateModelContent).not.toHaveBeenCalled();

    resolveInitialization();
    await translation;

    expect(translator.translateModelContent).toHaveBeenCalledOnce();
  });

  it("handleImport imports nothing before initialization resolves", async () => {
    const translator = createTranslator();
    let resolveInitialization!: () => void;
    const initialization = new Promise<void>((resolve) => {
      resolveInitialization = resolve;
    });

    const contentImport = useTranslationActions(
      translator,
      initialization,
    ).handleImport(GERMAN);
    await Promise.resolve();

    expect(translator.importModelContent).not.toHaveBeenCalled();

    resolveInitialization();
    await contentImport;

    expect(translator.importModelContent).toHaveBeenCalledOnce();
  });

  it("handleImport imports from the sourceLanguage after an accepted importConfirmation", async () => {
    const translator = createTranslator({ shouldConfirm: true });

    await useTranslationActions(translator).handleImport(GERMAN);

    expect(translator.importModelContent).toHaveBeenCalledWith(GERMAN);
  });

  it("handleImport names the sourceLanguage Deutsch in the importConfirmation", async () => {
    await useTranslationActions(
      createTranslator({ shouldConfirm: true }),
    ).handleImport(GERMAN);

    expect(openTextDialog).toHaveBeenCalledWith(
      'johannschopplich.content-translator.dialog.importConfirmation {"language":"Deutsch"}',
    );
  });

  it("handleImport names the default language English in the importConfirmation without a sourceLanguage", async () => {
    const translator = createTranslator({ shouldConfirm: true });

    await useTranslationActions(translator).handleImport();

    expect(openTextDialog).toHaveBeenCalledWith(
      'johannschopplich.content-translator.dialog.importConfirmation {"language":"English"}',
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

    expect(translator.batchTranslateModelContent).toHaveBeenCalledWith([
      GERMAN,
    ]);
  });

  it("handleBatchTranslate passes the getCascadeHelp text into the languages help of the dialog", async () => {
    await useTranslationActions(createTranslator()).handleBatchTranslate();

    expect(openFieldsDialog.mock.calls[0]![0].fields.languages.help).toContain(
      "1 page is also translated.",
    );
  });

  it("handleBatchTranslate translates nothing after a cancelled dialog", async () => {
    const translator = createTranslator();
    openFieldsDialog = vi.fn(async () => undefined);

    await useTranslationActions(translator).handleBatchTranslate();

    expect(translator.batchTranslateModelContent).not.toHaveBeenCalled();
  });
});
