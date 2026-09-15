import type { PanelLanguage } from "kirby-types";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { translateText } from "../../../src/panel/translation/text";

const mockApiPost = vi.fn();

vi.mock("kirbyuse", async () => {
  const { baseKirbyuseMock } = await import("../helpers/mock-kirbyuse");
  return {
    ...baseKirbyuseMock(),
    useApi: () => ({ post: mockApiPost }),
  };
});

const GERMAN = { code: "de", name: "Deutsch" } as PanelLanguage;

describe("translateText", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("translates a single text and returns the first result", async () => {
    mockApiPost.mockResolvedValueOnce({ texts: ["Hallo"] });

    const result = await translateText("Hello", {
      strategyName: "deepl",
      targetLanguage: GERMAN,
      fieldKey: "title",
    });

    expect(mockApiPost).toHaveBeenCalledWith(
      expect.any(String),
      expect.objectContaining({ texts: ["Hello"] }),
    );
    expect(result.text).toBe("Hallo");
    expect(result.result).toEqual({
      translatableCount: 1,
      translatedCount: 1,
      rejections: [],
    });
  });

  it("returns untranslatable text without reaching the strategy", async () => {
    const result = await translateText("2024", {
      strategyName: "deepl",
      targetLanguage: GERMAN,
      fieldKey: "title",
    });

    expect(mockApiPost).not.toHaveBeenCalled();
    expect(result.text).toBe("2024");
    expect(result.result.translatableCount).toBe(0);
  });

  it("names the fieldKey in the rejection", async () => {
    mockApiPost.mockResolvedValueOnce({ texts: [] });

    const result = await translateText("Hello", {
      strategyName: "deepl",
      targetLanguage: GERMAN,
      fieldKey: "title",
    });

    expect(result.result.rejections).toEqual([
      { fieldKey: "title", reason: "missing translation" },
    ]);
  });

  it("falls back to the source text when the strategy returns no result", async () => {
    mockApiPost.mockResolvedValueOnce({ texts: [] });

    const result = await translateText("Hello", {
      strategyName: "deepl",
      targetLanguage: GERMAN,
      fieldKey: "title",
    });

    expect(result.text).toBe("Hello");
    expect(result.result).toEqual({
      translatableCount: 1,
      translatedCount: 0,
      rejections: [{ fieldKey: "title", reason: "missing translation" }],
    });
  });
});
