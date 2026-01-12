import type { KirbyFieldProps } from "kirby-types";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { translateContent } from "../../src/panel/utils/translation";
import {
  blocksField,
  field,
  layoutField,
  objectField,
  structureField,
} from "./utils";

const mockApiPost = vi.fn();
vi.mock("kirbyuse", () => ({
  useApi: () => ({ post: mockApiPost }),
}));

vi.mock("p-all", () => ({
  default: async (
    tasks: (() => Promise<void>)[],
    _options?: { concurrency: number },
  ) => {
    for (const task of tasks) await task();
  },
}));

const defaultOptions = {
  targetLanguage: "de",
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
  ],
  kirbyTags: {},
  fields: {} as Record<string, KirbyFieldProps>,
};

describe("translateContent", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe("text-like fields (batch translation)", () => {
    it("translates text, writer, and list fields in a single batch request", async () => {
      mockApiPost.mockResolvedValueOnce({
        texts: ["Hallo", "Welt", "Liste"],
      });

      const content = { title: "Hello", body: "World", items: "List" };
      const fields = {
        title: field({ type: "text", name: "text" }),
        body: field({ type: "writer", name: "writer" }),
        items: field({ type: "list", name: "list" }),
      };

      await translateContent(content, { ...defaultOptions, fields });

      expect(mockApiPost).toHaveBeenCalledOnce();
      expect(mockApiPost).toHaveBeenCalledWith(
        "__content-translator__/translate-batch",
        expect.objectContaining({
          texts: ["Hello", "World", "List"],
          targetLanguage: "de",
        }),
      );
      expect(content).toEqual({ title: "Hallo", body: "Welt", items: "Liste" });
    });
  });

  describe("markdown/textarea fields", () => {
    it("translates each field individually via KirbyText endpoint", async () => {
      mockApiPost
        .mockResolvedValueOnce({ text: "Erster Absatz" })
        .mockResolvedValueOnce({ text: "Zweiter Absatz" });

      const content = {
        intro: "First paragraph",
        description: "Second paragraph",
      };
      const fields = {
        intro: field({ type: "textarea", name: "textarea" }),
        description: field({ type: "markdown", name: "markdown" }),
      };

      await translateContent(content, { ...defaultOptions, fields });

      expect(mockApiPost).toHaveBeenCalledTimes(2);
      expect(mockApiPost).toHaveBeenCalledWith(
        "__content-translator__/translate-kirbytext",
        expect.objectContaining({ text: "First paragraph" }),
      );
      expect(content.intro).toBe("Erster Absatz");
      expect(content.description).toBe("Zweiter Absatz");
    });

    it("skips empty textarea content", async () => {
      const content = { intro: "   ", description: "" };
      const fields = {
        intro: field({ type: "textarea", name: "textarea" }),
        description: field({ type: "markdown", name: "markdown" }),
      };

      await translateContent(content, { ...defaultOptions, fields });

      expect(mockApiPost).not.toHaveBeenCalled();
    });
  });

  describe("tags field", () => {
    it("joins tags for translation and splits result back", async () => {
      mockApiPost.mockResolvedValueOnce({ texts: ["Rot | Grün | Blau"] });

      const content = { colors: ["Red", "Green", "Blue"] };
      const fields = { colors: field({ type: "tags", name: "tags" }) };

      await translateContent(content, { ...defaultOptions, fields });

      expect(mockApiPost).toHaveBeenCalledWith(
        "__content-translator__/translate-batch",
        expect.objectContaining({ texts: ["Red | Green | Blue"] }),
      );
      expect(content.colors).toEqual(["Rot", "Grün", "Blau"]);
    });

    it("skips empty tags array", async () => {
      const content = { colors: [] };
      const fields = { colors: field({ type: "tags", name: "tags" }) };

      await translateContent(content, { ...defaultOptions, fields });

      expect(mockApiPost).not.toHaveBeenCalled();
    });
  });

  describe("structure fields", () => {
    it("recursively translates nested structure items", async () => {
      mockApiPost.mockResolvedValueOnce({ texts: ["Eins", "Zwei"] });

      const content = {
        items: [{ label: "One" }, { label: "Two" }],
      };
      const fields = {
        items: structureField("items", {
          label: field({ type: "text", name: "label" }),
        }),
      };

      await translateContent(content, { ...defaultOptions, fields });

      expect(content.items).toEqual([{ label: "Eins" }, { label: "Zwei" }]);
    });
  });

  describe("object fields", () => {
    it("recursively translates object content", async () => {
      mockApiPost.mockResolvedValueOnce({ texts: ["Verschachtelt"] });

      const content = { meta: { title: "Nested" } };
      const fields = {
        meta: objectField("meta", {
          title: field({ type: "text", name: "title" }),
        }),
      };

      await translateContent(content, { ...defaultOptions, fields });

      expect(content.meta.title).toBe("Verschachtelt");
    });
  });

  describe("blocks fields", () => {
    it("translates block content recursively", async () => {
      mockApiPost.mockResolvedValueOnce({ texts: ["Überschrift"] });

      const content = {
        blocks: [
          {
            id: "abc123",
            type: "heading",
            isHidden: false,
            content: { text: "Heading" },
          },
        ],
      };
      const fields = {
        blocks: blocksField("blocks", {
          heading: { text: field({ type: "text", name: "text" }) },
        }),
      };

      await translateContent(content, { ...defaultOptions, fields });

      expect(content.blocks[0].content.text).toBe("Überschrift");
    });

    it("skips hidden blocks", async () => {
      const content = {
        blocks: [
          {
            id: "abc",
            type: "text",
            isHidden: true,
            content: { text: "Skip" },
          },
        ],
      };
      const fields = {
        blocks: blocksField("blocks", {
          text: { text: field({ type: "text", name: "text" }) },
        }),
      };

      await translateContent(content, { ...defaultOptions, fields });

      expect(mockApiPost).not.toHaveBeenCalled();
    });
  });

  describe("layout fields", () => {
    it("translates blocks within layout columns", async () => {
      mockApiPost.mockResolvedValueOnce({ texts: ["Spaltentext"] });

      const content = {
        layout: [
          {
            columns: [
              {
                blocks: [
                  {
                    id: "xyz",
                    type: "text",
                    isHidden: false,
                    content: { body: "Column text" },
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

      await translateContent(content, { ...defaultOptions, fields });

      expect(content.layout[0].columns[0].blocks[0].content.body).toBe(
        "Spaltentext",
      );
    });
  });

  describe("field filtering", () => {
    it("skips fields with translate: false", async () => {
      const content = { title: "Hello", slug: "hello" };
      const fields = {
        title: field({ type: "text", name: "text" }),
        slug: field({ type: "text", name: "text", translate: false }),
      };

      mockApiPost.mockResolvedValueOnce({ texts: ["Hallo"] });

      await translateContent(content, { ...defaultOptions, fields });

      expect(content.title).toBe("Hallo");
      expect(content.slug).toBe("hello");
    });

    it("respects includeFields filter", async () => {
      mockApiPost.mockResolvedValueOnce({ texts: ["Hallo"] });

      const content = { title: "Hello", subtitle: "World" };
      const fields = {
        title: field({ type: "text", name: "text" }),
        subtitle: field({ type: "text", name: "text" }),
      };

      await translateContent(content, {
        ...defaultOptions,
        fields,
        includeFields: ["title"],
      });

      expect(content.title).toBe("Hallo");
      expect(content.subtitle).toBe("World");
    });

    it("respects excludeFields filter", async () => {
      mockApiPost.mockResolvedValueOnce({ texts: ["Hallo"] });

      const content = { title: "Hello", subtitle: "World" };
      const fields = {
        title: field({ type: "text", name: "text" }),
        subtitle: field({ type: "text", name: "text" }),
      };

      await translateContent(content, {
        ...defaultOptions,
        fields,
        excludeFields: ["subtitle"],
      });

      expect(content.title).toBe("Hallo");
      expect(content.subtitle).toBe("World");
    });

    it("skips fields not in fieldTypes list", async () => {
      const content = { title: "Hello" };
      const fields = { title: field({ type: "text", name: "text" }) };

      await translateContent(content, {
        ...defaultOptions,
        fields,
        fieldTypes: ["textarea"], // text not included
      });

      expect(mockApiPost).not.toHaveBeenCalled();
      expect(content.title).toBe("Hello");
    });

    it("skips fields without definition", async () => {
      const content = { title: "Hello", unknown: "Value" };
      const fields = { title: field({ type: "text", name: "text" }) };

      mockApiPost.mockResolvedValueOnce({ texts: ["Hallo"] });

      await translateContent(content, { ...defaultOptions, fields });

      expect(content.unknown).toBe("Value");
    });

    it("skips empty values", async () => {
      const content = { title: "", body: null, items: undefined };
      const fields = {
        title: field({ type: "text", name: "text" }),
        body: field({ type: "text", name: "text" }),
        items: field({ type: "text", name: "text" }),
      };

      await translateContent(content, { ...defaultOptions, fields });

      expect(mockApiPost).not.toHaveBeenCalled();
    });
  });

  describe("table fields", () => {
    it("translates table cells individually", async () => {
      mockApiPost
        .mockResolvedValueOnce({ text: "Zelle1" })
        .mockResolvedValueOnce({ text: "Zelle2" });

      const content = { table: [["Cell1", "Cell2"]] };
      const fields = { table: field({ type: "table", name: "table" }) };

      await translateContent(content, { ...defaultOptions, fields });

      expect(content.table).toEqual([["Zelle1", "Zelle2"]]);
    });

    it("handles YAML-encoded table data", async () => {
      mockApiPost
        .mockResolvedValueOnce({ text: "Übersetzt A" })
        .mockResolvedValueOnce({ text: "Übersetzt B" });

      const yamlTable = "-\n  - A\n  - B";
      const content = { table: yamlTable };
      const fields = { table: field({ type: "table", name: "table" }) };

      await translateContent(content, { ...defaultOptions, fields });

      // Should be re-serialized to YAML format
      expect(typeof content.table).toBe("string");
      expect(content.table).toContain("Übersetzt");
    });

    it("skips empty table cells", async () => {
      const content = { table: [["", "  ", null]] };
      const fields = { table: field({ type: "table", name: "table" }) };

      await translateContent(content, { ...defaultOptions, fields });

      expect(mockApiPost).not.toHaveBeenCalled();
    });
  });

  describe("error handling", () => {
    it("propagates API errors", async () => {
      mockApiPost.mockRejectedValueOnce(new Error("API Error"));

      const content = { title: "Hello" };
      const fields = { title: field({ type: "text", name: "text" }) };

      await expect(
        translateContent(content, { ...defaultOptions, fields }),
      ).rejects.toThrow("API Error");
    });
  });
});
