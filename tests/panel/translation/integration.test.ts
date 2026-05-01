import type { KirbyFieldProps } from "kirby-types";
import type { TranslationStrategy } from "../../../src/panel/translation/types";
import { beforeEach, describe, expect, it, vi } from "vitest";
import {
  DeepLStrategy,
  translateContent,
} from "../../../src/panel/translation";
import { blocksField, field, structureField } from "./utils";

const mockApiPost = vi.fn();

vi.mock("kirbyuse", () => ({
  useApi: () => ({ post: mockApiPost }),
}));

vi.mock("p-all", () => ({
  default: async <T>(
    tasks: (() => Promise<T>)[],
    _options?: { concurrency: number },
  ): Promise<T[]> => {
    const results: T[] = [];
    for (const task of tasks) results.push(await task());
    return results;
  },
}));

describe("translateContent (integration)", () => {
  const strategy = new DeepLStrategy();

  const defaultOptions = {
    strategy,
    targetLanguage: { code: "de", name: "German" },
    fieldTypes: ["text", "textarea", "blocks", "structure"] as const,
    kirbyTags: {},
    fields: {} as Record<string, KirbyFieldProps>,
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe("content shapes", () => {
    it("translates flat content", async () => {
      mockApiPost.mockResolvedValueOnce({ texts: ["Hallo", "Welt"] });

      const content = { title: "Hello", subtitle: "World" };
      const fields = {
        title: field({ type: "text", name: "text" }),
        subtitle: field({ type: "text", name: "text" }),
      };

      await translateContent(content, { ...defaultOptions, fields });

      expect(content).toEqual({ title: "Hallo", subtitle: "Welt" });
    });

    it("translates nested structure content", async () => {
      mockApiPost.mockResolvedValueOnce({
        texts: ["Titel", "Beschreibung", "Eins", "Zwei"],
      });

      const content = {
        title: "Title",
        description: "Description",
        items: [{ label: "One" }, { label: "Two" }],
      };
      const fields = {
        title: field({ type: "text", name: "text" }),
        description: field({ type: "textarea", name: "textarea" }),
        items: structureField("items", {
          label: field({ type: "text", name: "label" }),
        }),
      };

      await translateContent(content, { ...defaultOptions, fields });

      expect(content.title).toBe("Titel");
      expect(content.description).toBe("Beschreibung");
      expect(content.items).toEqual([{ label: "Eins" }, { label: "Zwei" }]);
    });

    it("translates blocks content", async () => {
      mockApiPost.mockResolvedValueOnce({ texts: ["Überschrift"] });

      const content = {
        blocks: [
          {
            id: "1",
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

      expect(content.blocks[0]!.content.text).toBe("Überschrift");
    });

    it("translates YAML-encoded table content", async () => {
      mockApiPost
        .mockResolvedValueOnce({ text: "X" })
        .mockResolvedValueOnce({ text: "Y" });

      const content = { table: "-\n  - A\n  - B" };
      const fields = { table: field({ type: "table", name: "table" }) };

      await translateContent(content, {
        ...defaultOptions,
        fieldTypes: [...defaultOptions.fieldTypes, "table"],
        fields,
      });

      expect(content.table).toBe("-\n  - X\n  - Y");
    });

    it("returns original object when nothing to translate", async () => {
      const content = { title: "" };
      const fields = { title: field({ type: "text", name: "text" }) };

      const result = await translateContent(content, {
        ...defaultOptions,
        fields,
      });

      expect(mockApiPost).not.toHaveBeenCalled();
      expect(result).toBe(content);
    });
  });

  describe("options", () => {
    it("passes sourceLanguage to strategy", async () => {
      mockApiPost.mockResolvedValueOnce({ texts: ["Hallo"] });

      const content = { title: "Hello" };
      const fields = { title: field({ type: "text", name: "text" }) };

      await translateContent(content, {
        ...defaultOptions,
        fields,
        sourceLanguage: { code: "en", name: "English" },
      });

      expect(mockApiPost).toHaveBeenCalledWith(
        "__content-translator__/translate-batch",
        expect.objectContaining({ sourceLanguage: "en" }),
      );
    });
  });

  describe("error handling", () => {
    it("propagates errors from strategy", async () => {
      mockApiPost.mockRejectedValueOnce(new Error("Translation failed"));

      const content = { title: "Hello" };
      const fields = { title: field({ type: "text", name: "text" }) };

      await expect(
        translateContent(content, { ...defaultOptions, fields }),
      ).rejects.toThrow("Translation failed");
    });
  });

  // eslint-disable-next-line test/prefer-lowercase-title
  describe("KirbyTags", () => {
    it("hides KirbyTag structure from the translator", async () => {
      const seenInputs: string[] = [];
      const spyStrategy: TranslationStrategy = {
        async execute(units) {
          for (const unit of units) seenInputs.push(unit.text);
          return units.map((u) => u.text);
        },
      };

      const content = {
        body: "Welcome (button: /start text: Start icon: arrow style: outline) home.",
      };
      const fields = {
        body: field({ type: "textarea", name: "textarea" }),
      };

      await translateContent(content, {
        strategy: spyStrategy,
        targetLanguage: { code: "de", name: "German" },
        fieldTypes: ["textarea"] as const,
        kirbyTags: { button: ["text"] },
        fields,
      });

      expect(seenInputs.join("\n")).not.toMatch(
        /icon|style|\/start|arrow|outline/,
      );
      expect(seenInputs).toContain("Start");
    });
  });

  describe("abort signal", () => {
    it("returns original content when signal is pre-aborted", async () => {
      const controller = new AbortController();
      controller.abort();

      const content = { title: "Hello", subtitle: "World" };
      const fields = {
        title: field({ type: "text", name: "text" }),
        subtitle: field({ type: "text", name: "text" }),
      };

      const result = await translateContent(content, {
        ...defaultOptions,
        fields,
        signal: controller.signal,
      });

      expect(mockApiPost).not.toHaveBeenCalled();
      expect(result).toBe(content);
      expect(content).toEqual({ title: "Hello", subtitle: "World" });
    });
  });
});
