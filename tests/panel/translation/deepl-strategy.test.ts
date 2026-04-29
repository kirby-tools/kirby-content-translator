import type { TranslationUnit } from "../../../src/panel/translation/types";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { DeepLStrategy } from "../../../src/panel/translation/strategies";

const mockApiPost = vi.fn();

vi.mock("kirbyuse", () => ({
  useApi: () => ({ post: mockApiPost }),
}));

vi.mock("p-all", () => ({
  default: async <T>(
    tasks: (() => Promise<T>)[],
    _options?: { concurrency: number },
  ): Promise<T[]> => {
    const results: T[] = [];
    for (const task of tasks) results.push(await task());
    return results;
  },
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

  describe("batch mode", () => {
    it("calls batch endpoint with all batch-mode texts", async () => {
      mockApiPost.mockResolvedValueOnce({ texts: ["Hallo", "Welt"] });

      const strategy = new DeepLStrategy();
      const units: TranslationUnit[] = [
        { text: "Hello", mode: "batch", fieldKey: "title" },
        { text: "World", mode: "batch", fieldKey: "subtitle" },
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
  });

  describe("single mode", () => {
    it("calls translate endpoint for each unit", async () => {
      mockApiPost
        .mockResolvedValueOnce({ text: "Zelle1" })
        .mockResolvedValueOnce({ text: "Zelle2" });

      const strategy = new DeepLStrategy();
      const units: TranslationUnit[] = [
        { text: "Cell1", mode: "single", fieldKey: "table[0][0]" },
        { text: "Cell2", mode: "single", fieldKey: "table[0][1]" },
      ];

      const results = await strategy.execute(units, defaultOptions);

      expect(mockApiPost).toHaveBeenCalledTimes(2);
      expect(mockApiPost).toHaveBeenCalledWith(
        "__content-translator__/translate",
        expect.objectContaining({ text: "Cell1" }),
      );
      expect(results).toEqual(["Zelle1", "Zelle2"]);
    });
  });

  describe("mixed modes", () => {
    it("handles batch and single modes in single execution", async () => {
      mockApiPost
        .mockResolvedValueOnce({ texts: ["Batch"] }) // batch
        .mockResolvedValueOnce({ text: "Single" }); // single

      const strategy = new DeepLStrategy();
      const units: TranslationUnit[] = [
        { text: "Batch text", mode: "batch", fieldKey: "title" },
        { text: "Single text", mode: "single", fieldKey: "table[0][0]" },
      ];

      const results = await strategy.execute(units, defaultOptions);

      expect(mockApiPost).toHaveBeenCalledTimes(2);
      expect(results).toHaveLength(2);
    });

    it("preserves original order regardless of mode", async () => {
      mockApiPost
        .mockResolvedValueOnce({ texts: ["B1", "B2"] }) // batch (indices 0, 1)
        .mockResolvedValueOnce({ text: "S1" }); // single (index 2)

      const strategy = new DeepLStrategy();
      const units: TranslationUnit[] = [
        { text: "batch1", mode: "batch", fieldKey: "a" },
        { text: "batch2", mode: "batch", fieldKey: "b" },
        { text: "single1", mode: "single", fieldKey: "c" },
      ];

      const results = await strategy.execute(units, defaultOptions);

      expect(results).toEqual(["B1", "B2", "S1"]);
    });
  });

  describe("source language", () => {
    it("passes sourceLanguage when provided", async () => {
      mockApiPost.mockResolvedValueOnce({ texts: ["Hallo"] });

      const strategy = new DeepLStrategy();
      const units: TranslationUnit[] = [
        { text: "Hello", mode: "batch", fieldKey: "title" },
      ];

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

  describe("error handling", () => {
    it("propagates API errors", async () => {
      mockApiPost.mockRejectedValueOnce(new Error("API Error"));

      const strategy = new DeepLStrategy();
      const units: TranslationUnit[] = [
        { text: "Hello", mode: "batch", fieldKey: "title" },
      ];

      await expect(strategy.execute(units, defaultOptions)).rejects.toThrow(
        "API Error",
      );
    });
  });

  describe("abort signal", () => {
    it("skips all API calls when signal is pre-aborted", async () => {
      const controller = new AbortController();
      controller.abort();

      const strategy = new DeepLStrategy();
      const units: TranslationUnit[] = [
        { text: "Hello", mode: "batch", fieldKey: "title" },
        { text: "Test", mode: "single", fieldKey: "cell" },
      ];

      const results = await strategy.execute(units, {
        ...defaultOptions,
        signal: controller.signal,
      });

      expect(mockApiPost).not.toHaveBeenCalled();
      expect(results).toEqual(["Hello", "Test"]);
    });

    it("skips remaining modes after signal is aborted", async () => {
      const controller = new AbortController();

      // Abort after batch completes
      mockApiPost.mockImplementationOnce(async () => {
        controller.abort();
        return { texts: ["Hallo"] };
      });

      const strategy = new DeepLStrategy();
      const units: TranslationUnit[] = [
        { text: "Hello", mode: "batch", fieldKey: "title" },
        { text: "World", mode: "single", fieldKey: "cell" },
      ];

      const results = await strategy.execute(units, {
        ...defaultOptions,
        signal: controller.signal,
      });

      expect(mockApiPost).toHaveBeenCalledOnce();
      expect(results).toEqual(["Hallo", "World"]);
    });
  });
});
