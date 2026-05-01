import type {
  TranslationExecutionOptions,
  TranslationStrategy,
  TranslationUnit,
} from "../types";
import { useApi } from "kirbyuse";
import pAll from "p-all";
import {
  TRANSLATE_API_ROUTE,
  TRANSLATE_BATCH_API_ROUTE,
} from "../../constants";

export interface DeepLStrategyOptions {
  /**
   * Concurrency for parallel API requests.
   * @default 5
   */
  concurrency?: number;
}

/**
 * DeepL translation strategy.
 *
 * @remarks
 * Groups translations by mode and executes them against the matching
 * endpoints, honouring `concurrency` for parallel requests.
 */
export class DeepLStrategy implements TranslationStrategy {
  private concurrency: number;

  constructor(options: DeepLStrategyOptions = {}) {
    this.concurrency = options.concurrency ?? 5;
  }

  async execute(
    units: TranslationUnit[],
    options: TranslationExecutionOptions,
  ) {
    const api = useApi();
    const { signal } = options;

    // Initialize results array with original texts (fallback for empty)
    const results: string[] = units.map((unit) => unit.text);

    // Partition by mode, carrying original indices for write-back
    const batchUnits: { unit: TranslationUnit; originalIndex: number }[] = [];
    const singleUnits: { unit: TranslationUnit; originalIndex: number }[] = [];

    for (const [originalIndex, unit] of units.entries()) {
      if (unit.mode === "batch") batchUnits.push({ unit, originalIndex });
      else if (unit.mode === "single")
        singleUnits.push({ unit, originalIndex });
    }

    // Execute batch translation (single API call)
    if (batchUnits.length > 0 && !signal?.aborted) {
      const response = await api.post<{ texts: string[] }>(
        TRANSLATE_BATCH_API_ROUTE,
        {
          sourceLanguage: options.sourceLanguage?.code,
          targetLanguage: options.targetLanguage.code,
          texts: batchUnits.map(({ unit }) => unit.text),
        },
      );

      for (const [i, { originalIndex }] of batchUnits.entries()) {
        results[originalIndex] = response.texts[i]!;
      }
    }

    // Execute single translations (parallel with concurrency limit)
    if (singleUnits.length > 0 && !signal?.aborted) {
      const singleResults = await pAll(
        singleUnits.map(({ unit, originalIndex }) => async () => {
          const response = await api.post<{ text: string }>(
            TRANSLATE_API_ROUTE,
            {
              sourceLanguage: options.sourceLanguage?.code,
              targetLanguage: options.targetLanguage.code,
              text: unit.text,
            },
          );
          return { originalIndex, text: response.text };
        }),
        { concurrency: this.concurrency, signal },
      );

      for (const { originalIndex, text } of singleResults) {
        results[originalIndex] = text;
      }
    }

    return results;
  }
}
