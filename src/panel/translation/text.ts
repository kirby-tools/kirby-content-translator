import type { PanelLanguage, PanelLanguageInfo } from "kirby-types";
import type { ContentTranslationResult, TranslationStrategy } from "./types";
import { translateUnits } from "./dispatch";
import { reportRejections } from "./result";

/**
 * Translates a single ad-hoc text (e.g. a model title), falling back to the
 * source text when the strategy returns no result.
 *
 * The result travels alongside the text so callers can fold an ad-hoc
 * translation into the outcome they report for a whole run.
 */
export async function translateText(
  text: string,
  {
    strategy,
    targetLanguage,
    sourceLanguage,
    fieldKey,
  }: {
    strategy: TranslationStrategy;
    targetLanguage: PanelLanguageInfo | PanelLanguage;
    sourceLanguage?: PanelLanguageInfo | PanelLanguage;
    /** Names the text in a rejection, since there is no field to name it. */
    fieldKey: string;
  },
): Promise<{ text: string; result: ContentTranslationResult }> {
  const { texts, translatableCount, translatedCount, rejections } =
    await translateUnits([{ text, fieldKey }], strategy, {
      sourceLanguage,
      targetLanguage,
    });

  return {
    text: texts[0] ?? text,
    result: { translatableCount, translatedCount, rejections },
  };
}

/**
 * Translates the title, returning `undefined` rather than the source text
 * for a rejected title: writing that would overwrite a manually translated
 * target title and re-derive its slug. An untranslatable title carries no
 * rejection and still comes back.
 */
export async function translateTitle(
  title: string,
  {
    strategy,
    targetLanguage,
    sourceLanguage,
  }: {
    strategy: TranslationStrategy;
    targetLanguage: PanelLanguageInfo | PanelLanguage;
    sourceLanguage?: PanelLanguageInfo | PanelLanguage;
  },
): Promise<{ text?: string; result: ContentTranslationResult }> {
  let translatedTitle: { text: string; result: ContentTranslationResult };

  try {
    translatedTitle = await translateText(title, {
      strategy,
      targetLanguage,
      sourceLanguage,
      fieldKey: "title",
    });
  } catch (error) {
    // A failed request is reported as a rejected title rather than thrown, so
    // it cannot fail a run whose content is already translated.
    translatedTitle = {
      text: title,
      result: {
        translatableCount: 1,
        translatedCount: 0,
        rejections: [
          {
            fieldKey: "title",
            reason: "request failed",
            detail: error instanceof Error ? error.message : String(error),
          },
        ],
      },
    };
  }

  reportRejections(translatedTitle.result, targetLanguage);

  return {
    text:
      translatedTitle.result.rejections.length === 0
        ? translatedTitle.text
        : undefined,
    result: translatedTitle.result,
  };
}
