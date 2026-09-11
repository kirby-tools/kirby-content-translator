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

    const outcomes: TranslationOutcome[] = [];

    if (units.length > 0) {
      const response = await api.post<{
        texts: string[];
        rejections?: {
          index: number;
          reason: string;
          expectedIndexes?: number[];
          actualIndexes?: number[];
        }[];
      }>(TRANSLATE_BATCH_API_ROUTE, {
        sourceLanguage: options.sourceLanguage?.code,
        targetLanguage: options.targetLanguage.code,
        texts: units.map((unit) => unit.text),
      });

      // The route answers for every unit, handing back the source text for a
      // rejected one, so a rejection is invisible in `texts` alone.
      const rejections = new Map(
        response.rejections?.map((rejection) => [rejection.index, rejection]) ??
          [],
      );

      response.texts.forEach((text, index) => {
        const rejection = rejections.get(index);
        if (rejection) {
          const { reason, expectedIndexes, actualIndexes } = rejection;
          outcomes[index] = { reason, expectedIndexes, actualIndexes };
          return;
        }
        // A `content-translator.translate:after` hook runs after the route's
        // own blank check, so a blank can still arrive here.
        outcomes[index] = text.trim() ? text : { reason: "empty translation" };
      });
    }

    return outcomes;
  }
}
