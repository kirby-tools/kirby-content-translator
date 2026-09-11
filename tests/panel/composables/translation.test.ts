import type { PanelLanguage } from "kirby-types";
import type { Mock } from "vitest";
import type {
  BatchStatusResponse,
  BatchWriteResponse,
  PluginConfig,
  PluginContextResponse,
  TranslatorOptions,
} from "../../../src/panel/types";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { blocksField, field } from "../translation/utils";

// Assigned in `beforeEach` and read lazily by the `kirbyuse` mock below.
let panel: ReturnType<typeof createPanelStub>;
let currentContent: { value: Record<string, unknown> };
let updateContent: ReturnType<typeof vi.fn>;
let modelData: {
  id: string;
  title: string;
  content: Record<string, unknown>;
  template: string;
  blueprint: { name: string };
};
let batchStatus: BatchStatusResponse;
// The translate route and the batch write route share `panel.api.post`.
let translateBatch: Mock<(route: string, payload: any) => Promise<unknown>>;
let batchWrite: Mock<
  (payload: Record<string, unknown>) => Promise<BatchWriteResponse>
>;

vi.mock("kirbyuse", async () => {
  const { effectScope, ref } =
    await vi.importActual<typeof import("vue")>("vue");

  return {
    ref,
    effectScope,
    isKirby5: () => true,
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
    dialog: { open: vi.fn() },
    api: {
      get: vi.fn(),
      patch: vi.fn(),
      post: vi.fn(),
    },
    notification: {
      open: vi.fn(),
      success: vi.fn(),
      error: vi.fn(),
      close: vi.fn(),
    },
    events: { on: vi.fn() },
    plugins: { thirdParty: {} },
  };
}

function createPluginContext(config: Partial<PluginConfig> = {}) {
  return {
    config: { DeepL: { apiKey: "test-api-key" }, ...config },
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
async function createContentTranslator(
  options: TranslatorOptions = {},
  config: Partial<PluginConfig> = {},
) {
  const { useContentTranslator } =
    await import("../../../src/panel/composables/translation");
  const translator = useContentTranslator();
  await translator.initializeConfig(createPluginContext(config), options);
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

function reportDialog() {
  expect(panel.dialog.open).toHaveBeenCalledTimes(1);
  const { component, props } = panel.dialog.open.mock.calls[0]![0];
  expect(component).toBe("k-error-dialog");
  return props as {
    message: string;
    details: { label: string; message: string[] }[];
  };
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

    modelData = {
      id: "example",
      title: "Example",
      content: { text: "Hello" },
      template: "article",
      blueprint: { name: "pages/article" },
    };
    batchStatus = {
      isUpdateAllowed: true,
      lockedBy: null,
      languagesWithUnsavedChanges: [],
    };
    translateBatch = vi.fn(
      async (_route: string, payload: { texts: string[] }) => ({
        texts: payload.texts.map((text) => `${text} (translated)`),
      }),
    );
    batchWrite = vi.fn(async () => ({ status: "saved" }));

    panel.api.get.mockImplementation(async (path: string) =>
      path === "__content-translator__/batch-status" ? batchStatus : modelData,
    );
    panel.api.post.mockImplementation(
      async (route: string, payload: Record<string, unknown>) =>
        route === "__content-translator__/batch-write"
          ? batchWrite(payload)
          : translateBatch(route, payload),
    );
  });

  describe("import", () => {
    it("does not patch the slug when viewing the default language", async () => {
      panel.language = DEFAULT_LANGUAGE;
      const translator = await createContentTranslator({
        title: false,
        slug: true,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.importModelContent(SECONDARY_LANGUAGE);

      expect(panel.api.patch).not.toHaveBeenCalled();
    });

    it("hands the source title to Kirby as the slug when importing from the default language into a secondary language", async () => {
      modelData.title = "Über uns";
      const translator = await createContentTranslator({
        title: false,
        slug: true,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.importModelContent(DEFAULT_LANGUAGE);

      expect(panel.api.patch).toHaveBeenCalledWith("pages/example/slug", {
        slug: "Über uns",
      });
    });

    it("refuses to import when Kirby falls back to pages/default for the article template", async () => {
      const error = vi.spyOn(console, "error").mockImplementation(() => {});
      modelData.blueprint = { name: "pages/default" };
      const translator = await createContentTranslator({ fields: {} });

      await translator.importModelContent(DEFAULT_LANGUAGE);

      expect(panel.notification.error).toHaveBeenCalledWith(
        "johannschopplich.content-translator.error.unresolvedFields",
      );
      expect(panel.notification.success).not.toHaveBeenCalled();
      expect(updateContent).not.toHaveBeenCalled();
      error.mockRestore();
    });
  });

  describe("single-language translation", () => {
    it("reads fields at call time, translating fields assigned after initialization", async () => {
      const translator = await createContentTranslator();
      translator.fields.value = { text: field({ type: "text", name: "text" }) };

      await translator.translateModelContent(SECONDARY_LANGUAGE);

      expect(panel.api.post).toHaveBeenCalledWith(
        "__content-translator__/translate-batch",
        expect.objectContaining({ texts: ["Hello"] }),
      );
    });

    it("hands the translated title to Kirby as the slug", async () => {
      const translator = await createContentTranslator({
        title: false,
        slug: true,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.translateModelContent(SECONDARY_LANGUAGE);

      expect(panel.api.patch).toHaveBeenCalledWith("pages/example/slug", {
        slug: "Example (translated)",
      });
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

    it("refuses to translate when Kirby falls back to pages/default for the article template", async () => {
      const error = vi.spyOn(console, "error").mockImplementation(() => {});
      modelData.blueprint = { name: "pages/default" };
      const translator = await createContentTranslator({ fields: {} });

      await translator.translateModelContent(SECONDARY_LANGUAGE);

      expect(panel.notification.error).toHaveBeenCalledWith(
        "johannschopplich.content-translator.error.unresolvedFields",
      );
      expect(translateBatch).not.toHaveBeenCalled();
      expect(updateContent).not.toHaveBeenCalled();
      expect(panel.view.isLoading).toBe(false);
      error.mockRestore();
    });
  });

  describe("batch translation", () => {
    it("sends the translated title as the slug with slug true and title false", async () => {
      const translator = await createContentTranslator({
        title: false,
        slug: true,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.batchTranslateModelContent([SECONDARY_LANGUAGE]);

      expect(batchWrite).toHaveBeenCalledWith({
        path: "pages/example",
        language: "fr",
        content: { text: "Hello (translated)" },
        title: undefined,
        slug: "Example (translated)",
      });
      expect(panel.api.patch).not.toHaveBeenCalled();
    });

    it("aborts before translating while another user edits the content", async () => {
      batchStatus.lockedBy = "Colleague";

      const translator = await createContentTranslator({
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.batchTranslateModelContent([
        SECONDARY_LANGUAGE,
        THIRD_LANGUAGE,
      ]);

      expect(translateBatch).not.toHaveBeenCalled();
      expect(batchWrite).not.toHaveBeenCalled();
      expect(panel.notification.error).toHaveBeenCalledWith(
        'johannschopplich.content-translator.error.batchLocked {"user":"Colleague"}',
      );
      expect(panel.view.isLoading).toBe(false);
    });

    it("aborts before translating without the update permission", async () => {
      batchStatus.isUpdateAllowed = false;

      const translator = await createContentTranslator({
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.batchTranslateModelContent([SECONDARY_LANGUAGE]);

      expect(translateBatch).not.toHaveBeenCalled();
      expect(batchWrite).not.toHaveBeenCalled();
      expect(panel.notification.error).toHaveBeenCalledWith(
        "johannschopplich.content-translator.error.batchForbidden",
      );
      expect(panel.view.isLoading).toBe(false);
    });

    it("reports a language with unsaved changes in the dialog without translating it", async () => {
      batchStatus.languagesWithUnsavedChanges = ["fr"];

      const translator = await createContentTranslator({
        title: false,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.batchTranslateModelContent([
        SECONDARY_LANGUAGE,
        THIRD_LANGUAGE,
      ]);

      expect(translateBatch).toHaveBeenCalledTimes(1);
      expect(translateBatch).toHaveBeenCalledWith(
        "__content-translator__/translate-batch",
        expect.objectContaining({ targetLanguage: "it" }),
      );
      expect(batchWrite).toHaveBeenCalledTimes(1);
      expect(reportDialog().details).toEqual([
        {
          label: "Français",
          message: [
            "johannschopplich.content-translator.batchReport.unsavedChanges",
          ],
        },
      ]);
    });

    it("shows no progress when every language has unsaved changes", async () => {
      batchStatus.languagesWithUnsavedChanges = ["fr"];

      const translator = await createContentTranslator({
        title: false,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.batchTranslateModelContent([SECONDARY_LANGUAGE]);

      expect(panel.notification.open).not.toHaveBeenCalledWith(
        expect.objectContaining({
          message: expect.stringContaining("notification.batchTranslating"),
        }),
      );
      expect(reportDialog().details).toEqual([
        {
          label: "Français",
          message: [
            "johannschopplich.content-translator.batchReport.unsavedChanges",
          ],
        },
      ]);
    });

    it("keeps translating the other languages when one language fails", async () => {
      const error = vi.spyOn(console, "error").mockImplementation(() => {});
      translateBatch.mockImplementation(
        async (
          _route: string,
          payload: { texts: string[]; targetLanguage: string },
        ) => {
          if (payload.targetLanguage === "fr") {
            throw new Error(
              "DeepL API quota exceeded. The character limit has been reached.",
            );
          }
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

      expect(batchWrite).toHaveBeenCalledWith(
        expect.objectContaining({ language: "it" }),
      );
      expect(error).toHaveBeenCalledWith(
        'Failed to translate into "fr":',
        expect.any(Error),
      );
      expect(panel.notification.success).not.toHaveBeenCalled();
      expect(reportDialog()).toEqual({
        message:
          'johannschopplich.content-translator.batchReport.message {"saved":1,"total":2}',
        details: [
          {
            label: "Français",
            message: [
              'johannschopplich.content-translator.batchReport.failed {"message":"DeepL API quota exceeded. The character limit has been reached."}',
            ],
          },
        ],
      });
      error.mockRestore();
    });

    it("reports the fields that kept their source text next to a failed language", async () => {
      const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
      const error = vi.spyOn(console, "error").mockImplementation(() => {});
      translateBatch.mockImplementation(
        async (
          _route: string,
          payload: { texts: string[]; targetLanguage: string },
        ) => {
          if (payload.targetLanguage === "fr") throw new Error("provider down");
          return {
            texts: payload.texts,
            rejections: [{ index: 0, reason: "placeholder mismatch" }],
          };
        },
      );

      const translator = await createContentTranslator({
        title: false,
        fields: {
          text: field({ type: "textarea", name: "text", label: "Body" }),
        },
      });

      await translator.batchTranslateModelContent([
        SECONDARY_LANGUAGE,
        THIRD_LANGUAGE,
      ]);

      expect(reportDialog().details).toEqual([
        {
          label: "Français",
          message: [
            'johannschopplich.content-translator.batchReport.failed {"message":"provider down"}',
          ],
        },
        {
          label: "Italiano",
          message: [
            'johannschopplich.content-translator.batchReport.keptSource {"field":"Body","reason":"johannschopplich.content-translator.rejection.placeholderMismatch"}',
          ],
        },
      ]);
      warn.mockRestore();
      error.mockRestore();
    });

    it("names a field nested in a block by the label of its blocks field", async () => {
      const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
      modelData.content = {
        text: "Teaser",
        body: [
          {
            id: "1",
            type: "text",
            isHidden: false,
            content: { text: "Nested" },
          },
        ],
      };
      translateBatch.mockImplementation(
        async (_route: string, payload: { texts: string[] }) => ({
          texts: payload.texts,
          rejections: [
            {
              index: payload.texts.indexOf("Nested"),
              reason: "placeholder mismatch",
            },
          ],
        }),
      );

      const translator = await createContentTranslator({
        title: false,
        fields: {
          text: field({ type: "text", name: "text", label: "Teaser" }),
          body: {
            ...blocksField("body", {
              text: { text: field({ type: "text", name: "text" }) },
            }),
            label: "Body",
          },
        },
      });

      await translator.batchTranslateModelContent([SECONDARY_LANGUAGE]);

      expect(lastNotification().message).toBe(
        'johannschopplich.content-translator.notification.batchPartiallyTranslated {"languages":"Français (Body)"}',
      );
      warn.mockRestore();
    });

    it("reports a failed title request in the dialog", async () => {
      const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
      translateBatch.mockImplementation(
        async (_route: string, payload: { texts: string[] }) => {
          if (payload.texts.includes("Example")) {
            throw new Error(
              "DeepL API quota exceeded. The character limit has been reached.",
            );
          }
          return { texts: payload.texts.map((text) => `${text} (translated)`) };
        },
      );

      const translator = await createContentTranslator({
        title: true,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.batchTranslateModelContent([SECONDARY_LANGUAGE]);

      expect(reportDialog().details).toEqual([
        {
          label: "Français",
          message: [
            'johannschopplich.content-translator.batchReport.keptSource {"field":"title","reason":"DeepL API quota exceeded. The character limit has been reached."}',
          ],
        },
      ]);
      warn.mockRestore();
    });

    it("names each language with the fields that kept their source text in a notice", async () => {
      const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
      translateBatch.mockImplementation(
        async (_route: string, payload: { texts: string[] }) => ({
          texts: payload.texts,
          rejections: [{ index: 0, reason: "placeholder mismatch" }],
        }),
      );

      const translator = await createContentTranslator({
        title: false,
        fields: { text: field({ type: "text", name: "text", label: "Body" }) },
      });

      await translator.batchTranslateModelContent([
        SECONDARY_LANGUAGE,
        THIRD_LANGUAGE,
      ]);

      expect(panel.dialog.open).not.toHaveBeenCalled();
      const notification = lastNotification();
      expect(notification.message).toBe(
        'johannschopplich.content-translator.notification.batchPartiallyTranslated {"languages":"Français (Body), Italiano (Body)"}',
      );
      expect(staysOnScreen(notification)).toBe(true);
      warn.mockRestore();
    });

    it("names the first three fields of a language and counts the rest", async () => {
      const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
      const names = ["a", "b", "c", "d", "e"];
      modelData.content = Object.fromEntries(names.map((name) => [name, name]));
      translateBatch.mockImplementation(
        async (_route: string, payload: { texts: string[] }) => ({
          texts: payload.texts,
          rejections: payload.texts.map((_, index) => ({
            index,
            reason: "placeholder mismatch",
          })),
        }),
      );

      const translator = await createContentTranslator({
        title: false,
        fields: Object.fromEntries(
          names.map((name) => [
            name,
            field({ type: "text", name, label: name.toUpperCase() }),
          ]),
        ),
      });

      await translator.batchTranslateModelContent([SECONDARY_LANGUAGE]);

      expect(lastNotification().message).toBe(
        'johannschopplich.content-translator.notification.batchPartiallyTranslated {"languages":"Français (johannschopplich.content-translator.notification.andMore {\\"fields\\":\\"A, B, C\\",\\"count\\":2})"}',
      );
      warn.mockRestore();
    });

    it("names each invalid field and the slugError in the dialog", async () => {
      batchWrite.mockResolvedValue({
        status: "saved",
        invalidFields: {
          teaser: {
            label: "Teaser",
            message: { maxlength: "Please enter a shorter value." },
          },
        },
        slugError: "The URL appendix exists",
      });

      const translator = await createContentTranslator({
        title: false,
        slug: true,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.batchTranslateModelContent([SECONDARY_LANGUAGE]);

      expect(panel.notification.success).not.toHaveBeenCalled();
      expect(reportDialog().details).toEqual([
        {
          label: "Français",
          message: [
            'johannschopplich.content-translator.batchReport.notChanged {"field":"slug","message":"The URL appendix exists"}',
            'johannschopplich.content-translator.batchReport.invalidField {"field":"Teaser","message":"Please enter a shorter value."}',
          ],
        },
      ]);
    });

    it("names the user who started editing while the batch ran", async () => {
      batchWrite.mockResolvedValue({ status: "locked", lockedBy: "Colleague" });

      const translator = await createContentTranslator({
        title: false,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.batchTranslateModelContent([SECONDARY_LANGUAGE]);

      expect(reportDialog()).toEqual({
        message:
          'johannschopplich.content-translator.batchReport.message {"saved":0,"total":1}',
        details: [
          {
            label: "Français",
            message: [
              'johannschopplich.content-translator.batchReport.locked {"user":"Colleague"}',
            ],
          },
        ],
      });
    });

    it("starts no further language once another user starts editing", async () => {
      batchWrite.mockResolvedValueOnce({
        status: "locked",
        lockedBy: "Colleague",
      });

      const translator = await createContentTranslator(
        {
          title: false,
          fields: { text: field({ type: "text", name: "text" }) },
        },
        { batchConcurrency: 1 },
      );

      await translator.batchTranslateModelContent([
        SECONDARY_LANGUAGE,
        THIRD_LANGUAGE,
      ]);

      expect(translateBatch).toHaveBeenCalledTimes(1);
      expect(reportDialog().details).toEqual([
        {
          label: "Français",
          message: [
            'johannschopplich.content-translator.batchReport.locked {"user":"Colleague"}',
          ],
        },
        {
          label: "Italiano",
          message: [
            'johannschopplich.content-translator.batchReport.notStarted {"user":"Colleague"}',
          ],
        },
      ]);
    });

    it("reloads the view before reporting a failed language", async () => {
      const error = vi.spyOn(console, "error").mockImplementation(() => {});
      const callOrder: string[] = [];
      translateBatch.mockImplementation(async () => {
        throw new Error("provider down");
      });
      panel.view.reload.mockImplementation(() => {
        callOrder.push("reload");
      });
      panel.dialog.open.mockImplementation(() => {
        callOrder.push("dialog");
      });

      const translator = await createContentTranslator({
        title: false,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.batchTranslateModelContent([SECONDARY_LANGUAGE]);

      expect(callOrder).toEqual(["reload", "dialog"]);
      error.mockRestore();
    });

    it("reports nothing to translate without opening the report dialog", async () => {
      modelData.content = { text: "2024" };

      const translator = await createContentTranslator({
        title: false,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.batchTranslateModelContent([SECONDARY_LANGUAGE]);

      expect(panel.notification.success).not.toHaveBeenCalled();
      expect(panel.dialog.open).not.toHaveBeenCalled();
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
      expect(panel.dialog.open).not.toHaveBeenCalled();
    });

    it("refuses to batch-translate when Kirby falls back to pages/default for the article template", async () => {
      const error = vi.spyOn(console, "error").mockImplementation(() => {});
      modelData.blueprint = { name: "pages/default" };
      const translator = await createContentTranslator({ fields: {} });

      await translator.batchTranslateModelContent([SECONDARY_LANGUAGE]);

      expect(panel.notification.error).toHaveBeenCalledWith(
        "johannschopplich.content-translator.error.unresolvedFields",
      );
      expect(translateBatch).not.toHaveBeenCalled();
      expect(batchWrite).not.toHaveBeenCalled();
      error.mockRestore();
    });

    it("translates the title of a page whose own blueprint has no fields while its content still holds a text value", async () => {
      modelData.content = { title: "Example", text: "Hello" };

      const translator = await createContentTranslator({
        title: true,
        fields: {},
      });

      await translator.batchTranslateModelContent([SECONDARY_LANGUAGE]);

      expect(panel.notification.error).not.toHaveBeenCalled();
      expect(batchWrite).toHaveBeenCalledWith(
        expect.objectContaining({
          language: "fr",
          title: "Example (translated)",
        }),
      );
    });

    it("translates the title of a page with the default template and no fields", async () => {
      modelData.content = { title: "Example" };
      modelData.template = "default";
      modelData.blueprint = { name: "pages/default" };

      const translator = await createContentTranslator({
        title: true,
        fields: {},
      });

      await translator.batchTranslateModelContent([SECONDARY_LANGUAGE]);

      expect(panel.notification.error).not.toHaveBeenCalled();
      expect(batchWrite).toHaveBeenCalledWith(
        expect.objectContaining({
          language: "fr",
          title: "Example (translated)",
        }),
      );
    });
  });

  describe("rejection reporting", () => {
    it("names the field, the language and the reason in the console", async () => {
      const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
      translateBatch.mockImplementation(
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
      translateBatch.mockImplementation(
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
      translateBatch.mockImplementation(
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

    it("lists fieldTypes in the notice when no field is eligible", async () => {
      currentContent = { value: { price: "49" } };
      const translator = await createContentTranslator({
        title: false,
        fieldTypes: ["text", "textarea"],
        fields: { price: field({ type: "number", name: "price" }) },
      });

      await translator.translateModelContent(SECONDARY_LANGUAGE);

      expect(panel.api.post).not.toHaveBeenCalled();
      expect(lastNotification().message).toBe(
        'johannschopplich.content-translator.notification.noEligibleFields {"fieldTypes":"text, textarea"}',
      );
    });

    it("counts a field in excludeFields as not eligible", async () => {
      const translator = await createContentTranslator({
        title: false,
        excludeFields: ["text"],
        fieldTypes: ["text"],
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.translateModelContent(SECONDARY_LANGUAGE);

      expect(lastNotification().message).toBe(
        'johannschopplich.content-translator.notification.noEligibleFields {"fieldTypes":"text"}',
      );
    });

    it("reports 1 of 2 translation units as keeping its source text in Body", async () => {
      currentContent = { value: { text: "Hello", intro: "World" } };
      translateBatch.mockImplementation(
        async (_route: string, payload: { texts: string[] }) => ({
          texts: payload.texts,
          rejections: [{ index: 0, reason: "placeholder mismatch" }],
        }),
      );

      const translator = await createContentTranslator({
        title: false,
        fields: {
          text: field({ type: "text", name: "text", label: "Body" }),
          intro: field({ type: "text", name: "intro", label: "Intro" }),
        },
      });

      await translator.translateModelContent(SECONDARY_LANGUAGE);

      expect(panel.notification.success).not.toHaveBeenCalled();
      const notification = lastNotification();
      expect(notification.message).toBe(
        'johannschopplich.content-translator.notification.partiallyTranslated {"untranslated":1,"total":2,"fields":"Body"}',
      );
      expect(staysOnScreen(notification)).toBe(true);
    });

    it("reports every translation unit as untranslated", async () => {
      currentContent = { value: { text: "Hello" } };
      translateBatch.mockImplementation(
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
      translateBatch.mockImplementation(
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
        'johannschopplich.content-translator.notification.partiallyTranslated {"untranslated":1,"total":2,"fields":"title"}',
      );
    });

    it("keeps the target slug when the title translation fails", async () => {
      const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
      currentContent = { value: { text: "Hello" } };
      let call = 0;
      translateBatch.mockImplementation(
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

    it("reports nothing to import when no content field is eligible", async () => {
      modelData.content = { untracked: "Hello" };

      const translator = await createContentTranslator({
        title: false,
        slug: false,
        fields: { text: field({ type: "text", name: "text" }) },
      });

      await translator.importModelContent(DEFAULT_LANGUAGE);

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
