import type {
  TranslationExecutionOptions,
  TranslationStrategy,
  TranslationUnit,
} from "../types";
import * as z from "zod/mini";
import { resolveCopilot } from "../../utils/copilot";
import { REQUIRED_COPILOT_API_VERSION } from "../../utils/copilot-contract";

export interface AIStrategyOptions {
  /**
   * System prompt for the AI translation. Resolved server-side from
   * `johannschopplich.content-translator.ai.systemPrompt` with a Copilot-side
   * default fallback, then handed in from `usePluginContext()`.
   */
  systemPrompt?: string;
}

// Pinned by `tests/fixtures/contract.json`. The size unit intentionally
// differs (UTF-16 code units here, bytes in PHP) – only the values are shared.
export const MAX_BATCH_SIZE = 50;
export const MAX_CHARS_PER_BATCH = 100_000;

/**
 * Requires the Kirby Copilot plugin, which owns the provider credentials –
 * this plugin never talks to an AI provider directly.
 */
export class AIStrategy implements TranslationStrategy {
  private systemPrompt?: string;

  constructor(options: AIStrategyOptions = {}) {
    this.systemPrompt = options.systemPrompt;
  }

  async execute(
    units: TranslationUnit[],
    options: TranslationExecutionOptions,
  ): Promise<(string | null)[]> {
    const copilot = resolveCopilot();
    if (!copilot) {
      throw new Error("Kirby Copilot plugin is required for AI translations");
    }

    // Composer conflict rules cannot guard manual plugin installs,
    // so the seam version is checked at runtime as well.
    if ((copilot.apiVersion ?? 1) < REQUIRED_COPILOT_API_VERSION) {
      throw new Error(
        "The installed Kirby Copilot version does not support AI translations anymore – please update the Kirby Copilot plugin.",
      );
    }

    const { streamText } = copilot;

    const results: (string | null)[] = units.map(() => null);
    let translatedCount = 0;
    let hasProviderAnswer = false;
    let lastReason: string | undefined;

    // The original index travels with each unit so a failed chunk leaves the
    // other results in place.
    const chunks = chunkUnitsWithIndices(units);

    for (const chunk of chunks) {
      try {
        const schema = z.strictObject({
          translations: z.array(z.string()).check(z.length(chunk.length)),
        });

        const { output: finalOutput } = await streamText({
          userPrompt: buildTranslationPrompt(
            chunk.map(({ unit }) => unit.text),
            options,
          ),
          systemPrompt: this.systemPrompt?.trim(),
          outputSchema: schema,
        });

        // Prevent unhandled rejection.
        finalOutput.catch(() => {});

        const result = await finalOutput;
        hasProviderAnswer = true;

        for (const [i, { unit, originalIndex }] of chunk.entries()) {
          const translation = result?.translations?.[i];
          if (!translation?.trim()) {
            lastReason = "empty or non-string translation";
            console.warn(
              `Empty translation for "${unit.fieldKey}". Keeping source text.`,
            );
            continue;
          }

          results[originalIndex] = translation;
          translatedCount++;
        }
      } catch (error) {
        lastReason = error instanceof Error ? error.message : String(error);
        console.error(
          `Failed to translate chunk (${chunk.map(({ unit }) => unit.fieldKey).join(", ")})`,
        );
        console.error(error);
      }
    }

    // Mirrors `CopilotAIStrategy`: a run where the provider produced nothing
    // usable is an error, not a silent no-op that reports success. A provider
    // that answered gets its own reason, because `lastReason` then holds a
    // message this file wrote rather than anything the provider said.
    if (translatedCount === 0) {
      const reason = hasProviderAnswer
        ? `the provider answered, but all ${units.length} translations were unusable`
        : (lastReason ?? "unknown error");

      throw new Error(
        `AI translation failed for all ${units.length} texts: ${reason}`,
      );
    }

    return results;
  }
}

function buildTranslationPrompt(
  texts: string[],
  options: TranslationExecutionOptions,
) {
  const { sourceLanguage, targetLanguage } = options;
  const sourceName = sourceLanguage?.name ?? "the source language";
  const targetName = targetLanguage.name;

  const payload = JSON.stringify({
    sourceLanguage: sourceName,
    targetLanguage: targetName,
    texts,
  });

  return `Translate each string in the \`texts\` array from ${sourceName} to ${targetName}.\n\n${payload}`;
}

function chunkUnitsWithIndices<T extends TranslationUnit>(
  units: T[],
): { unit: T; originalIndex: number }[][] {
  const chunks: { unit: T; originalIndex: number }[][] = [];
  let currentChunk: { unit: T; originalIndex: number }[] = [];
  let currentSize = 0;

  for (const [index, unit] of units.entries()) {
    if (
      currentChunk.length >= MAX_BATCH_SIZE ||
      currentSize + unit.text.length > MAX_CHARS_PER_BATCH
    ) {
      if (currentChunk.length > 0) chunks.push(currentChunk);
      currentChunk = [];
      currentSize = 0;
    }
    currentChunk.push({ unit, originalIndex: index });
    currentSize += unit.text.length;
  }

  if (currentChunk.length > 0) chunks.push(currentChunk);
  return chunks;
}
