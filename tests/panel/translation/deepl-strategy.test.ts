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

    it("reports a whitespace-only translation as an empty translation", async () => {
      mockApiPost.mockResolvedValueOnce({ texts: ["\u00A0 ", "Welt"] });

      const strategy = new DeepLStrategy();
      const units: TranslationUnit[] = [
        { text: "Hello", fieldKey: "title" },
        { text: "World", fieldKey: "subtitle" },
      ];

      const results = await strategy.execute(units, defaultOptions);

      expect(results).toEqual([{ reason: "empty translation" }, "Welt"]);
    });

    it("carries the reason the route names for a rejected index", async () => {
      mockApiPost.mockResolvedValueOnce({
        texts: ["Hello", "Welt"],
        rejected: [{ index: 0, reason: "placeholder count mismatch" }],
      });

      const strategy = new DeepLStrategy();
      const units: TranslationUnit[] = [
        { text: "Hello", fieldKey: "title" },
        { text: "World", fieldKey: "subtitle" },
      ];

      const results = await strategy.execute(units, defaultOptions);

      expect(results).toEqual([
        { reason: "placeholder count mismatch" },
        "Welt",
      ]);
    });

    it("falls back to a bare reason when the route sends only rejectedIndexes", async () => {
      mockApiPost.mockResolvedValueOnce({
        texts: ["Hello", "Welt"],
        rejectedIndexes: [0],
      });

      const strategy = new DeepLStrategy();
      const units: TranslationUnit[] = [
        { text: "Hello", fieldKey: "title" },
        { text: "World", fieldKey: "subtitle" },
      ];

      const results = await strategy.execute(units, defaultOptions);

      expect(results).toEqual([{ reason: "no usable translation" }, "Welt"]);
    });

    it("returns null for units the response leaves unanswered", async () => {
      mockApiPost.mockResolvedValueOnce({ texts: ["Hallo"] });

      const strategy = new DeepLStrategy();
      const units: TranslationUnit[] = [
        { text: "Hello", fieldKey: "title" },
        { text: "World", fieldKey: "subtitle" },
      ];

      const results = await strategy.execute(units, defaultOptions);

      expect(results).toEqual(["Hallo", null]);
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
});
