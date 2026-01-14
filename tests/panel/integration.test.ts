import type { KirbyFieldProps } from "kirby-types";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { DeepLStrategy, translateContent } from "../../src/panel/translation";
import { blocksField, field, structureField } from "./translation/utils";

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
    targetLanguage: "de",
    fieldTypes: ["text", "textarea", "blocks", "structure"] as const,
    kirbyTags: {},
    fields: {} as Record<string, KirbyFieldProps>,
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("translates flat content end-to-end", async () => {
    mockApiPost.mockResolvedValueOnce({ texts: ["Hallo", "Welt"] });

    const content = { title: "Hello", subtitle: "World" };
    const fields = {
      title: field({ type: "text", name: "text" }),
      subtitle: field({ type: "text", name: "text" }),
    };

    await translateContent(content, { ...defaultOptions, fields });

    expect(content).toEqual({ title: "Hallo", subtitle: "Welt" });
  });

  it("translates nested content end-to-end", async () => {
    mockApiPost
      .mockResolvedValueOnce({ texts: ["Titel", "Eins", "Zwei"] }) // batch
      .mockResolvedValueOnce({ text: "Beschreibung" }); // kirbytext

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

  it("translates blocks content end-to-end", async () => {
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

    expect(content.blocks[0].content.text).toBe("Überschrift");
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

  it("passes sourceLanguage to strategy", async () => {
    mockApiPost.mockResolvedValueOnce({ texts: ["Hallo"] });

    const content = { title: "Hello" };
    const fields = { title: field({ type: "text", name: "text" }) };

    await translateContent(content, {
      ...defaultOptions,
      fields,
      sourceLanguage: "en",
    });

    expect(mockApiPost).toHaveBeenCalledWith(
      "__content-translator__/translate-batch",
      expect.objectContaining({ sourceLanguage: "en" }),
    );
  });

  it("propagates errors from strategy", async () => {
    mockApiPost.mockRejectedValueOnce(new Error("Translation failed"));

    const content = { title: "Hello" };
    const fields = { title: field({ type: "text", name: "text" }) };

    await expect(
      translateContent(content, { ...defaultOptions, fields }),
    ).rejects.toThrow("Translation failed");
  });
});
