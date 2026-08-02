import type {
  TranslationExecutionOptions,
  TranslationStrategy,
  TranslationUnit,
} from "./types";
import { PLACEHOLDER_PATTERN } from "./kirby-text";
import { isUntranslatable } from "./untranslatable";

function countPlaceholders(text: string): number {
  return (text.match(PLACEHOLDER_PATTERN) ?? []).length;
}

/**
 * Sends only the units worth translating to the strategy, splicing source text
 * into the skipped slots so callers keep a 1:1 mapping with `units`.
 *
 * Also enforces the KirbyTag placeholder invariant here rather than inside a
 * strategy, so every strategy is covered.
 */
export async function translateUnits(
  units: TranslationUnit[],
  strategy: TranslationStrategy,
  options: TranslationExecutionOptions,
): Promise<string[]> {
  const results = units.map((unit) => unit.text);

  const translatableIndexes: number[] = [];
  const translatableUnits: TranslationUnit[] = [];

  for (const [index, unit] of units.entries()) {
    if (!isUntranslatable(unit.text)) {
      translatableIndexes.push(index);
      translatableUnits.push(unit);
    }
  }

  if (!translatableUnits.length) return results;

  const translations = await strategy.execute(translatableUnits, options);

  for (const [position, index] of translatableIndexes.entries()) {
    const translation = translations[position];
    // A short response leaves the source text in place rather than blanking it
    if (translation === undefined) continue;

    const unit = translatableUnits[position]!;
    const expectedCount = countPlaceholders(unit.text);
    const actualCount = countPlaceholders(translation);

    if (expectedCount !== actualCount) {
      console.warn(
        `Placeholder count mismatch in "${unit.fieldKey}": expected ${expectedCount}, got ${actualCount}. Keeping source text.`,
      );
      continue;
    }

    results[index] = translation;
  }

  return results;
}
