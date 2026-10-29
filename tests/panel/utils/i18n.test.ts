import { describe, expect, it } from "vitest";
import { translatePlural } from "../../../src/panel/utils/i18n";

const TRANSLATIONS: Record<string, string> = {
  items: "{count} item | {count} items",
  keptSource:
    "{count} text kept its source text: {fields} | {count} texts kept their source text: {fields}",
  translated: "Translated",
};

// Fills `{name}` like the Panel's `template()`.
const t = (key: string, data: Record<string, unknown> = {}) =>
  TRANSLATIONS[key]!.replace(/\{(\w+)\}/g, (_, name) => String(data[name]));

describe("translatePlural", () => {
  it("picks the singular form for a count of 1", () => {
    expect(translatePlural(t, "items", { count: 1 }, 1)).toBe("1 item");
  });

  it("picks the plural form for a count of 0", () => {
    expect(translatePlural(t, "items", { count: 0 }, 0)).toBe("0 items");
  });

  it("keeps a string without a plural form whole", () => {
    expect(translatePlural(t, "translated", {}, 2)).toBe("Translated");
  });

  it("fills in a value holding the form separator in full", () => {
    expect(
      translatePlural(t, "keptSource", { count: 1, fields: "Price | Tax" }, 1),
    ).toBe("1 text kept its source text: Price | Tax");
  });
});
