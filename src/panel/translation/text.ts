import type { PanelLanguage, PanelLanguageInfo } from "kirby-types";
import type { TranslationProvider } from "../types";
import { AIStrategy, DeepLStrategy } from "./strategies";

/**
 * Translates a single ad-hoc text (e.g. a model title), falling back to the
 * source text when the strategy returns no result.
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
): Promise<string> {
  const strategy =
    provider === "ai" ? new AIStrategy({ systemPrompt }) : new DeepLStrategy();
  const results = await strategy.execute([{ text }], {
    sourceLanguage,
    targetLanguage,
  });
  return results[0] ?? text;
}
