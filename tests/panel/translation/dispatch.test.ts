import { describe, expect, it, vi } from "vitest";
import { translateUnits } from "../../../src/panel/translation/dispatch";

const GERMAN = { code: "de", name: "Deutsch" };

describe("translateUnits", () => {
  it("keeps source text for units a short strategy response leaves unanswered", async () => {
    const { texts } = await translateUnits(
      [{ text: "Hello" }, { text: "World" }],
      { execute: async () => ["Hallo"] },
      { targetLanguage: GERMAN },
    );

    expect(texts).toEqual(["Hallo", "World"]);
  });

  it("keeps source text when a strategy returns a non-string slot", async () => {
    const { texts } = await translateUnits(
      [{ text: "Hello" }, { text: "World" }],
      { execute: async () => [123, "Welt"] as unknown as string[] },
      { targetLanguage: GERMAN },
    );

    expect(texts).toEqual(["Hello", "Welt"]);
  });

  it("keeps source text when a strategy returns only whitespace", async () => {
    const { texts } = await translateUnits(
      [{ text: "Hello" }, { text: "World" }],
      // A no-break space: `isUntranslatable` would drop this as a source.
      { execute: async () => ["\u00A0 ", "Welt"] },
      { targetLanguage: GERMAN },
    );

    expect(texts).toEqual(["Hello", "Welt"]);
  });

  it("returns translatableCount 0 without reaching the strategy", async () => {
    const execute = vi.fn();

    const result = await translateUnits([{ text: "2024" }], { execute }, {
      targetLanguage: GERMAN,
    });

    expect(execute).not.toHaveBeenCalled();
    expect(result).toEqual({
      texts: ["2024"],
      translatableCount: 0,
      translatedCount: 0,
    });
  });

  it("counts only the units a strategy translated", async () => {
    const result = await translateUnits(
      [{ text: "Hello" }, { text: "World" }],
      { execute: async () => [null, "Welt"] },
      { targetLanguage: GERMAN },
    );

    expect(result.translatableCount).toBe(2);
    expect(result.translatedCount).toBe(1);
  });
});
