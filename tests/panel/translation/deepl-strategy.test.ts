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
    targetLanguage: "de",
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
        { text: "Hello", mode: "batch", fieldKey: "title", fieldType: "text" },
        {
          text: "World",
          mode: "batch",
          fieldKey: "subtitle",
          fieldType: "text",
        },
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

  describe("kirbytext mode", () => {
    it("calls kirbytext endpoint for each unit", async () => {
      mockApiPost
        .mockResolvedValueOnce({ text: "Erster" })
        .mockResolvedValueOnce({ text: "Zweiter" });

      const strategy = new DeepLStrategy();
      const units: TranslationUnit[] = [
        {
          text: "First",
          mode: "kirbytext",
          fieldKey: "intro",
          fieldType: "textarea",
        },
        {
          text: "Second",
          mode: "kirbytext",
          fieldKey: "body",
          fieldType: "markdown",
        },
      ];

      const results = await strategy.execute(units, defaultOptions);

      expect(mockApiPost).toHaveBeenCalledTimes(2);
      expect(mockApiPost).toHaveBeenCalledWith(
        "__content-translator__/translate-kirbytext",
        expect.objectContaining({ text: "First" }),
      );
      expect(results).toEqual(["Erster", "Zweiter"]);
    });

    it("passes kirbyTags to kirbytext endpoint", async () => {
      mockApiPost.mockResolvedValueOnce({ text: "Translated" });

      const strategy = new DeepLStrategy();
      const units: TranslationUnit[] = [
        {
          text: "Text",
          mode: "kirbytext",
          fieldKey: "body",
          fieldType: "textarea",
        },
      ];
      const kirbyTags = { link: { attr: "href" } };

      await strategy.execute(units, { ...defaultOptions, kirbyTags });

      expect(mockApiPost).toHaveBeenCalledWith(
        "__content-translator__/translate-kirbytext",
        expect.objectContaining({ kirbyTags }),
      );
    });
  });

  describe("plain mode", () => {
    it("calls plain translate endpoint for each unit", async () => {
      mockApiPost
        .mockResolvedValueOnce({ text: "Zelle1" })
        .mockResolvedValueOnce({ text: "Zelle2" });

      const strategy = new DeepLStrategy();
      const units: TranslationUnit[] = [
        {
          text: "Cell1",
          mode: "plain",
          fieldKey: "table[0][0]",
          fieldType: "table",
        },
        {
          text: "Cell2",
          mode: "plain",
          fieldKey: "table[0][1]",
          fieldType: "table",
        },
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
    it("handles all modes in single execution", async () => {
      mockApiPost
        .mockResolvedValueOnce({ texts: ["Batch"] }) // batch
        .mockResolvedValueOnce({ text: "Kirby" }) // kirbytext
        .mockResolvedValueOnce({ text: "Plain" }); // plain

      const strategy = new DeepLStrategy();
      const units: TranslationUnit[] = [
        {
          text: "Batch text",
          mode: "batch",
          fieldKey: "title",
          fieldType: "text",
        },
        {
          text: "Kirby text",
          mode: "kirbytext",
          fieldKey: "body",
          fieldType: "textarea",
        },
        {
          text: "Plain text",
          mode: "plain",
          fieldKey: "table[0][0]",
          fieldType: "table",
        },
      ];

      const results = await strategy.execute(units, defaultOptions);

      expect(mockApiPost).toHaveBeenCalledTimes(3);
      expect(results).toHaveLength(3);
    });

    it("preserves original order regardless of mode", async () => {
      mockApiPost
        .mockResolvedValueOnce({ texts: ["B1", "B2"] }) // batch (indices 0, 2)
        .mockResolvedValueOnce({ text: "K1" }) // kirbytext (index 1)
        .mockResolvedValueOnce({ text: "P1" }); // plain (index 3)

      const strategy = new DeepLStrategy();
      const units: TranslationUnit[] = [
        { text: "batch1", mode: "batch", fieldKey: "a", fieldType: "text" },
        {
          text: "kirby1",
          mode: "kirbytext",
          fieldKey: "b",
          fieldType: "textarea",
        },
        { text: "batch2", mode: "batch", fieldKey: "c", fieldType: "text" },
        { text: "plain1", mode: "plain", fieldKey: "d", fieldType: "table" },
      ];

      const results = await strategy.execute(units, defaultOptions);

      expect(results).toEqual(["B1", "K1", "B2", "P1"]);
    });
  });

  describe("source language", () => {
    it("passes sourceLanguage when provided", async () => {
      mockApiPost.mockResolvedValueOnce({ texts: ["Hallo"] });

      const strategy = new DeepLStrategy();
      const units: TranslationUnit[] = [
        { text: "Hello", mode: "batch", fieldKey: "title", fieldType: "text" },
      ];

      await strategy.execute(units, {
        ...defaultOptions,
        sourceLanguage: "en",
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
        { text: "Hello", mode: "batch", fieldKey: "title", fieldType: "text" },
      ];

      await expect(strategy.execute(units, defaultOptions)).rejects.toThrow(
        "API Error",
      );
    });
  });

  describe("concurrency option", () => {
    it("accepts custom concurrency", () => {
      const strategy = new DeepLStrategy({ concurrency: 10 });
      expect(strategy).toBeInstanceOf(DeepLStrategy);
    });
  });
});
