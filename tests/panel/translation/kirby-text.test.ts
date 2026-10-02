import { readdirSync, readFileSync } from "node:fs";
import { join } from "node:path";
import { Ajv } from "ajv";
import { describe, expect, it } from "vitest";
import { splitKirbyText } from "../../../src/panel/translation/kirby-text";

interface ConformanceCase {
  description: string;
  sourceText: string;
  kirbyTagTypes: string[];
  kirbyTags: Record<string, string[]>;
  translatedUnitTexts: string[];
  unitTexts: string[];
  restoredText: string;
}

const FIXTURES_DIR = join(import.meta.dirname, "../../fixtures/kirby-text");

const conformanceCases = readdirSync(FIXTURES_DIR)
  .filter((file) => file.endsWith(".json"))
  .map((file) => ({
    name: file.replace(/\.json$/, ""),
    conformanceCase: JSON.parse(
      readFileSync(join(FIXTURES_DIR, file), "utf8"),
    ) as ConformanceCase,
  }));

const validateConformanceCase = new Ajv().compile(
  JSON.parse(
    readFileSync(
      join(import.meta.dirname, "../../fixtures/kirby-text.schema.json"),
      "utf8",
    ),
  ),
);

it.each(conformanceCases)(
  "$name validates against kirby-text.schema.json",
  ({ conformanceCase }) => {
    validateConformanceCase(conformanceCase);
    expect(validateConformanceCase.errors).toBeNull();
  },
);

describe("splitKirbyText", () => {
  // Shared with `KirbyTextSplitTest.php` – drift fails here first.
  it.each(conformanceCases)(
    "splits and restores $name",
    ({
      conformanceCase: {
        sourceText,
        kirbyTagTypes,
        kirbyTags,
        translatedUnitTexts,
        unitTexts,
        restoredText,
      },
    }) => {
      const split = splitKirbyText(sourceText, {
        types: kirbyTagTypes,
        attributes: kirbyTags,
      });

      expect(split.unitTexts).toEqual(unitTexts);
      expect(split.restore(translatedUnitTexts)).toBe(restoredText);
    },
  );

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
        const { unitTexts, restore } = splitKirbyText(text, {
          types: ["link"],
          attributes: { link: ["text"] },
        });

        expect(unitTexts).toHaveLength(2);
        expect(() => restore(input(unitTexts))).toThrow(
          `Expected 2 translations, got ${expectedGot}`,
        );
      },
    );
  });
});
