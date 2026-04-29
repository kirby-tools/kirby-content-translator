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
    targetLanguage: { code: "de", name: "German" },
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
        targetLanguage: { code: "fr", name: "French" },
      });

      expect(mockStreamText).toHaveBeenCalledWith(
        expect.objectContaining({
          userPrompt: expect.stringContaining("to French"),
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
        sourceLanguage: { code: "en", name: "English" },
      });

      expect(mockStreamText).toHaveBeenCalledWith(
        expect.objectContaining({
          userPrompt: expect.stringContaining("from English"),
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
      // Create text that exceeds maximum character limit (100k)
      const largeText = "x".repeat(60000);
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
      const largeText = "x".repeat(60000);
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

  describe("placeholder validation", () => {
    it("keeps source text and warns when placeholder count drops", async () => {
      const warnSpy = vi.spyOn(console, "warn").mockImplementation(() => {});
      mockStreamText.mockResolvedValueOnce({
        output: Promise.resolve({ translations: ["Click here"] }),
      });

      const strategy = new AIStrategy();
      const units: TranslationUnit[] = [
        { text: "Click <c0/> now", mode: "batch", fieldKey: "body" },
      ];

      const results = await strategy.execute(units, defaultOptions);

      expect(results).toEqual(["Click <c0/> now"]);
      expect(warnSpy).toHaveBeenCalledWith(expect.stringContaining("body"));
      warnSpy.mockRestore();
    });

    it("accepts translation when placeholder count matches", async () => {
      const warnSpy = vi.spyOn(console, "warn").mockImplementation(() => {});
      mockStreamText.mockResolvedValueOnce({
        output: Promise.resolve({ translations: ["Klick <c0/> jetzt"] }),
      });

      const strategy = new AIStrategy();
      const units: TranslationUnit[] = [
        { text: "Click <c0/> now", mode: "batch", fieldKey: "body" },
      ];

      const results = await strategy.execute(units, defaultOptions);

      expect(results).toEqual(["Klick <c0/> jetzt"]);
      expect(warnSpy).not.toHaveBeenCalled();
      warnSpy.mockRestore();
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
    it("handles batch and single modes uniformly", async () => {
      mockStreamText.mockResolvedValueOnce({
        output: Promise.resolve({
          translations: ["Batch", "Single"],
        }),
      });

      const strategy = new AIStrategy();
      const units: TranslationUnit[] = [
        { text: "batch", mode: "batch", fieldKey: "a" },
        { text: "single", mode: "single", fieldKey: "b" },
      ];

      const results = await strategy.execute(units, defaultOptions);

      // All modes processed in single call (no mode-based grouping)
      expect(mockStreamText).toHaveBeenCalledOnce();
      expect(results).toEqual(["Batch", "Single"]);
    });
  });

  describe("abort signal", () => {
    it("skips all chunks when signal is pre-aborted", async () => {
      const controller = new AbortController();
      controller.abort();

      const strategy = new AIStrategy();
      const units: TranslationUnit[] = [
        { text: "Hello", mode: "batch", fieldKey: "title" },
        { text: "World", mode: "batch", fieldKey: "body" },
      ];

      const results = await strategy.execute(units, {
        ...defaultOptions,
        signal: controller.signal,
      });

      expect(mockStreamText).not.toHaveBeenCalled();
      expect(results).toEqual(["Hello", "World"]);
    });

    it("stops processing remaining chunks after abort", async () => {
      const controller = new AbortController();
      const largeText = "x".repeat(60000);

      // First chunk succeeds, then abort
      mockStreamText.mockImplementationOnce(async () => {
        controller.abort();
        return { output: Promise.resolve({ translations: ["First"] }) };
      });

      const strategy = new AIStrategy();
      const units: TranslationUnit[] = [
        { text: largeText, mode: "batch", fieldKey: "a" },
        { text: largeText, mode: "batch", fieldKey: "b" },
      ];

      const results = await strategy.execute(units, {
        ...defaultOptions,
        signal: controller.signal,
      });

      expect(mockStreamText).toHaveBeenCalledOnce();
      expect(results[0]).toBe("First");
      expect(results[1]).toBe(largeText); // Original kept
    });
  });
});
