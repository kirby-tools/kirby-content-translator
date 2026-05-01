import type { KirbyFieldProps } from "kirby-types";
import { describe, expect, it } from "vitest";
import { collectTranslations } from "../../../src/panel/translation/collector";
import {
  blocksField,
  field,
  layoutField,
  objectField,
  structureField,
} from "./utils";

describe("collectTranslations", () => {
  const defaultOptions = {
    fieldTypes: [
      "text",
      "textarea",
      "writer",
      "list",
      "tags",
      "blocks",
      "layout",
      "structure",
      "object",
      "markdown",
      "table",
    ] as const,
    fields: {} as Record<string, KirbyFieldProps>,
  };

  describe("translation modes", () => {
    it("collects text, writer, and list fields as batch mode", () => {
      const content = { title: "Hello", body: "World", items: "List" };
      const fields = {
        title: field({ type: "text", name: "text" }),
        body: field({ type: "writer", name: "writer" }),
        items: field({ type: "list", name: "list" }),
      };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      expect(translations).toHaveLength(3);
      expect(translations.map((t) => t.unit.mode)).toEqual([
        "batch",
        "batch",
        "batch",
      ]);
      expect(translations.map((t) => t.unit.text)).toEqual([
        "Hello",
        "World",
        "List",
      ]);
    });

    it("expands textarea fields into batch units with KirbyTag protection", () => {
      const content = { intro: "Visit (link: /a text: site)" };
      const fields = {
        intro: field({ type: "textarea", name: "textarea" }),
      };

      const { translations, finalizers } = collectTranslations(content, {
        ...defaultOptions,
        fields,
        kirbyTags: { link: ["text"] },
      });

      expect(translations).toHaveLength(2);
      expect(translations.every((t) => t.unit.mode === "batch")).toBe(true);
      expect(translations.map((t) => t.unit.text)).toContain("site");

      translations[0]!.apply(translations[0]!.unit.text);
      translations[1]!.apply("Seite");
      for (const finalize of finalizers) finalize();

      expect(content.intro).toBe("Visit (link: /a text: Seite)");
    });

    it("protects markdown fields when no kirbyTags config is provided", () => {
      const content = { description: "Hello (link: /a text: world)!" };
      const fields = {
        description: field({ type: "markdown", name: "markdown" }),
      };

      const { translations, finalizers } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      expect(translations).toHaveLength(1);
      expect(translations[0]!.unit.mode).toBe("batch");
      expect(translations[0]!.unit.text).not.toContain("link");

      translations[0]!.apply(
        translations[0]!.unit.text.replace("Hello", "Hallo"),
      );
      for (const finalize of finalizers) finalize();

      expect(content.description).toBe("Hallo (link: /a text: world)!");
    });

    it("collects tags as batch mode with joined text", () => {
      const content = { colors: ["Red", "Green", "Blue"] };
      const fields = { colors: field({ type: "tags", name: "tags" }) };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      expect(translations).toHaveLength(1);
      expect(translations[0]!.unit.mode).toBe("batch");
      expect(translations[0]!.unit.text).toBe("Red | Green | Blue");
    });

    it("collects table cells as single mode", () => {
      const content = {
        table: [
          ["A", "B"],
          ["C", "D"],
        ],
      };
      const fields = { table: field({ type: "table", name: "table" }) };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      expect(translations).toHaveLength(4);
      expect(translations.every((t) => t.unit.mode === "single")).toBe(true);
      expect(translations.map((t) => t.unit.text)).toEqual([
        "A",
        "B",
        "C",
        "D",
      ]);
    });
  });

  // eslint-disable-next-line test/prefer-lowercase-title
  describe("YAML table serialization", () => {
    it("registers finalizer for YAML-encoded tables", () => {
      const yamlTable = "-\n  - A\n  - B";
      const content = { table: yamlTable };
      const fields = { table: field({ type: "table", name: "table" }) };

      const { translations, finalizers } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      expect(translations).toHaveLength(2);
      expect(finalizers).toHaveLength(1);
    });

    it("serializes translations back to YAML format", () => {
      const yamlTable = "-\n  - A\n  - B";
      const content = { table: yamlTable };
      const fields = { table: field({ type: "table", name: "table" }) };

      const { translations, finalizers } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      // Apply translations
      translations[0]!.apply("X");
      translations[1]!.apply("Y");

      // Run finalizer
      finalizers[0]!();

      expect(content.table).toBe("-\n  - X\n  - Y");
    });

    it("handles multi-row YAML tables", () => {
      const yamlTable = "-\n  - A\n  - B\n-\n  - C\n  - D";
      const content = { table: yamlTable };
      const fields = { table: field({ type: "table", name: "table" }) };

      const { translations, finalizers } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      translations[0]!.apply("1");
      translations[1]!.apply("2");
      translations[2]!.apply("3");
      translations[3]!.apply("4");
      finalizers[0]!();

      expect(content.table).toBe('-\n  - "1"\n  - "2"\n-\n  - "3"\n  - "4"');
    });
  });

  describe("nested structures", () => {
    it("traverses structure fields recursively", () => {
      const content = {
        items: [{ label: "One" }, { label: "Two" }],
      };
      const fields = {
        items: structureField("items", {
          label: field({ type: "text", name: "label" }),
        }),
      };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      expect(translations).toHaveLength(2);
      expect(translations.map((t) => t.unit.text)).toEqual(["One", "Two"]);
    });

    it("traverses object fields recursively", () => {
      const content = { meta: { title: "Nested" } };
      const fields = {
        meta: objectField("meta", {
          title: field({ type: "text", name: "title" }),
        }),
      };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      expect(translations).toHaveLength(1);
      expect(translations[0]!.unit.text).toBe("Nested");
    });

    it("traverses blocks fields recursively", () => {
      const content = {
        blocks: [
          {
            id: "1",
            type: "heading",
            isHidden: false,
            content: { text: "Title" },
          },
          { id: "2", type: "text", isHidden: false, content: { body: "Body" } },
        ],
      };
      const fields = {
        blocks: blocksField("blocks", {
          heading: { text: field({ type: "text", name: "text" }) },
          text: { body: field({ type: "text", name: "body" }) },
        }),
      };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      expect(translations).toHaveLength(2);
      expect(translations.map((t) => t.unit.text)).toEqual(["Title", "Body"]);
    });

    it("skips hidden blocks", () => {
      const content = {
        blocks: [
          {
            id: "1",
            type: "text",
            isHidden: true,
            content: { text: "Hidden" },
          },
          {
            id: "2",
            type: "text",
            isHidden: false,
            content: { text: "Visible" },
          },
        ],
      };
      const fields = {
        blocks: blocksField("blocks", {
          text: { text: field({ type: "text", name: "text" }) },
        }),
      };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      expect(translations).toHaveLength(1);
      expect(translations[0]!.unit.text).toBe("Visible");
    });

    it("skips blocks with unknown fieldset type", () => {
      const content = {
        blocks: [
          {
            id: "1",
            type: "unknown",
            isHidden: false,
            content: { text: "Unknown" },
          },
          {
            id: "2",
            type: "text",
            isHidden: false,
            content: { text: "Known" },
          },
        ],
      };
      const fields = {
        blocks: blocksField("blocks", {
          text: { text: field({ type: "text", name: "text" }) },
        }),
      };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      expect(translations).toHaveLength(1);
      expect(translations[0]!.unit.text).toBe("Known");
    });

    it("traverses layout columns recursively", () => {
      const content = {
        layout: [
          {
            columns: [
              {
                blocks: [
                  {
                    id: "1",
                    type: "text",
                    isHidden: false,
                    content: { body: "Column" },
                  },
                ],
              },
            ],
          },
        ],
      };
      const fields = {
        layout: layoutField("layout", {
          text: { body: field({ type: "text", name: "body" }) },
        }),
      };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      expect(translations).toHaveLength(1);
      expect(translations[0]!.unit.text).toBe("Column");
    });
  });

  describe("field filtering", () => {
    it("skips fields with translate: false", () => {
      const content = { title: "Hello", slug: "hello" };
      const fields = {
        title: field({ type: "text", name: "text" }),
        slug: field({ type: "text", name: "text", translate: false }),
      };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      expect(translations).toHaveLength(1);
      expect(translations[0]!.unit.text).toBe("Hello");
    });

    it("respects includeFields filter", () => {
      const content = { title: "Hello", subtitle: "World" };
      const fields = {
        title: field({ type: "text", name: "text" }),
        subtitle: field({ type: "text", name: "text" }),
      };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
        includeFields: ["title"],
      });

      expect(translations).toHaveLength(1);
      expect(translations[0]!.unit.text).toBe("Hello");
    });

    it("respects excludeFields filter", () => {
      const content = { title: "Hello", subtitle: "World" };
      const fields = {
        title: field({ type: "text", name: "text" }),
        subtitle: field({ type: "text", name: "text" }),
      };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
        excludeFields: ["subtitle"],
      });

      expect(translations).toHaveLength(1);
      expect(translations[0]!.unit.text).toBe("Hello");
    });

    it("traverses structure inner fields when includeFields is set", () => {
      const content = {
        title: "Hello",
        items: [{ label: "One" }, { label: "Two" }],
      };
      const fields = {
        title: field({ type: "text", name: "text" }),
        items: structureField("items", {
          label: field({ type: "text", name: "label" }),
        }),
      };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
        includeFields: ["items"],
      });

      expect(translations).toHaveLength(2);
      expect(translations.map((t) => t.unit.text)).toEqual(["One", "Two"]);
    });

    it("skips fields not in fieldTypes list", () => {
      const content = { title: "Hello" };
      const fields = { title: field({ type: "text", name: "text" }) };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
        fieldTypes: ["textarea"],
      });

      expect(translations).toHaveLength(0);
    });

    it("skips fields without definition", () => {
      const content = { title: "Hello", unknown: "Value" };
      const fields = { title: field({ type: "text", name: "text" }) };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      expect(translations).toHaveLength(1);
    });
  });

  describe("value filtering", () => {
    it.each([
      // Falsy / empty values
      { kind: "empty string", fieldType: "text", value: "" },
      { kind: "null value", fieldType: "text", value: null },
      { kind: "undefined value", fieldType: "text", value: undefined },
      // Numeric-only strings carry no translatable prose
      { kind: "pure integer", fieldType: "text", value: "123" },
      { kind: "decimal", fieldType: "text", value: "45.67" },
      { kind: "negative number", fieldType: "text", value: "-99" },
      { kind: "scientific notation", fieldType: "text", value: "1.5e10" },
      // URL-only strings
      { kind: "https URL", fieldType: "text", value: "https://example.com" },
      {
        kind: "http URL with path",
        fieldType: "text",
        value: "http://localhost:3000/path?query=1",
      },
      // Whitespace / structural empties
      { kind: "whitespace-only textarea", fieldType: "textarea", value: "   " },
      { kind: "empty markdown", fieldType: "markdown", value: "" },
      { kind: "empty tags array", fieldType: "tags", value: [] },
      {
        kind: "empty table cells",
        fieldType: "table",
        value: [["", "  ", null]],
      },
    ])("skips $kind", ({ fieldType, value }) => {
      const content = { x: value };
      const fields = { x: field({ type: fieldType, name: fieldType }) };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      expect(translations).toHaveLength(0);
    });

    it("translates non-empty content alongside skipped values", () => {
      const content = {
        skipped: "123",
        valid: "Visit https://example.com today",
      };
      const fields = {
        skipped: field({ type: "text", name: "text" }),
        valid: field({ type: "text", name: "text" }),
      };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      expect(translations).toHaveLength(1);
      expect(translations[0]!.unit.text).toBe(
        "Visit https://example.com today",
      );
    });
  });

  describe("apply callbacks", () => {
    it("applies translated text to original object", () => {
      const content = { title: "Hello" };
      const fields = { title: field({ type: "text", name: "text" }) };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });
      translations[0]!.apply("Hallo");

      expect(content.title).toBe("Hallo");
    });

    it("splits translated tags back into array", () => {
      const content = { colors: ["Red", "Green"] };
      const fields = { colors: field({ type: "tags", name: "tags" }) };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });
      translations[0]!.apply("Rot | Grün");

      expect(content.colors).toEqual(["Rot", "Grün"]);
    });

    it("applies translations to nested structure items", () => {
      const content = { items: [{ label: "One" }] };
      const fields = {
        items: structureField("items", {
          label: field({ type: "text", name: "label" }),
        }),
      };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });
      translations[0]!.apply("Eins");

      expect(content.items[0]!.label).toBe("Eins");
    });

    it("applies translations to table cells", () => {
      const content = {
        table: [
          ["A", "B"],
          ["C", "D"],
        ],
      };
      const fields = { table: field({ type: "table", name: "table" }) };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      translations[0]!.apply("1");
      translations[1]!.apply("2");
      translations[2]!.apply("3");
      translations[3]!.apply("4");

      expect(content.table).toEqual([
        ["1", "2"],
        ["3", "4"],
      ]);
    });
  });
});
