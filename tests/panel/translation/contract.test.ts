import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, it, vi } from "vitest";
import {
  PLACEHOLDER_PATTERN,
  splitKirbyText,
} from "../../../src/panel/translation/kirby-text";
import {
  MAX_BATCH_SIZE,
  MAX_CHARS_PER_BATCH,
} from "../../../src/panel/translation/strategies/ai";
import { shouldSkipTranslation } from "../../../src/panel/translation/utils";

vi.mock("../../../src/panel/utils/copilot", () => ({
  resolveCopilot: vi.fn(),
}));

interface TranslationContract {
  skipCases: { text: string; skip: boolean }[];
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
  it.each(contract.skipCases)(
    "evaluates skip('$text') as $skip",
    ({ text, skip }) => {
      expect(shouldSkipTranslation(text)).toBe(skip);
    },
  );

  it("emits placeholders in the contract format", () => {
    const { placeholder } = contract;
    const { fragments } = splitKirbyText("(link: /a)", {});

    expect(fragments[0]).toBe(
      placeholder.format.replace("{n}", String(placeholder.indexBase)),
    );
    expect(fragments[0]!.match(PLACEHOLDER_PATTERN)).toHaveLength(1);
  });

  it("caps AI batches at the contract limits", () => {
    expect(MAX_BATCH_SIZE).toBe(contract.batching.maxBatchSize);
    expect(MAX_CHARS_PER_BATCH).toBe(contract.batching.maxSizePerBatch);
  });
});
