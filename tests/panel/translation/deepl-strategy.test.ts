import type { TranslationUnit } from "../../../src/panel/translation/types";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { DeepLStrategy } from "../../../src/panel/translation/strategies";

const mockApiPost = vi.fn();

vi.mock("kirbyuse", () => ({
  useApi: () => ({ post: mockApiPost }),
}));

// eslint-disable-next-line test/prefer-lowercase-title
describe("DeepLStrategy", () => {
  const defaultOptions = {
    targetLanguage: { code: "de", name: "German" },
    kirbyTags: {},
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe("batch translation", () => {
    it("calls batch endpoint with all texts", async () => {
      mockApiPost.mockResolvedValueOnce({ texts: ["Hallo", "Welt"] });

      const strategy = new DeepLStrategy();
      const units: TranslationUnit[] = [
        { text: "Hello", fieldKey: "title" },
        { text: "World", fieldKey: "subtitle" },
      ];

      const results = await strategy.execute(units, defaultOptions);

      expect(mockApiPost).toHaveBeenCalledOnce();
      expect(mockApiPost).toHaveBeenCalledWith(
        "__content-translator__/translate-batch",
        expect.objectContaining({
          texts: ["Hello", "World"],
          targetLanguage: "de",
        }),
      );
      expect(results).toEqual(["Hallo", "Welt"]);
    });

    it("preserves input order in results", async () => {
      mockApiPost.mockResolvedValueOnce({ texts: ["B1", "B2", "B3"] });

      const strategy = new DeepLStrategy();
      const units: TranslationUnit[] = [
        { text: "batch1", fieldKey: "a" },
        { text: "batch2", fieldKey: "b" },
        { text: "batch3", fieldKey: "c" },
      ];

      const results = await strategy.execute(units, defaultOptions);

      expect(results).toEqual(["B1", "B2", "B3"]);
    });
  });

  describe("source language", () => {
    it("passes sourceLanguage when provided", async () => {
      mockApiPost.mockResolvedValueOnce({ texts: ["Hallo"] });

      const strategy = new DeepLStrategy();
      const units: TranslationUnit[] = [{ text: "Hello", fieldKey: "title" }];

      await strategy.execute(units, {
        ...defaultOptions,
        sourceLanguage: { code: "en", name: "English" },
      });

      expect(mockApiPost).toHaveBeenCalledWith(
        "__content-translator__/translate-batch",
        expect.objectContaining({ sourceLanguage: "en" }),
      );
    });
  });

  describe("abort signal", () => {
    it("skips the API call when signal is pre-aborted", async () => {
      const controller = new AbortController();
      controller.abort();

      const strategy = new DeepLStrategy();
      const units: TranslationUnit[] = [
        { text: "Hello", fieldKey: "title" },
        { text: "Test", fieldKey: "cell" },
      ];

      const results = await strategy.execute(units, {
        ...defaultOptions,
        signal: controller.signal,
      });

      expect(mockApiPost).not.toHaveBeenCalled();
      expect(results).toEqual(["Hello", "Test"]);
    });
  });
});
