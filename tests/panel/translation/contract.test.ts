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
  untranslatableCases: { text: string; isUntranslatable: boolean }[];
  rejectionReasons: {
    reason: string;
    sourceText: string;
    answer: string | number | null;
  }[];
  translateUnitsRouteResponse: {
    keys: string[];
    rejectionKeys: string[];
    optionalRejectionKeys: string[];
  };
}

const contract = JSON.parse(
  readFileSync(
    join(import.meta.dirname, "../../fixtures/contract.json"),
    "utf8",
  ),
) as TranslationContract;

// Shared with `ContractTest.php` – a one-sided edit fails here first.
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
    "evaluates isUntranslatable('$text') as $isUntranslatable",
    ({ text, isUntranslatable: expected }) => {
      expect(isUntranslatable(text)).toBe(expected);
    },
  );

  it.each(contract.rejectionReasons)(
    "rejects $sourceText as $reason",
    async ({ reason, sourceText, answer }) => {
      const { rejections } = await translateUnits(
        [{ text: sourceText, fieldKey: "body" }],
        { execute: async () => [answer] as unknown as string[] },
        { targetLanguage: { code: "de", name: "Deutsch" } },
      );

      expect(rejections).toMatchObject([{ fieldKey: "body", reason }]);
    },
  );

  it("reads texts and rejections from the translate-units route", async () => {
    const [textsKey, rejectionsKey] = contract.translateUnitsRouteResponse.keys;
    const [indexKey, reasonKey] =
      contract.translateUnitsRouteResponse.rejectionKeys;

    mockApiPost.mockResolvedValueOnce({
      [textsKey!]: ["Hello", "Welt"],
      [rejectionsKey!]: [
        { [indexKey!]: 0, [reasonKey!]: "placeholder mismatch" },
      ],
    });

    const outcomes = await new DeepLStrategy().execute(
      [
        { text: "Hello", fieldKey: "title" },
        { text: "World", fieldKey: "subtitle" },
      ],
      { targetLanguage: { code: "de", name: "Deutsch" } },
    );

    expect(outcomes).toEqual([{ reason: "placeholder mismatch" }, "Welt"]);
  });

  it("reads the placeholder indexes from the translate-units route", async () => {
    const [textsKey, rejectionsKey] = contract.translateUnitsRouteResponse.keys;
    const [indexKey, reasonKey] =
      contract.translateUnitsRouteResponse.rejectionKeys;
    const [expectedKey, actualKey] =
      contract.translateUnitsRouteResponse.optionalRejectionKeys;

    mockApiPost.mockResolvedValueOnce({
      [textsKey!]: ["Read <c0/>"],
      [rejectionsKey!]: [
        {
          [indexKey!]: 0,
          [reasonKey!]: "placeholder mismatch",
          [expectedKey!]: [0],
          [actualKey!]: [],
        },
      ],
    });

    const outcomes = await new DeepLStrategy().execute(
      [{ text: "Read <c0/>", fieldKey: "body" }],
      { targetLanguage: { code: "de", name: "Deutsch" } },
    );

    expect(outcomes).toEqual([
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
