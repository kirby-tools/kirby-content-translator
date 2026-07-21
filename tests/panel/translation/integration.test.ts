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
});
