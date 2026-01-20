import type { TranslationUnit } from "../../../src/panel/translation/types";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { AIStrategy } from "../../../src/panel/translation/strategies/ai";

const mockStreamText = vi.fn();
const mockLoadAISDK = vi.fn();

vi.mock("../../../src/panel/utils/copilot", () => ({
  resolveCopilot: vi.fn(() => ({
    streamText: mockStreamText,
    loadAISDK: mockLoadAISDK,
  })),
}));

// eslint-disable-next-line test/prefer-lowercase-title
describe("AIStrategy", () => {
  const defaultOptions = {
    targetLanguage: "de",
    kirbyTags: {},
  };

  beforeEach(() => {
    vi.clearAllMocks();
    mockLoadAISDK.mockResolvedValue({
      Output: {
        object: vi.fn(({ schema }) => ({ schema })),
      },
    });
  });

  describe("basic translation", () => {
    it("returns translated texts in order", async () => {
      mockStreamText.mockResolvedValueOnce({
        output: Promise.resolve({ translations: ["Hallo", "Welt"] }),
      });

      const strategy = new AIStrategy();
      const units: TranslationUnit[] = [
        { text: "Hello", mode: "batch", fieldKey: "title" },
        { text: "World", mode: "batch", fieldKey: "body" },
      ];

      const results = await strategy.execute(units, defaultOptions);

      expect(results).toEqual(["Hallo", "Welt"]);
    });

    it("calls streamText with system prompt", async () => {
      mockStreamText.mockResolvedValueOnce({
        output: Promise.resolve({ translations: ["Test"] }),
      });

      const strategy = new AIStrategy();
      const units: TranslationUnit[] = [
        { text: "Test", mode: "batch", fieldKey: "title" },
      ];

      await strategy.execute(units, defaultOptions);

      expect(mockStreamText).toHaveBeenCalledWith(
        expect.objectContaining({
          systemPrompt: expect.stringContaining("professional translator"),
        }),
      );
    });

    it("calls streamText with structured output schema", async () => {
      mockStreamText.mockResolvedValueOnce({
        output: Promise.resolve({ translations: ["Test"] }),
      });

      const strategy = new AIStrategy();
      const units: TranslationUnit[] = [
        { text: "Test", mode: "batch", fieldKey: "title" },
      ];

      await strategy.execute(units, defaultOptions);

      expect(mockStreamText).toHaveBeenCalledWith(
        expect.objectContaining({
          output: expect.objectContaining({ schema: expect.anything() }),
        }),
      );
    });
  });

  describe("prompt construction", () => {
    it("includes target language in prompt", async () => {
      mockStreamText.mockResolvedValueOnce({
        output: Promise.resolve({ translations: ["Test"] }),
      });

      const strategy = new AIStrategy();
      const units: TranslationUnit[] = [
        { text: "Test", mode: "batch", fieldKey: "title" },
      ];

      await strategy.execute(units, {
        ...defaultOptions,
        targetLanguage: "fr",
      });

      expect(mockStreamText).toHaveBeenCalledWith(
        expect.objectContaining({
          userPrompt: expect.stringContaining("to fr"),
        }),
      );
    });

    it("includes source language when provided", async () => {
      mockStreamText.mockResolvedValueOnce({
        output: Promise.resolve({ translations: ["Test"] }),
      });

      const strategy = new AIStrategy();
      const units: TranslationUnit[] = [
        { text: "Test", mode: "batch", fieldKey: "title" },
      ];

      await strategy.execute(units, {
        ...defaultOptions,
        sourceLanguage: "en",
      });

      expect(mockStreamText).toHaveBeenCalledWith(
        expect.objectContaining({
          userPrompt: expect.stringContaining("from en"),
        }),
      );
    });

    it("includes texts in XML format", async () => {
      mockStreamText.mockResolvedValueOnce({
        output: Promise.resolve({ translations: ["A", "B"] }),
      });

      const strategy = new AIStrategy();
      const units: TranslationUnit[] = [
        { text: "First", mode: "batch", fieldKey: "a" },
        { text: "Second", mode: "batch", fieldKey: "b" },
      ];

      await strategy.execute(units, defaultOptions);

      expect(mockStreamText).toHaveBeenCalledWith(
        expect.objectContaining({
          userPrompt: expect.stringMatching(/<item index="0">First<\/item>/),
        }),
      );
      expect(mockStreamText).toHaveBeenCalledWith(
        expect.objectContaining({
          userPrompt: expect.stringMatching(/<item index="1">Second<\/item>/),
        }),
      );
    });
  });

  describe("kirbyTags handling", () => {
    it("includes kirbyTags config in prompt when provided", async () => {
      mockStreamText.mockResolvedValueOnce({
        output: Promise.resolve({ translations: ["Test"] }),
      });

      const strategy = new AIStrategy();
      const units: TranslationUnit[] = [
        { text: "Test", mode: "batch", fieldKey: "title" },
      ];
      const kirbyTags = { link: ["text", "title"] };

      await strategy.execute(units, { ...defaultOptions, kirbyTags });

      expect(mockStreamText).toHaveBeenCalledWith(
        expect.objectContaining({
          userPrompt: expect.stringContaining("<kirby_tags>"),
        }),
      );
      expect(mockStreamText).toHaveBeenCalledWith(
        expect.objectContaining({
          userPrompt: expect.stringContaining('"link"'),
        }),
      );
    });

    it("includes attribute translation instruction with kirbyTags", async () => {
      mockStreamText.mockResolvedValueOnce({
        output: Promise.resolve({ translations: ["Test"] }),
      });

      const strategy = new AIStrategy();
      const units: TranslationUnit[] = [
        { text: "Test", mode: "batch", fieldKey: "title" },
      ];
      const kirbyTags = { link: ["text"] };

      await strategy.execute(units, { ...defaultOptions, kirbyTags });

      expect(mockStreamText).toHaveBeenCalledWith(
        expect.objectContaining({
          userPrompt: expect.stringContaining(
            "translate ONLY the listed attributes",
          ),
        }),
      );
    });
  });

  describe("chunking", () => {
    it("processes small batches in single chunk", async () => {
      mockStreamText.mockResolvedValueOnce({
        output: Promise.resolve({ translations: ["A", "B", "C"] }),
      });

      const strategy = new AIStrategy();
      const units: TranslationUnit[] = [
        { text: "1", mode: "batch", fieldKey: "a" },
        { text: "2", mode: "batch", fieldKey: "b" },
        { text: "3", mode: "batch", fieldKey: "c" },
      ];

      await strategy.execute(units, defaultOptions);

      expect(mockStreamText).toHaveBeenCalledOnce();
    });

    it("splits large content into multiple chunks", async () => {
      // Create text that exceeds maximum character limit
      const largeText = "x".repeat(30000);
      mockStreamText
        .mockResolvedValueOnce({
          output: Promise.resolve({ translations: ["A"] }),
        })
        .mockResolvedValueOnce({
          output: Promise.resolve({ translations: ["B"] }),
        });

      const strategy = new AIStrategy();
      const units: TranslationUnit[] = [
        { text: largeText, mode: "batch", fieldKey: "a" },
        { text: largeText, mode: "batch", fieldKey: "b" },
      ];

      const results = await strategy.execute(units, defaultOptions);

      expect(mockStreamText).toHaveBeenCalledTimes(2);
      expect(results).toEqual(["A", "B"]);
    });
  });

  describe("error handling", () => {
    it("keeps original text when chunk fails", async () => {
      const consoleSpy = vi
        .spyOn(console, "error")
        .mockImplementation(() => {});
      mockStreamText.mockRejectedValueOnce(new Error("API Error"));

      const strategy = new AIStrategy();
      const units: TranslationUnit[] = [
        { text: "Hello", mode: "batch", fieldKey: "title" },
        { text: "World", mode: "batch", fieldKey: "body" },
      ];

      const results = await strategy.execute(units, defaultOptions);

      expect(results).toEqual(["Hello", "World"]);
      expect(consoleSpy).toHaveBeenCalled();
      consoleSpy.mockRestore();
    });

    it("continues processing after chunk failure", async () => {
      const consoleSpy = vi
        .spyOn(console, "error")
        .mockImplementation(() => {});
      const largeText = "x".repeat(30000);
      mockStreamText
        .mockRejectedValueOnce(new Error("First chunk failed"))
        .mockResolvedValueOnce({
          output: Promise.resolve({ translations: ["Success"] }),
        });

      const strategy = new AIStrategy();
      const units: TranslationUnit[] = [
        { text: largeText, mode: "batch", fieldKey: "a" },
        { text: largeText, mode: "batch", fieldKey: "b" },
      ];

      const results = await strategy.execute(units, defaultOptions);

      expect(results[0]).toBe(largeText); // Original kept
      expect(results[1]).toBe("Success"); // Second chunk succeeded
      consoleSpy.mockRestore();
    });
  });

  describe("copilot availability", () => {
    it("throws when copilot is not available", async () => {
      const { resolveCopilot } =
        await import("../../../src/panel/utils/copilot");
      vi.mocked(resolveCopilot).mockReturnValueOnce(undefined);

      const strategy = new AIStrategy();
      const units: TranslationUnit[] = [
        { text: "Test", mode: "batch", fieldKey: "title" },
      ];

      await expect(strategy.execute(units, defaultOptions)).rejects.toThrow(
        "Kirby Copilot plugin is required",
      );
    });
  });

  describe("mixed modes", () => {
    beforeEach(() => {
      vi.clearAllMocks();
    });

    it("handles all translation modes uniformly", async () => {
      mockStreamText.mockResolvedValueOnce({
        output: Promise.resolve({
          translations: ["Batch", "Kirby", "Single"],
        }),
      });

      const strategy = new AIStrategy();
      const units: TranslationUnit[] = [
        { text: "batch", mode: "batch", fieldKey: "a" },
        { text: "kirby", mode: "kirbytext", fieldKey: "b" },
        { text: "single", mode: "single", fieldKey: "c" },
      ];

      const results = await strategy.execute(units, defaultOptions);

      // All modes processed in single call (no mode-based grouping)
      expect(mockStreamText).toHaveBeenCalledOnce();
      expect(results).toEqual(["Batch", "Kirby", "Single"]);
    });
  });
});
