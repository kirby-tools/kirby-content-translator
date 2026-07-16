import type {
  TranslationExecutionOptions,
  TranslationStrategy,
  TranslationUnit,
} from "../types";
import * as z from "zod/mini";
import { resolveCopilot } from "../../utils/copilot";
import { PLACEHOLDER_PATTERN } from "../kirby-text";

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
 * AI translation strategy using Kirby Copilot.
 *
 * @remarks
 * Requires the Kirby Copilot plugin to be installed and configured.
 * Uses structured outputs with Zod schemas for reliable JSON responses.
 */
export class AIStrategy implements TranslationStrategy {
  private systemPrompt?: string;

  constructor(options: AIStrategyOptions = {}) {
    this.systemPrompt = options.systemPrompt;
  }

  async execute(
    units: TranslationUnit[],
    options: TranslationExecutionOptions,
  ): Promise<string[]> {
    const copilot = resolveCopilot();
    if (!copilot) {
      throw new Error("Kirby Copilot plugin is required for AI translations");
    }

    const { signal } = options;
    const { loadAISDK, streamText } = copilot;
    const { Output } = await loadAISDK();

    // Initialize results with original texts (fallback)
    const results: string[] = units.map((unit) => unit.text);

    // Chunk units with their original indices for efficient mapping
    const chunks = chunkUnitsWithIndices(units);

    for (const chunk of chunks) {
      // Stop processing if aborted
      if (signal?.aborted) break;

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
          output: Output.object({ schema }),
        });

        // Prevent unhandled rejection
        (finalOutput as Promise<unknown>).catch(() => {});

        const result = await finalOutput;

        // Map translations back using tracked indices
        for (const [i, { unit, originalIndex }] of chunk.entries()) {
          const translation = result?.translations?.[i];
          if (translation) {
            const expectedCount = countPlaceholders(unit.text);
            const actualCount = countPlaceholders(translation);
            if (expectedCount !== actualCount) {
              console.warn(
                `Placeholder count mismatch in "${unit.fieldKey}": expected ${expectedCount}, got ${actualCount}. Keeping source text.`,
              );
              continue;
            }
            results[originalIndex] = translation;
          }
        }
      } catch (error) {
        console.error(
          `Failed to translate chunk (${chunk.map(({ unit }) => unit.fieldKey).join(", ")})`,
        );
        console.error(error);
        // Keep original texts (already in results)
      }
    }

    return results;
  }
}

function countPlaceholders(text: string): number {
  return (text.match(PLACEHOLDER_PATTERN) ?? []).length;
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
