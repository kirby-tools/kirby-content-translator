import { describe, expect, it } from "vitest";
import { translateUnits } from "../../../src/panel/translation/dispatch";

const GERMAN = { code: "de", name: "Deutsch" };

describe("translateUnits", () => {
  it("keeps source text for units a short strategy response leaves unanswered", async () => {
    const results = await translateUnits(
      [{ text: "Hello" }, { text: "World" }],
      { execute: async () => ["Hallo"] },
      { targetLanguage: GERMAN },
    );

    expect(results).toEqual(["Hallo", "World"]);
  });
});
