import type {
  TranslationExecutionOptions,
  TranslationOutcome,
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

    // Pre-filled so a response that answers for fewer units than it was given
    // still names a reason for the rest.
    const results: TranslationOutcome[] = units.map(() => ({
      reason: "missing translation",
    }));

    if (units.length > 0) {
      const response = await api.post<{
        texts: string[];
        rejected?: { index: number; reason: string }[];
      }>(TRANSLATE_BATCH_API_ROUTE, {
        sourceLanguage: options.sourceLanguage?.code,
        targetLanguage: options.targetLanguage.code,
        texts: units.map((unit) => unit.text),
      });

      // The route answers for every unit, handing back the source text for one
      // it dropped, so a rejection is invisible in `texts` alone.
      const reasons = new Map(
        response.rejected?.map(({ index, reason }) => [index, reason]) ?? [],
      );

      response.texts.forEach((text, index) => {
        const reason = reasons.get(index);
        if (reason !== undefined) {
          results[index] = { reason };
          return;
        }
        // A `content-translator.translate:after` hook runs after the route's
        // own blank check, so a blank can still arrive here.
        results[index] = text.trim() ? text : { reason: "empty translation" };
      });
    }

    return results;
  }
}
