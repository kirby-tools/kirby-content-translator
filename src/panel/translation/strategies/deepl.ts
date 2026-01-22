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
  TRANSLATE_KIRBYTEXT_API_ROUTE,
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
 * Groups translations by mode and executes them with appropriate endpoints.
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

    // Build index map to preserve original order
    const indexMap = new Map<TranslationUnit, number>();
    for (const [index, unit] of units.entries()) {
      indexMap.set(unit, index);
    }

    // Initialize results array with original texts (fallback for empty)
    const results: string[] = units.map((unit) => unit.text);

    // Group units by mode
    const batchUnits = units.filter((unit) => unit.mode === "batch");
    const kirbytextUnits = units.filter((unit) => unit.mode === "kirbytext");
    const singleUnits = units.filter((unit) => unit.mode === "single");

    // Execute batch translation (single API call)
    if (batchUnits.length > 0 && !signal?.aborted) {
      const response = await api.post<{ texts: string[] }>(
        TRANSLATE_BATCH_API_ROUTE,
        {
          sourceLanguage: options.sourceLanguage?.code,
          targetLanguage: options.targetLanguage.code,
          texts: batchUnits.map((unit) => unit.text),
        },
      );

      for (const [i, unit] of batchUnits.entries()) {
        results[indexMap.get(unit)!] = response.texts[i]!;
      }
    }

    // Execute kirbytext translations (parallel with concurrency limit)
    if (kirbytextUnits.length > 0 && !signal?.aborted) {
      const kirbytextResults = await pAll(
        kirbytextUnits.map((unit) => async () => {
          const response = await api.post<{ text: string }>(
            TRANSLATE_KIRBYTEXT_API_ROUTE,
            {
              sourceLanguage: options.sourceLanguage?.code,
              targetLanguage: options.targetLanguage.code,
              text: unit.text,
              kirbyTags: options.kirbyTags,
            },
          );
          return { unit, text: response.text };
        }),
        { concurrency: this.concurrency, signal },
      );

      for (const { unit, text } of kirbytextResults) {
        results[indexMap.get(unit)!] = text;
      }
    }

    // Execute single translations (parallel with concurrency limit)
    if (singleUnits.length > 0 && !signal?.aborted) {
      const singleResults = await pAll(
        singleUnits.map((unit) => async () => {
          const response = await api.post<{ text: string }>(
            TRANSLATE_API_ROUTE,
            {
              sourceLanguage: options.sourceLanguage?.code,
              targetLanguage: options.targetLanguage.code,
              text: unit.text,
            },
          );
          return { unit, text: response.text };
        }),
        { concurrency: this.concurrency, signal },
      );

      for (const { unit, text } of singleResults) {
        results[indexMap.get(unit)!] = text;
      }
    }

    return results;
  }
}
