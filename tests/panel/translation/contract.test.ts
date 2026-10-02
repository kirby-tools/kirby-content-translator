import { readFileSync } from "node:fs";
import { join } from "node:path";
import { Ajv } from "ajv";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { translateUnits } from "../../../src/panel/translation/dispatch";
import { DeepLStrategy } from "../../../src/panel/translation/strategies";
import { isUntranslatable } from "../../../src/panel/translation/untranslatable";
import { REQUIRED_COPILOT_API_VERSION } from "../../../src/panel/utils/copilot-contract";

vi.mock("../../../src/panel/utils/copilot", () => ({
  resolveCopilot: vi.fn(),
}));

const mockApiPost = vi.fn();

vi.mock("kirbyuse", async () => {
  const { baseKirbyuseMock } = await import("../helpers/mock-kirbyuse");
  return {
    ...baseKirbyuseMock(),
    useApi: () => ({ post: mockApiPost }),
  };
});

beforeEach(() => {
  mockApiPost.mockReset();
});

interface TranslationContract {
  untranslatableCases: { sourceText: string; isUntranslatable: boolean }[];
  rejectionCases: {
    sourceText: string;
    translation: string | number | null;
    reason: string;
  }[];
  translateUnitsRouteCase: {
    texts: string[];
    translations: string[];
    response: {
      texts: string[];
      rejections: {
        index: number;
        reason: string;
        expectedIndexes?: number[];
        actualIndexes?: number[];
      }[];
    };
  };
}

const contract = JSON.parse(
  readFileSync(
    join(import.meta.dirname, "../../fixtures/contract.json"),
    "utf8",
  ),
) as TranslationContract;

describe("translation contract", () => {
  it("validates against contract.schema.json", () => {
    const schema = JSON.parse(
      readFileSync(
        join(import.meta.dirname, "../../fixtures/contract.schema.json"),
        "utf8",
      ),
    );

    const ajv = new Ajv({ allowUnionTypes: true });
    ajv.validate(schema, contract);
    expect(ajv.errors).toBeNull();
  });

  it.each(contract.untranslatableCases)(
    "evaluates isUntranslatable('$sourceText') as $isUntranslatable",
    ({ sourceText, isUntranslatable: expected }) => {
      expect(isUntranslatable(sourceText)).toBe(expected);
    },
  );

  it.each(contract.rejectionCases)(
    "rejects $sourceText as $reason",
    async ({ sourceText, translation, reason }) => {
      const { rejections } = await translateUnits(
        [{ text: sourceText, fieldKey: "body" }],
        { execute: async () => [translation] as unknown as string[] },
        { targetLanguage: { code: "de", name: "Deutsch" } },
      );

      expect(rejections).toMatchObject([{ fieldKey: "body", reason }]);
    },
  );

  it("reads texts and rejections from the translate-units route", async () => {
    const { texts, response } = contract.translateUnitsRouteCase;
    mockApiPost.mockResolvedValueOnce(response);

    const outcomes = await new DeepLStrategy().execute(
      texts.map((text) => ({ text })),
      { targetLanguage: { code: "de", name: "Deutsch" } },
    );

    expect(outcomes).toEqual([
      "Hallo",
      {
        reason: "placeholder mismatch",
        expectedIndexes: [0],
        actualIndexes: [],
      },
    ]);
  });
});

describe("copilot seam contract", () => {
  it("requires the seam version pinned in the shared fixture", () => {
    const seamContract = JSON.parse(
      readFileSync(
        join(import.meta.dirname, "../../fixtures/copilot-seam-contract.json"),
        "utf8",
      ),
    ) as { apiVersion: number };

    expect(REQUIRED_COPILOT_API_VERSION).toBe(seamContract.apiVersion);
  });
});
