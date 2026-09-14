import { readFileSync } from "node:fs";
import { join } from "node:path";
import { Ajv } from "ajv";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { translateUnits } from "../../../src/panel/translation/dispatch";
import {
  PLACEHOLDER_PATTERN,
  splitKirbyText,
} from "../../../src/panel/translation/kirby-text";
import { DeepLStrategy } from "../../../src/panel/translation/strategies";
import {
  MAX_BATCH_SIZE,
  MAX_CHARS_PER_BATCH,
} from "../../../src/panel/translation/strategies/ai";
import { isUntranslatable } from "../../../src/panel/translation/untranslatable";
import { REQUIRED_COPILOT_API_VERSION } from "../../../src/panel/utils/copilot-contract";

vi.mock("../../../src/panel/utils/copilot", () => ({
  resolveCopilot: vi.fn(),
}));

const mockApiPost = vi.fn();

vi.mock("kirbyuse", () => ({
  useApi: () => ({ post: mockApiPost }),
}));

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
  placeholder: { format: string; indexBase: number };
  batching: { maxBatchSize: number; maxSizePerBatch: number };
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

      expect(rejections).toHaveLength(1);
      expect(rejections[0]!.reason).toBe(reason);
    },
  );

  it("reads texts and rejections from the translate-units route", async () => {
    const [textsKey, rejectionsKey] = contract.translateUnitsRouteResponse.keys;
    const [indexKey, reasonKey] = contract.translateUnitsRouteResponse.rejectionKeys;

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
    const [indexKey, reasonKey] = contract.translateUnitsRouteResponse.rejectionKeys;
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

  it("emits placeholders in the contract format", () => {
    const { placeholder } = contract;
    const { unitTexts } = splitKirbyText("(link: /a)", {});

    expect(unitTexts[0]).toBe(
      placeholder.format.replace("{n}", String(placeholder.indexBase)),
    );
    expect(unitTexts[0]!.match(PLACEHOLDER_PATTERN)).toHaveLength(1);
  });

  it("caps AI batches at the contract limits", () => {
    expect(MAX_BATCH_SIZE).toBe(contract.batching.maxBatchSize);
    expect(MAX_CHARS_PER_BATCH).toBe(contract.batching.maxSizePerBatch);
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
