import type { PanelLanguage } from "kirby-types";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { translateText } from "../../../src/panel/translation/text";

const mockApiPost = vi.fn();

vi.mock("kirbyuse", () => ({
  useApi: () => ({ post: mockApiPost }),
}));

const GERMAN = { code: "de", name: "Deutsch" } as PanelLanguage;

describe("translateText", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("translates a single text and returns the first result", async () => {
    mockApiPost.mockResolvedValueOnce({ texts: ["Hallo"] });

    const result = await translateText("Hello", {
      provider: "deepl",
      targetLanguage: GERMAN,
    });

    expect(mockApiPost).toHaveBeenCalledWith(
      expect.any(String),
      expect.objectContaining({ texts: ["Hello"] }),
    );
    expect(result.text).toBe("Hallo");
    expect(result.result).toEqual({ translatableCount: 1, translatedCount: 1 });
  });

  it("returns untranslatable text without reaching the strategy", async () => {
    const result = await translateText("2024", {
      provider: "deepl",
      targetLanguage: GERMAN,
    });

    expect(mockApiPost).not.toHaveBeenCalled();
    expect(result.text).toBe("2024");
    expect(result.result.translatableCount).toBe(0);
  });

  it("falls back to the source text when the strategy returns no result", async () => {
    mockApiPost.mockResolvedValueOnce({ texts: [] });

    const result = await translateText("Hello", {
      provider: "deepl",
      targetLanguage: GERMAN,
    });

    expect(result.text).toBe("Hello");
    expect(result.result).toEqual({ translatableCount: 1, translatedCount: 0 });
  });
});
