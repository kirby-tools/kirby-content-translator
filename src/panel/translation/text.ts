import type { PanelLanguage, PanelLanguageInfo } from "kirby-types";
import type { TranslationProvider } from "../types";
import type { ContentTranslationResult } from "./types";
import { translateUnits } from "./dispatch";
import { AIStrategy, DeepLStrategy } from "./strategies";

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
    provider,
    targetLanguage,
    sourceLanguage,
    systemPrompt,
  }: {
    provider: TranslationProvider;
    targetLanguage: PanelLanguageInfo | PanelLanguage;
    sourceLanguage?: PanelLanguageInfo | PanelLanguage;
    systemPrompt?: string;
  },
): Promise<{ text: string; result: ContentTranslationResult }> {
  const strategy =
    provider === "ai" ? new AIStrategy({ systemPrompt }) : new DeepLStrategy();
  const { texts, translatableCount, translatedCount } = await translateUnits(
    [{ text }],
    strategy,
    { sourceLanguage, targetLanguage },
  );

  return {
    text: texts[0] ?? text,
    result: { translatableCount, translatedCount },
  };
}
