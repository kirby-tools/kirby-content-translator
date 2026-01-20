import type {
  TranslationExecutionOptions,
  TranslationStrategy,
  TranslationUnit,
} from "../types";
import * as z from "zod/mini";
import { resolveCopilot } from "../../utils/copilot";

const MAX_BATCH_SIZE = 50;
const MAX_CHARS_PER_BATCH = 50_000;

const TRANSLATION_SYSTEM_PROMPT = `

You are a professional translator for a Kirby CMS website.

TASK:
- Translate each text accurately while preserving meaning, tone, and style
- Return translations in exact input order
- No explanations or commentary

PRESERVE UNCHANGED:
- HTML tags and attributes
- Markdown syntax (links, formatting, headings)
- URLs and file paths
- Placeholders: {{var}}, {0}, %s
- Empty strings → return empty strings
- Kirby tags by default (see below)

KIRBY TAGS:
Kirby tags use the format (tagname: value attr: value). Examples:
- (link: /about text: About us title: Learn more)
- (image: photo.jpg alt: A sunset caption: Beautiful view)
- (email: hello@example.com text: Contact us)
- (file: document.pdf text: Download)

By default, preserve all Kirby tags exactly as they appear. If a kirby_tags config is provided in the request, translate ONLY the specified attributes for those tag types.

TRANSLATION GUIDELINES:
- Proper nouns: only translate if an established translation exists
- Technical terms: keep original if no standard translation exists
`;

/**
 * AI translation strategy using Kirby Copilot.
 *
 * @remarks
 * Requires the Kirby Copilot plugin to be installed and configured.
 * Uses structured outputs with Zod schemas for reliable JSON responses.
 */
export class AIStrategy implements TranslationStrategy {
  async execute(
    units: TranslationUnit[],
    options: TranslationExecutionOptions,
  ): Promise<string[]> {
    const copilot = resolveCopilot();
    if (!copilot) {
      throw new Error("Kirby Copilot plugin is required for AI translations");
    }

    const { loadAISDK, streamText } = copilot;
    const { Output } = await loadAISDK();

    // Initialize results with original texts (fallback)
    const results: string[] = units.map((unit) => unit.text);

    // Chunk all units together (no mode-based grouping needed)
    const chunks = chunkUnits(units);

    for (const chunk of chunks) {
      try {
        const schema = z.strictObject({
          translations: z.array(z.string()),
        });

        const { output: finalOutput } = await streamText({
          userPrompt: buildTranslationPrompt(
            chunk.map((unit) => unit.text),
            options,
          ),
          systemPrompt: TRANSLATION_SYSTEM_PROMPT,
          output: Output.object({ schema }),
        });

        // Prevent unhandled rejection
        (finalOutput as Promise<unknown>).catch(() => {});

        const result = await finalOutput;

        // Map translations back to results array
        for (const [i, unit] of chunk.entries()) {
          const translation = result?.translations?.[i];
          if (translation) {
            const originalIndex = units.indexOf(unit);
            results[originalIndex] = translation;
          }
        }
      } catch (error) {
        console.error(
          `Failed to translate chunk (${chunk.map((unit) => unit.fieldKey).join(", ")})`,
        );
        console.error(error);
        // Keep original texts (already in results)
      }
    }

    return results;
  }
}

function buildTranslationPrompt(
  texts: string[],
  options: TranslationExecutionOptions,
) {
  const { sourceLanguage, targetLanguage, kirbyTags } = options;
  const hasKirbyTags = kirbyTags && Object.keys(kirbyTags).length > 0;

  const lines = [
    // 1. Instructions
    `Translate ${texts.length} text(s) ${sourceLanguage ? `from ${sourceLanguage} ` : ""}to ${targetLanguage}.`,
    // 2. Kirby tags config
    hasKirbyTags &&
      `
<kirby_tags>
${JSON.stringify(kirbyTags, undefined, 2)}
</kirby_tags>

For these Kirby tag types, translate ONLY the listed attributes. Preserve all other attributes unchanged.
`.trim(),
    // 3. Texts to translate
    `
<texts>
${texts.map((text, i) => `<item index="${i}">${text}</item>`).join("\n")}
</texts>
`.trim(),
  ];

  return lines.filter(Boolean).join("\n\n").trim();
}

function chunkUnits<T extends TranslationUnit>(units: T[]): T[][] {
  const chunks: T[][] = [];
  let currentChunk: T[] = [];
  let currentSize = 0;

  for (const unit of units) {
    if (
      currentChunk.length >= MAX_BATCH_SIZE ||
      currentSize + unit.text.length > MAX_CHARS_PER_BATCH
    ) {
      if (currentChunk.length > 0) chunks.push(currentChunk);
      currentChunk = [];
      currentSize = 0;
    }
    currentChunk.push(unit);
    currentSize += unit.text.length;
  }

  if (currentChunk.length > 0) chunks.push(currentChunk);
  return chunks;
}
