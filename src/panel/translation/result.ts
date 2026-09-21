import type { PanelLanguage, PanelLanguageInfo } from "kirby-types";
import type { ContentTranslationResult } from "./types";

export function reportRejections(
  result: ContentTranslationResult,
  targetLanguage: PanelLanguageInfo | PanelLanguage,
  modelPath?: string,
) {
  const modelSuffix = modelPath === undefined ? "" : ` of "${modelPath}"`;

  for (const { fieldKey, reason, detail } of result.rejections) {
    console.warn(
      `Rejected "${fieldKey}"${modelSuffix} (${targetLanguage.code}): ${detail ?? reason}. Keeping source text.`,
    );
  }
}

export function mergeTranslationResults(
  results: ContentTranslationResult[],
): ContentTranslationResult {
  return {
    translatableCount: results.reduce(
      (total, result) => total + result.translatableCount,
      0,
    ),
    translatedCount: results.reduce(
      (total, result) => total + result.translatedCount,
      0,
    ),
    rejections: results.flatMap((result) => result.rejections),
  };
}
