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

const THIRD_LANGUAGE: PanelLanguage = {
  code: "it",
  name: "Italiano",
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
 * Kirby's own rules, so a test asserts against them rather than against a
 * literal: `k-panel-notification` skips anything typed `error` or `fatal`, and
 * coerces every falsy timeout back to `KIRBY_DEFAULT_TIMEOUT`. Clearing that
 * default is what "stays on screen" has to mean – merely non-zero would pass at
 * one millisecond.
 */
const KIRBY_DEFAULT_TIMEOUT = 4000;

function lastNotification() {
  return panel.notification.open.mock.calls.at(-1)![0];
}

function staysOnScreen(notification: { type?: string; timeout?: number }) {
  return (
    notification.type !== "error" &&
    notification.type !== "fatal" &&
    (notification.timeout ?? 0) > KIRBY_DEFAULT_TIMEOUT
  );
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

    it("keeps translating the other languages when one language fails", async () => {
      const error = vi.spyOn(console, "error").mockImplementation(() => {});
      panel.api.post.mockImplementation(
        async (
          _route: string,
          payload: { texts: string[]; targetLanguage: string },
        ) => {
          if (payload.targetLanguage === "fr") throw new Error("provider down");
          return { texts: payload.texts.map((text) => `${text} (translated)`) };
        },
      );

      const translator = await createContentTranslator({
        title: false,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.batchTranslateModelContent([
        SECONDARY_LANGUAGE,
        THIRD_LANGUAGE,
      ]);

      expect(panel.api.patch).toHaveBeenCalledWith(
        "pages/example",
        expect.anything(),
        expect.objectContaining({ headers: { "x-language": "it" } }),
      );
      expect(error).toHaveBeenCalledWith(
        'Failed to translate into "fr":',
        expect.any(Error),
      );
      expect(panel.notification.success).not.toHaveBeenCalled();
      expect(panel.notification.error).toHaveBeenCalledWith(
        'johannschopplich.content-translator.notification.batchLanguagesFailed {"languages":"Français"}',
      );
      error.mockRestore();
    });

    it("reloads the view before reporting a failed language", async () => {
      const error = vi.spyOn(console, "error").mockImplementation(() => {});
      const callOrder: string[] = [];
      panel.api.post.mockImplementation(async () => {
        throw new Error("provider down");
      });
      panel.view.reload.mockImplementation(() => {
        callOrder.push("reload");
      });
      panel.notification.error.mockImplementation(() => {
        callOrder.push("error");
      });

      const translator = await createContentTranslator({
        title: false,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.batchTranslateModelContent([SECONDARY_LANGUAGE]);

      expect(callOrder).toEqual(["reload", "error"]);
      error.mockRestore();
    });

    it("names the languages whose segments kept their source text", async () => {
      const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
      panel.api.post.mockImplementation(
        async (
          _route: string,
          payload: { texts: string[]; targetLanguage: string },
        ) =>
          payload.targetLanguage === "fr"
            ? {
                texts: payload.texts,
                rejections: [{ index: 0, reason: "placeholder mismatch" }],
              }
            : { texts: payload.texts.map((text) => `${text} (translated)`) },
      );

      const translator = await createContentTranslator({
        title: false,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.batchTranslateModelContent([
        SECONDARY_LANGUAGE,
        THIRD_LANGUAGE,
      ]);

      expect(panel.notification.success).not.toHaveBeenCalled();
      const notification = lastNotification();
      expect(notification.message).toBe(
        'johannschopplich.content-translator.notification.batchPartiallyTranslated {"languages":"Français"}',
      );
      expect(staysOnScreen(notification)).toBe(true);
      warn.mockRestore();
    });

    it("reports nothing to translate when no language has translatable content", async () => {
      panel.api.get.mockResolvedValue({
        id: "example",
        title: "Example",
        content: { text: "2024" },
      });

      const translator = await createContentTranslator({
        title: false,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.batchTranslateModelContent([SECONDARY_LANGUAGE]);

      expect(panel.notification.success).not.toHaveBeenCalled();
      expect(lastNotification().message).toBe(
        "johannschopplich.content-translator.notification.nothingToTranslate",
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

  describe("rejection reporting", () => {
    it("names the field, the language and the reason in the console", async () => {
      const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
      panel.api.post.mockImplementation(
        async (_route: string, payload: { texts: string[] }) => ({
          texts: payload.texts,
          rejections: [{ index: 0, reason: "placeholder mismatch" }],
        }),
      );

      const translator = await createContentTranslator({
        title: false,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.translateModelContent(SECONDARY_LANGUAGE);

      expect(warn).toHaveBeenCalledWith(
        'Rejected "text" (fr): placeholder mismatch. Keeping source text.',
      );
      warn.mockRestore();
    });

    it("names a rejection when a later step of the same run throws", async () => {
      const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
      const error = vi.spyOn(console, "error").mockImplementation(() => {});
      // The content call is rejected, the title call succeeds – so the run
      // reaches the title patch, which is the later step that throws.
      let call = 0;
      panel.api.post.mockImplementation(
        async (_route: string, payload: { texts: string[] }) =>
          ++call === 1
            ? {
                texts: payload.texts,
                rejections: [{ index: 0, reason: "placeholder mismatch" }],
              }
            : { texts: payload.texts.map((text) => `${text} (translated)`) },
      );
      panel.api.patch.mockRejectedValue(new Error("permission denied"));

      const translator = await createContentTranslator({
        title: true,
        slug: false,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.translateModelContent(SECONDARY_LANGUAGE);

      expect(panel.notification.error).toHaveBeenCalledWith(
        "permission denied",
      );
      expect(warn).toHaveBeenCalledWith(
        'Rejected "text" (fr): placeholder mismatch. Keeping source text.',
      );
      warn.mockRestore();
      error.mockRestore();
    });

    it("warns for a surviving language when another language fails", async () => {
      const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
      const error = vi.spyOn(console, "error").mockImplementation(() => {});
      panel.api.post.mockImplementation(
        async (
          _route: string,
          payload: { texts: string[]; targetLanguage: string },
        ) => {
          if (payload.targetLanguage === "fr") throw new Error("provider down");
          return {
            texts: payload.texts,
            rejections: [{ index: 0, reason: "empty translation" }],
          };
        },
      );

      const translator = await createContentTranslator({
        title: false,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.batchTranslateModelContent([
        SECONDARY_LANGUAGE,
        THIRD_LANGUAGE,
      ]);

      expect(warn).toHaveBeenCalledWith(
        'Rejected "text" (it): empty translation. Keeping source text.',
      );
      warn.mockRestore();
      error.mockRestore();
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

    it("reports 1 of 2 text segments as keeping its source text", async () => {
      currentContent = { value: { text: "Hello", intro: "World" } };
      panel.api.post.mockImplementation(
        async (_route: string, payload: { texts: string[] }) => ({
          texts: payload.texts,
          rejections: [{ index: 0, reason: "placeholder mismatch" }],
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
      // Asserted on one notification, because the loader notification would
      // satisfy `staysOnScreen` on its own.
      const notification = lastNotification();
      expect(notification.message).toBe(
        'johannschopplich.content-translator.notification.partiallyTranslated {"untranslated":1,"total":2}',
      );
      expect(staysOnScreen(notification)).toBe(true);
    });

    it("reports every text segment as untranslated", async () => {
      currentContent = { value: { text: "Hello" } };
      panel.api.post.mockImplementation(
        async (_route: string, payload: { texts: string[] }) => ({
          texts: payload.texts,
          rejections: [{ index: 0, reason: "placeholder mismatch" }],
        }),
      );

      const translator = await createContentTranslator({
        title: false,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.translateModelContent(SECONDARY_LANGUAGE);

      expect(panel.notification.success).not.toHaveBeenCalled();
      expect(panel.notification.error).not.toHaveBeenCalled();
      const notification = lastNotification();
      expect(notification.message).toBe(
        'johannschopplich.content-translator.notification.noSegmentTranslated {"total":1}',
      );
      expect(staysOnScreen(notification)).toBe(true);
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
      expect(panel.api.patch).not.toHaveBeenCalledWith(
        "pages/example/title",
        expect.anything(),
      );
      expect(lastNotification().message).toBe(
        'johannschopplich.content-translator.notification.partiallyTranslated {"untranslated":1,"total":2}',
      );
    });

    it("keeps the target slug when the title translation fails", async () => {
      const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
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
        slug: true,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.translateModelContent(SECONDARY_LANGUAGE);

      expect(panel.api.patch).not.toHaveBeenCalledWith(
        "pages/example/slug",
        expect.anything(),
      );
      warn.mockRestore();
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
