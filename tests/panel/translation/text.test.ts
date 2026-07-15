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
    expect(result).toBe("Hallo");
  });

  it("falls back to the source text when the strategy returns no result", async () => {
    mockApiPost.mockResolvedValueOnce({ texts: [] });

    const result = await translateText("Hello", {
      provider: "deepl",
      targetLanguage: GERMAN,
    });

    expect(result).toBe("Hello");
  });
});
