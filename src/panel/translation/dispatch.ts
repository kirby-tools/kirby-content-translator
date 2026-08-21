import type {
  TranslationExecutionOptions,
  TranslationRejection,
  TranslationStrategy,
  TranslationUnit,
  UnitTranslationResult,
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
    return { texts, translatableCount: 0, translatedCount: 0, rejections: [] };
  }

  const translations = await strategy.execute(translatableUnits, options);

  const rejections: TranslationRejection[] = [];
  let translatedCount = 0;

  function reject(unit: TranslationUnit, reason: string, detail?: string) {
    rejections.push({ fieldKey: unit.fieldKey, reason, detail });
  }

  for (const [position, index] of translatableIndexes.entries()) {
    const unit = translatableUnits[position]!;
    const outcome = translations[position];

    // The PHP tier calls a `null` slot unanswered too, and `contract.json` pins
    // both tiers to the same name for it.
    if (outcome === undefined || outcome === null) {
      reject(unit, "missing translation");
      continue;
    }

    if (typeof outcome === "object") {
      reject(unit, outcome.reason);
      continue;
    }

    if (typeof outcome !== "string") {
      reject(unit, "non-string translation");
      continue;
    }

    // `isUntranslatable` dropped the blank sources, so nothing that reaches a
    // strategy can legitimately come back blank.
    if (!outcome.trim()) {
      reject(unit, "empty translation");
      continue;
    }

    const expectedIndexes = placeholderIndexes(unit.text);
    const actualIndexes = placeholderIndexes(outcome);

    if (expectedIndexes !== actualIndexes) {
      reject(
        unit,
        "placeholder mismatch",
        `placeholder mismatch, expected ${expectedIndexes || "none"}, got ${actualIndexes || "none"}`,
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
    rejections,
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
