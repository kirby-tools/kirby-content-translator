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

    it("collects textarea and markdown fields as kirbytext mode", () => {
      const content = { intro: "First", description: "Second" };
      const fields = {
        intro: field({ type: "textarea", name: "textarea" }),
        description: field({ type: "markdown", name: "markdown" }),
      };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      expect(translations).toHaveLength(2);
      expect(translations.map((t) => t.unit.mode)).toEqual([
        "kirbytext",
        "kirbytext",
      ]);
    });

    it("collects tags as batch mode with joined text", () => {
      const content = { colors: ["Red", "Green", "Blue"] };
      const fields = { colors: field({ type: "tags", name: "tags" }) };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      expect(translations).toHaveLength(1);
      expect(translations[0].unit.mode).toBe("batch");
      expect(translations[0].unit.text).toBe("Red | Green | Blue");
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
  });

  describe("yAML table serialization", () => {
    it("serializes translations back to YAML format", () => {
      const yamlTable = "-\n  - A\n  - B";
      const content = { table: yamlTable };
      const fields = { table: field({ type: "table", name: "table" }) };

      const { translations, finalizers } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      // Apply translations
      translations[0].apply("X");
      translations[1].apply("Y");

      // Run finalizer
      finalizers[0]();

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

      translations[0].apply("1");
      translations[1].apply("2");
      translations[2].apply("3");
      translations[3].apply("4");
      finalizers[0]();

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
      expect(translations[0].unit.text).toBe("Nested");
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
      expect(translations[0].unit.text).toBe("Visible");
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
      expect(translations[0].unit.text).toBe("Known");
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
      expect(translations[0].unit.text).toBe("Column");
    });
  });

  describe("filtering", () => {
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
      expect(translations[0].unit.text).toBe("Hello");
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
      expect(translations[0].unit.text).toBe("Hello");
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
      expect(translations[0].unit.text).toBe("Hello");
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

    it("skips empty values", () => {
      const content = { a: "", b: null, c: undefined, d: "Valid" };
      const fields = {
        a: field({ type: "text", name: "text" }),
        b: field({ type: "text", name: "text" }),
        c: field({ type: "text", name: "text" }),
        d: field({ type: "text", name: "text" }),
      };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      expect(translations).toHaveLength(1);
      expect(translations[0].unit.text).toBe("Valid");
    });

    it("skips whitespace-only textarea content", () => {
      const content = { intro: "   ", description: "" };
      const fields = {
        intro: field({ type: "textarea", name: "textarea" }),
        description: field({ type: "markdown", name: "markdown" }),
      };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      expect(translations).toHaveLength(0);
    });

    it("skips empty tags array", () => {
      const content = { colors: [] };
      const fields = { colors: field({ type: "tags", name: "tags" }) };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      expect(translations).toHaveLength(0);
    });

    it("skips empty table cells", () => {
      const content = { table: [["", "  ", null]] };
      const fields = { table: field({ type: "table", name: "table" }) };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });

      expect(translations).toHaveLength(0);
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
      translations[0].apply("Hallo");

      expect(content.title).toBe("Hallo");
    });

    it("splits translated tags back into array", () => {
      const content = { colors: ["Red", "Green"] };
      const fields = { colors: field({ type: "tags", name: "tags" }) };

      const { translations } = collectTranslations(content, {
        ...defaultOptions,
        fields,
      });
      translations[0].apply("Rot | Grün");

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
      translations[0].apply("Eins");

      expect(content.items[0].label).toBe("Eins");
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

      translations[0].apply("1");
      translations[1].apply("2");
      translations[2].apply("3");
      translations[3].apply("4");

      expect(content.table).toEqual([
        ["1", "2"],
        ["3", "4"],
      ]);
    });
  });
});
