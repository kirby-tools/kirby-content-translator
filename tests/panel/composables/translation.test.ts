import type { PanelLanguage } from "kirby-types";
import type {
  PluginContextResponse,
  TranslatorOptions,
} from "../../../src/panel/types";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { field } from "../translation/utils";

// Assigned in `beforeEach` and read lazily by the `kirbyuse` mock below.
let panel: ReturnType<typeof createPanelStub>;
let currentContent: { value: Record<string, unknown> };
let updateContent: ReturnType<typeof vi.fn>;

vi.mock("kirbyuse", async () => {
  const { effectScope, ref } =
    await vi.importActual<typeof import("vue")>("vue");

  return {
    ref,
    effectScope,
    usePanel: () => panel,
    useApi: () => panel.api,
    useContent: () => ({ currentContent, update: updateContent }),
    useI18n: () => ({
      t: (value: unknown) => (typeof value === "string" ? value : undefined),
    }),
  };
});

const DEFAULT_LANGUAGE: PanelLanguage = {
  code: "en",
  name: "English",
  default: true,
} as PanelLanguage;

const SECONDARY_LANGUAGE: PanelLanguage = {
  code: "fr",
  name: "Français",
  default: false,
} as PanelLanguage;

function createPanelStub() {
  return {
    t: vi.fn((key: string, data?: Record<string, unknown>) =>
      data ? `${key} ${JSON.stringify(data)}` : key,
    ),
    language: SECONDARY_LANGUAGE,
    languages: [DEFAULT_LANGUAGE, SECONDARY_LANGUAGE],
    view: {
      path: "pages/example",
      title: "Example",
      isLoading: false,
      reload: vi.fn(),
    },
    api: {
      get: vi.fn(),
      patch: vi.fn(),
      post: vi.fn(),
    },
    notification: {
      open: vi.fn(),
      success: vi.fn(),
      error: vi.fn(),
    },
    events: { on: vi.fn() },
    plugins: { thirdParty: {} },
  };
}

function createPluginContext() {
  return {
    config: { DeepL: { apiKey: "test-api-key" } },
    homePageId: "home",
    errorPageId: "error",
    licenseStatus: "active",
  } as unknown as PluginContextResponse;
}

/**
 * Imports a fresh composable instance per test: `vi.resetModules()` in
 * `beforeEach` clears the module-level model data cache and the global
 * `isTranslating` state, which would otherwise leak between tests.
 */
async function createContentTranslator(options: TranslatorOptions = {}) {
  const { useContentTranslator } =
    await import("../../../src/panel/composables/translation");
  const translator = useContentTranslator();
  translator.initializeConfig(createPluginContext(), options);
  return translator;
}

/**
 * Kirby's own rule, so a test asserts against it rather than against a literal:
 * every falsy timeout is coerced back to four seconds unless the type is
 * `error`.
 */
function staysOnScreen(options: { type?: string; timeout?: number }) {
  return options.type === "error" || (options.timeout ?? 0) > 0;
}

describe("useContentTranslator", () => {
  beforeEach(() => {
    vi.resetModules();
    vi.clearAllMocks();

    panel = createPanelStub();
    currentContent = { value: { text: "Hello" } };
    updateContent = vi.fn();

    panel.api.get.mockResolvedValue({
      id: "example",
      title: "Example",
      content: { text: "Hello" },
    });
    panel.api.post.mockImplementation(
      async (_route: string, payload: { texts: string[] }) => ({
        texts: payload.texts.map((text) => `${text} (translated)`),
      }),
    );
  });

  describe("content import", () => {
    it("does not patch the slug when viewing the default language", async () => {
      panel.language = DEFAULT_LANGUAGE;
      const translator = await createContentTranslator({
        title: false,
        slug: true,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.syncModelContent(SECONDARY_LANGUAGE);

      expect(panel.api.patch).not.toHaveBeenCalled();
    });

    it("patches the slug when importing from the default language into a secondary language", async () => {
      const translator = await createContentTranslator({
        title: false,
        slug: true,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.syncModelContent(DEFAULT_LANGUAGE);

      expect(panel.api.patch).toHaveBeenCalledWith("pages/example/slug", {
        slug: "example",
      });
    });
  });

  describe("single translation", () => {
    it("reads fields at call time, translating fields assigned after initialization", async () => {
      const translator = await createContentTranslator();
      translator.fields.value = { text: field({ type: "text", name: "text" }) };

      await translator.translateModelContent(SECONDARY_LANGUAGE);

      expect(panel.api.post).toHaveBeenCalledWith(
        "__content-translator__/translate-batch",
        expect.objectContaining({ texts: ["Hello"] }),
      );
    });

    it("resets the translating state before reloading and notifies success after the reload", async () => {
      const { useTranslationState } =
        await import("../../../src/panel/composables/translation");
      const { isTranslating } = useTranslationState();

      const callOrder: string[] = [];
      let isTranslatingDuringReload: boolean | undefined;
      panel.view.reload.mockImplementation(() => {
        callOrder.push("reload");
        isTranslatingDuringReload = isTranslating.value;
      });
      panel.notification.success.mockImplementation(() => {
        callOrder.push("success");
      });

      const translator = await createContentTranslator({
        title: true,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.translateModelContent(SECONDARY_LANGUAGE);

      expect(callOrder).toEqual(["reload", "success"]);
      expect(isTranslatingDuringReload).toBe(false);
    });
  });

  describe("batch translation", () => {
    it("patches the slug when only slug translation is enabled", async () => {
      const translator = await createContentTranslator({
        title: false,
        slug: true,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.batchTranslateModelContent([SECONDARY_LANGUAGE]);

      expect(panel.api.patch).toHaveBeenCalledWith(
        "pages/example/slug",
        { slug: "example-translated" },
        { headers: { "x-language": "fr" }, silent: true },
      );
      expect(panel.api.patch).not.toHaveBeenCalledWith(
        "pages/example/title",
        expect.anything(),
        expect.anything(),
      );
    });

    it("notifies success before reloading the view", async () => {
      // Inverse of the single-translation teardown ordering.
      const callOrder: string[] = [];
      panel.view.reload.mockImplementation(() => {
        callOrder.push("reload");
      });
      panel.notification.success.mockImplementation(() => {
        callOrder.push("success");
      });

      const translator = await createContentTranslator({
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.batchTranslateModelContent([SECONDARY_LANGUAGE]);

      expect(callOrder).toEqual(["success", "reload"]);
    });
  });

  describe("translation outcome", () => {
    it("reports nothing to translate when every field value is untranslatable", async () => {
      currentContent = { value: { text: "2024" } };
      const translator = await createContentTranslator({
        title: false,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.translateModelContent(SECONDARY_LANGUAGE);

      expect(panel.api.post).not.toHaveBeenCalled();
      expect(panel.notification.success).not.toHaveBeenCalled();
      expect(panel.notification.open).toHaveBeenCalledWith(
        expect.objectContaining({
          message:
            "johannschopplich.content-translator.notification.nothingToTranslate",
        }),
      );
    });

    it("reports 1 of 2 text segments as untranslated", async () => {
      currentContent = { value: { text: "Hello", intro: "World" } };
      panel.api.post.mockImplementation(
        async (_route: string, payload: { texts: string[] }) => ({
          texts: payload.texts,
          rejectedIndexes: [0],
        }),
      );

      const translator = await createContentTranslator({
        title: false,
        fields: {
          text: field({ type: "text", name: "text" }),
          intro: field({ type: "text", name: "intro" }),
        },
      });

      await translator.translateModelContent(SECONDARY_LANGUAGE);

      expect(panel.notification.success).not.toHaveBeenCalled();
      const shown = panel.notification.open.mock.calls.at(-1)![0];
      expect(shown.message).toBe(
        'johannschopplich.content-translator.notification.partiallyTranslated {"untranslated":1,"total":2}',
      );
      expect(staysOnScreen(shown)).toBe(true);
    });

    it("keeps the nothingTranslated notification on screen", async () => {
      currentContent = { value: { text: "Hello" } };
      panel.api.post.mockImplementation(
        async (_route: string, payload: { texts: string[] }) => ({
          texts: payload.texts,
          rejectedIndexes: [0],
        }),
      );

      const translator = await createContentTranslator({
        title: false,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.translateModelContent(SECONDARY_LANGUAGE);

      expect(panel.notification.success).not.toHaveBeenCalled();
      expect(panel.notification.error).not.toHaveBeenCalled();
      const shown = panel.notification.open.mock.calls.at(-1)![0];
      expect(shown.message).toBe(
        'johannschopplich.content-translator.notification.nothingTranslated {"total":1}',
      );
      expect(staysOnScreen(shown)).toBe(true);
    });

    it("reports the title as untranslated when its translation throws", async () => {
      currentContent = { value: { text: "Hello" } };
      let call = 0;
      panel.api.post.mockImplementation(
        async (_route: string, payload: { texts: string[] }) => {
          if (++call > 1) throw new Error("provider unavailable");
          return { texts: payload.texts.map((text) => `${text} (translated)`) };
        },
      );

      const translator = await createContentTranslator({
        title: true,
        slug: false,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.translateModelContent(SECONDARY_LANGUAGE);

      expect(panel.notification.error).not.toHaveBeenCalled();
      expect(panel.api.patch).toHaveBeenCalledWith("pages/example/title", {
        title: "Example",
      });
      const shown = panel.notification.open.mock.calls.at(-1)![0];
      expect(shown.message).toBe(
        'johannschopplich.content-translator.notification.partiallyTranslated {"untranslated":1,"total":2}',
      );
    });

    it("reports nothing to import when no content field is syncable", async () => {
      panel.api.get.mockResolvedValue({
        id: "example",
        title: "Example",
        content: { untracked: "Hello" },
      });

      const translator = await createContentTranslator({
        title: false,
        slug: false,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.syncModelContent(DEFAULT_LANGUAGE);

      expect(updateContent).not.toHaveBeenCalled();
      expect(panel.notification.success).not.toHaveBeenCalled();
      expect(panel.notification.open).toHaveBeenCalledWith(
        expect.objectContaining({
          message:
            "johannschopplich.content-translator.notification.nothingToImport",
        }),
      );
    });
  });
});
