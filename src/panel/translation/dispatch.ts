import type {
  BatchTranslationResult,
  TranslationExecutionOptions,
  TranslationStrategy,
  TranslationUnit,
} from "./types";
import { PLACEHOLDER_PATTERN } from "./kirby-text";
import { isUntranslatable } from "./untranslatable";

/**
 * Sends only the units worth translating to the strategy, splicing source text
 * into the skipped slots so callers keep a 1:1 mapping with `units`.
 *
 * Also enforces the KirbyTag placeholder invariant here rather than inside a
 * strategy, so a new strategy cannot forget it. `DeepLStrategy` is the
 * exception: the PHP tier has already adjudicated its units, so its rejections
 * arrive as outcomes and never reach the check.
 *
 * The counts travel with the texts because a translation may legitimately equal
 * its source text, so no caller can recover them by diffing the result.
 */
export async function translateUnits(
  units: TranslationUnit[],
  strategy: TranslationStrategy,
  options: TranslationExecutionOptions,
): Promise<UnitTranslationResult> {
  const texts = units.map((unit) => unit.text);

  const translatableIndexes: number[] = [];
  const translatableUnits: TranslationUnit[] = [];

  for (const [index, unit] of units.entries()) {
    if (!isUntranslatable(unit.text)) {
      translatableIndexes.push(index);
      translatableUnits.push(unit);
    }
  }

  if (!translatableUnits.length) {
    return { texts, translatableCount: 0, translatedCount: 0 };
  }

  const translations = await strategy.execute(translatableUnits, options);

  let translatedCount = 0;

  for (const [position, index] of translatableIndexes.entries()) {
    const unit = translatableUnits[position]!;
    const outcome = translations[position];

    // `isUntranslatable` dropped the blank sources, so nothing that reaches a
    // strategy can legitimately come back blank.
    if (typeof outcome !== "string" || !outcome.trim()) {
      const reason =
        typeof outcome === "object" ? outcome.reason : "missing translation";
      console.warn(
        `Rejected "${unit.fieldKey}" (${options.targetLanguage.code}): ${reason}. Keeping source text.`,
      );
      continue;
    }

    const expected = placeholderIndexes(unit.text);
    const actual = placeholderIndexes(outcome);

    if (expected !== actual) {
      console.warn(
        `Rejected "${unit.fieldKey}" (${options.targetLanguage.code}): placeholder mismatch, expected ${expected || "none"}, got ${actual || "none"}. Keeping source text.`,
      );
      continue;
    }

    texts[index] = outcome;
    translatedCount++;
  }

  return {
    texts,
    translatableCount: translatableUnits.length,
    translatedCount,
  };
}

/**
 * Sorted `<cN/>` indexes as one comparable string. Counting alone would accept
 * `<c0/> <c0/>` for a source holding `<c0/> <c1/>`, and `restore()` would then
 * rebuild tag 0 twice and drop tag 1.
 */
function placeholderIndexes(text: string): string {
  return [...text.matchAll(PLACEHOLDER_PATTERN)]
    .map((match) => Number(match[1]))
    .sort((a, b) => a - b)
    .join(",");
}
