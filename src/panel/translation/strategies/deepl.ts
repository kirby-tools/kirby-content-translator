import type {
  TranslationExecutionOptions,
  TranslationStrategy,
  TranslationUnit,
} from "../types";
import { useApi } from "kirbyuse";
import { TRANSLATE_BATCH_API_ROUTE } from "../../constants";

/**
 * DeepL translation strategy.
 *
 * @remarks
 * Translates all units in a single batch API call.
 */
export class DeepLStrategy implements TranslationStrategy {
  async execute(
    units: TranslationUnit[],
    options: TranslationExecutionOptions,
  ) {
    const api = useApi();
    const { signal } = options;

    // Initialize results array with original texts (fallback for empty)
    const results: string[] = units.map((unit) => unit.text);

    if (units.length > 0 && !signal?.aborted) {
      const response = await api.post<{ texts: string[] }>(
        TRANSLATE_BATCH_API_ROUTE,
        {
          sourceLanguage: options.sourceLanguage?.code,
          targetLanguage: options.targetLanguage.code,
          texts: units.map((unit) => unit.text),
        },
      );

      for (const [index] of units.entries()) {
        results[index] = response.texts[index]!;
      }
    }

    return results;
  }
}
