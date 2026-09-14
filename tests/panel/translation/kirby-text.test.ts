import { readdirSync, readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, it } from "vitest";
import {
  PLACEHOLDER_PATTERN,
  splitKirbyText,
} from "../../../src/panel/translation/kirby-text";

interface ConformanceCase {
  description?: string;
  input: string;
  kirbyTags: Record<string, string[]>;
  expectedUnitTexts: string[];
  expectedPlaceholderCount: number;
  restoredWith: string[];
  expectedRestore: string;
}

const FIXTURES_DIR = join(import.meta.dirname, "../../fixtures/kirby-text");

const conformanceCases = readdirSync(FIXTURES_DIR)
  .filter((file) => file.endsWith(".json") && file !== "schema.json")
  .map((file) => ({
    name: file.replace(/\.json$/, ""),
    ...(JSON.parse(
      readFileSync(join(FIXTURES_DIR, file), "utf8"),
    ) as ConformanceCase),
  }));

describe("splitKirbyText", () => {
  // Shared with `KirbyTextSplitTest.php` – drift fails here first.
  describe("conformance corpus", () => {
    it.each(conformanceCases)(
      "$name",
      ({
        input,
        kirbyTags,
        expectedUnitTexts,
        expectedPlaceholderCount,
        restoredWith,
        expectedRestore,
      }) => {
        const { unitTexts, restore } = splitKirbyText(input, kirbyTags);

        expect(unitTexts).toEqual(expectedUnitTexts);
        expect(unitTexts[0]!.match(PLACEHOLDER_PATTERN) ?? []).toHaveLength(
          expectedPlaceholderCount,
        );
        expect(restore(restoredWith)).toBe(expectedRestore);
      },
    );
  });

  describe("restore validation", () => {
    it.each([
      {
        name: "fewer",
        input: (texts: string[]) => [texts[0]!],
        expectedGot: 1,
      },
      {
        name: "more",
        input: (texts: string[]) => [...texts, "extra"],
        expectedGot: 3,
      },
    ])(
      "throws when restore receives $name translations than unit texts",
      ({ input, expectedGot }) => {
        const text = "(link: /a text: site)";
        const { unitTexts, restore } = splitKirbyText(text, { link: ["text"] });

        expect(unitTexts).toHaveLength(2);
        expect(() => restore(input(unitTexts))).toThrow(
          `Expected 2 translations, got ${expectedGot}`,
        );
      },
    );
  });
});
