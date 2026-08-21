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

    // Units the endpoint does not answer for keep their source text.
    const results: string[] = units.map((unit) => unit.text);

    if (units.length > 0) {
      const response = await api.post<{ texts: string[] }>(
        TRANSLATE_BATCH_API_ROUTE,
        {
          sourceLanguage: options.sourceLanguage?.code,
          targetLanguage: options.targetLanguage.code,
          texts: units.map((unit) => unit.text),
        },
      );

      response.texts.forEach((text, index) => {
        // Mirrors `AIStrategy`: a blank response is a failed unit, not a
        // translation that happens to hold nothing. Reachable past the route's
        // own blank check, since `content-translator.translate:after` runs
        // after it.
        if (text.trim()) results[index] = text;
      });
    }

    return results;
  }
}
