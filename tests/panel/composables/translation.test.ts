import type { PanelLanguage } from "kirby-types";
import type {
  PluginConfig,
  PluginContextResponse,
  TranslatorOptions,
} from "../../../src/panel/types";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { field } from "../translation/utils";

// Assigned in `beforeEach` and read lazily by the `kirbyuse` mock below
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
    t: vi.fn((key: string) => key),
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

async function resolveAvailability(config: PluginConfig) {
  const { getProviderAvailability } =
    await import("../../../src/panel/composables/translation");
  return getProviderAvailability(config);
}

function installCopilot() {
  panel.plugins.thirdParty = { copilot: { apiVersion: 2 } };
}

describe("getProviderAvailability", () => {
  beforeEach(() => {
    vi.resetModules();
    panel = createPanelStub();
  });

  it("treats a custom strategy as a usable backend without a DeepL key", async () => {
    const availability = await resolveAvailability({ strategy: "custom" });

    expect(availability.hasAnyProvider).toBe(true);
    expect(availability.hasDefaultProvider).toBe(true);
    expect(availability.hasMultipleProviders).toBe(false);
  });

  it("requires a DeepL API key when the strategy resolves to DeepL", async () => {
    expect((await resolveAvailability({ strategy: "deepl" })).hasAnyProvider)
      .toBe(false);
    expect(
      (await resolveAvailability({
        strategy: "deepl",
        DeepL: { apiKey: true },
      })).hasAnyProvider,
    ).toBe(true);
  });

  it("offers Copilot alone when the strategy resolves to AI", async () => {
    installCopilot();

    const availability = await resolveAvailability({
      strategy: "ai",
      DeepL: { apiKey: true },
    });

    expect(availability.hasAnyProvider).toBe(true);
    expect(availability.hasDefaultProvider).toBe(false);
    expect(availability.hasMultipleProviders).toBe(false);
  });

  it("offers both providers when a usable backend and Copilot are available", async () => {
    installCopilot();

    const availability = await resolveAvailability({
      strategy: "deepl",
      DeepL: { apiKey: true },
    });

    expect(availability.hasMultipleProviders).toBe(true);
  });
});

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
      // Inverse of the single-translation teardown ordering
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
});
