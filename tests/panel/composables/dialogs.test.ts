import type { PanelLanguageInfo } from "kirby-types";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { useTranslationDialogs } from "../../../src/panel/composables/dialogs";
import { copilotWith } from "../helpers/mock-copilot";

// Assigned per test and read lazily by the mocks below.
let copilot: Record<string, unknown> | undefined;
let openFieldsDialog: ReturnType<typeof vi.fn>;

const LANGUAGES = [
  { code: "en", name: "English", default: true },
  { code: "fr", name: "Français", default: false },
] as PanelLanguageInfo[];

vi.mock("kirbyuse", async () => {
  const { baseKirbyuseMock } = await import("../helpers/mock-kirbyuse");
  return {
    ...baseKirbyuseMock(),
    usePanel: () => ({
      t: (key: string) => key,
      languages: LANGUAGES,
      plugins: { thirdParty: copilot ? { copilot } : {} },
    }),
    useDialog: () => ({ openFieldsDialog }),
  };
});

function openTranslationDialog(help?: string) {
  return useTranslationDialogs().openTranslationDialog(help);
}

describe("useTranslationDialogs", () => {
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
    localStorage.setItem("kirby$content-translator$preferences$provider", "ai");
    openFieldsDialog = vi.fn(async () => ({ strategyName: "deepl" }));
    copilot = undefined;
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("openTranslationDialog returns strategy deepl without opening the dialog when Copilot has no API key", async () => {
    copilot = copilotWith({ hasApiKey: false });

    expect(await openTranslationDialog()).toEqual({ strategyName: "deepl" });
    expect(openFieldsDialog).not.toHaveBeenCalled();
  });

  it("openTranslationDialog preselects strategy ai in the dialog when Copilot is ready", async () => {
    copilot = copilotWith();

    await openTranslationDialog();

    expect(openFieldsDialog).toHaveBeenCalledWith(
      expect.objectContaining({ value: { strategyName: "ai" } }),
    );
  });

  it("openTranslationDialog opens the dialog with the help as its only field when Copilot has no API key", async () => {
    copilot = copilotWith({ hasApiKey: false });

    await openTranslationDialog("1 page is also translated.");

    expect(openFieldsDialog.mock.calls[0]![0].fields).toEqual({
      help: { type: "info", text: "1 page is also translated." },
    });
  });

  it("openTranslationDialog returns strategy deepl from a dialog without a strategyName field", async () => {
    copilot = copilotWith({ hasApiKey: false });
    openFieldsDialog = vi.fn(async () => ({}));

    expect(await openTranslationDialog("1 page is also translated.")).toEqual({
      strategyName: "deepl",
    });
  });

  it("openTranslationDialog shows the help above the strategyName field when Copilot is ready", async () => {
    copilot = copilotWith();

    await openTranslationDialog("1 page is also translated.");

    expect(Object.keys(openFieldsDialog.mock.calls[0]![0].fields)).toEqual([
      "help",
      "strategyName",
    ]);
  });

  it("openTranslationDialog returns undefined after a cancelled dialog", async () => {
    copilot = copilotWith({ hasApiKey: false });
    openFieldsDialog = vi.fn(async () => undefined);

    expect(
      await openTranslationDialog("1 page is also translated."),
    ).toBeUndefined();
  });

  it("openTranslationDialog keeps the stored strategy ai after a dialog without a strategyName field", async () => {
    copilot = copilotWith({ hasApiKey: false });

    await openTranslationDialog("1 page is also translated.");

    expect(
      localStorage.getItem("kirby$content-translator$preferences$provider"),
    ).toBe("ai");
  });
});
