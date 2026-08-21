import type { TranslationStrategy } from "../../../src/panel/translation/types";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { AIStrategy, translateContent } from "../../../src/panel/translation";
import { field } from "./utils";

const mockStreamText = vi.fn();

vi.mock("kirbyuse", () => ({
  useApi: () => ({ post: vi.fn() }),
}));

vi.mock("../../../src/panel/utils/copilot", () => ({
  resolveCopilot: vi.fn(() => ({
    apiVersion: 2,
    streamText: mockStreamText,
  })),
}));

describe("translateContent", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("hides KirbyTag structure from the strategy", async () => {
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

  it("keeps untranslatable KirbyTag attributes away from the strategy", async () => {
    const seenInputs: string[] = [];
    const spyStrategy: TranslationStrategy = {
      async execute(units) {
        for (const unit of units) seenInputs.push(unit.text);
        return units.map((u) => `[de]${u.text}`);
      },
    };

    const content = {
      body: "Photo (image: photo.jpg alt: 2024 caption: Our team)",
    };
    const fields = {
      body: field({ type: "textarea", name: "textarea" }),
    };

    await translateContent(content, {
      strategy: spyStrategy,
      targetLanguage: { code: "de", name: "German" },
      fieldTypes: ["textarea"] as const,
      kirbyTags: { image: ["alt", "caption"] },
      fields,
    });

    expect(seenInputs).not.toContain("2024");
    expect(content.body).toBe(
      "[de]Photo (image: photo.jpg alt: 2024 caption: [de]Our team)",
    );
  });

  it("keeps source text when a strategy drops a placeholder", async () => {
    const warnSpy = vi.spyOn(console, "warn").mockImplementation(() => {});
    const manglingStrategy: TranslationStrategy = {
      async execute(units) {
        return units.map((u) => u.text.replace(/<c\d+\/>\s*/g, ""));
      },
    };

    const content = { body: "Click (link: /a text: here) now" };
    const fields = {
      body: field({ type: "textarea", name: "textarea" }),
    };

    await translateContent(content, {
      strategy: manglingStrategy,
      targetLanguage: { code: "de", name: "German" },
      fieldTypes: ["textarea"] as const,
      kirbyTags: {},
      fields,
    });

    expect(content.body).toBe("Click (link: /a text: here) now");
    expect(warnSpy).toHaveBeenCalledWith(expect.stringContaining("body"));
    warnSpy.mockRestore();
  });

  it("drives an AIStrategy end-to-end through the orchestration", async () => {
    mockStreamText.mockResolvedValueOnce({
      output: Promise.resolve({ translations: ["Hallo", "Welt"] }),
    });

    const content = { title: "Hello", subtitle: "World" };
    const fields = {
      title: field({ type: "text", name: "text" }),
      subtitle: field({ type: "text", name: "text" }),
    };

    await translateContent(content, {
      strategy: new AIStrategy(),
      targetLanguage: { code: "de", name: "German" },
      fieldTypes: ["text"] as const,
      kirbyTags: {},
      fields,
    });

    expect(content).toEqual({ title: "Hallo", subtitle: "Welt" });
    expect(mockStreamText).toHaveBeenCalledOnce();
  });

  it("restores the KirbyTag when a strategy pads a placeholder with a space", async () => {
    const paddingStrategy: TranslationStrategy = {
      async execute(units) {
        // A stricter AI reformats the placeholder it was handed.
        return units.map(() => "Klick <c0 /> jetzt");
      },
    };

    const content = { body: "Click (link: /a text: here) now" };

    await translateContent(content, {
      strategy: paddingStrategy,
      targetLanguage: { code: "de", name: "German" },
      fieldTypes: ["textarea"] as const,
      kirbyTags: {},
      fields: { body: field({ type: "textarea", name: "textarea" }) },
    });

    expect(content.body).toBe("Klick (link: /a text: here) jetzt");
  });
});
