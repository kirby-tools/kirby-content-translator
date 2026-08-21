import type {
  TranslationExecutionOptions,
  TranslationStrategy,
  TranslationUnit,
} from "../types";
import { useApi } from "kirbyuse";
import { TRANSLATE_BATCH_API_ROUTE } from "../../constants";

/**
 * Translates all units in a single batch API call.
 */
export class DeepLStrategy implements TranslationStrategy {
  async execute(
    units: TranslationUnit[],
    options: TranslationExecutionOptions,
  ) {
    const api = useApi();

    const results: (string | null)[] = units.map(() => null);

    if (units.length > 0) {
      const response = await api.post<{
        texts: string[];
        rejectedIndexes?: number[];
      }>(TRANSLATE_BATCH_API_ROUTE, {
        sourceLanguage: options.sourceLanguage?.code,
        targetLanguage: options.targetLanguage.code,
        texts: units.map((unit) => unit.text),
      });

      // The route answers for every unit, handing back the source text for one
      // it dropped, so a rejection is invisible in `texts` alone. Absent when a
      // cached Panel bundle talks to an older server, which then reports as it
      // did before: silently.
      const rejectedIndexes = new Set(response.rejectedIndexes ?? []);

      response.texts.forEach((text, index) => {
        if (rejectedIndexes.has(index)) return;
        // Mirrors `AIStrategy`: a blank response is a failed unit, not a
        // translation that happens to hold nothing. Reachable past the route's
        // own blank check, since `content-translator.translate:after` runs
        // after it.
        results[index] = text.trim() ? text : null;
      });
    }

    return results;
  }
}
