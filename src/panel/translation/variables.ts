import type {
  ContentTranslationResult,
  KirbyTagRules,
  TranslationLanguage,
  TranslationRejection,
  TranslationStrategy,
  TranslationUnit,
} from "./types";
import { translateUnits } from "./dispatch";
import { PLACEHOLDER_PATTERN, splitKirbyText } from "./kirby-text";
import { isNumeric, isUntranslatable } from "./untranslatable";

/** The `Str::template()` placeholders Kirby fills `tt()` and `tc()` with. */
const VARIABLE_PLACEHOLDER_PATTERN = /(?:\{\{|\{<|\{)(.*?)(?:\}\}|>\}|\})/g;

export type LanguageVariables = Record<string, unknown>;

export interface VariableTranslationPlan {
  /** Keys of the default language's variables that hold text to translate and that Kirby saves in the target language. */
  translatableKeys: string[];
  /** The translatable variables, with their default value, that are missing or blank in the target language, or still identical to the default language. */
  pending: LanguageVariables;
}

interface VariableTextOptions {
  kirbyTags: KirbyTagRules;
}

export function planVariableTranslation(
  sourceVariables: LanguageVariables,
  targetVariables: LanguageVariables,
  {
    reservedKeys,
    ...textOptions
  }: VariableTextOptions & { reservedKeys: readonly string[] },
): VariableTranslationPlan {
  // Kirby refuses to save an empty or numeric key, or one that shadows its own strings.
  const translatableKeys = Object.keys(sourceVariables).filter(
    (key) =>
      key !== "" &&
      !isNumeric(key) &&
      !reservedKeys.includes(key) &&
      hasTextToTranslate(sourceVariables[key], textOptions),
  );

  const pending = Object.fromEntries(
    translatableKeys
      .filter((key) => isPending(targetVariables[key], sourceVariables[key]))
      .map((key) => [key, sourceVariables[key]]),
  );

  return { translatableKeys, pending };
}

/**
 * Translates every pending variable with one strategy call, form by form for
 * the multiple values of `tc()`. A variable with a rejected unit is left out
 * of `translatedVariables`, so it keeps the value it has.
 */
export async function translateVariables(
  pendingVariables: LanguageVariables,
  {
    strategy,
    sourceLanguage,
    targetLanguage,
    ...textOptions
  }: VariableTextOptions & {
    strategy: TranslationStrategy;
    sourceLanguage: TranslationLanguage;
    targetLanguage: TranslationLanguage;
  },
): Promise<{
  translatedVariables: LanguageVariables;
  result: ContentTranslationResult;
}> {
  const units: TranslationUnit[] = [];
  const slots: {
    key: string;
    entryKey: string;
    restore: (translatedUnitTexts: string[]) => string;
    offset: number;
    length: number;
  }[] = [];

  for (const [key, value] of Object.entries(pendingVariables)) {
    for (const [entryKey, entry] of textEntries(value)) {
      const { unitTexts, restore } = splitVariable(entry, textOptions);
      slots.push({
        key,
        entryKey,
        restore,
        offset: units.length,
        length: unitTexts.length,
      });
      units.push(...unitTexts.map((text) => ({ text, fieldKey: key })));
    }
  }

  const { texts, rejections } = await translateUnits(units, strategy, {
    sourceLanguage,
    targetLanguage,
  });

  // A variable is named once, by the first unit that kept its source text.
  // Variable placeholders are masked like KirbyTag placeholders, so a
  // placeholder mismatch is reported as one that may concern either.
  const rejectionsByKey = new Map<string, TranslationRejection>();
  for (const rejection of rejections) {
    if (rejectionsByKey.has(rejection.fieldKey!)) continue;
    rejectionsByKey.set(
      rejection.fieldKey!,
      rejection.reason === "placeholder mismatch"
        ? { ...rejection, reason: "variable placeholder mismatch" }
        : rejection,
    );
  }

  const translatedEntries = new Map<string, Map<string, string>>();
  for (const { key, entryKey, restore, offset, length } of slots) {
    if (rejectionsByKey.has(key)) continue;
    if (!translatedEntries.has(key)) translatedEntries.set(key, new Map());
    translatedEntries
      .get(key)!
      .set(entryKey, restore(texts.slice(offset, offset + length)));
  }

  const translatedVariables = Object.fromEntries(
    [...translatedEntries].map(([key, entries]) => [
      key,
      replaceTextEntries(pendingVariables[key], entries),
    ]),
  );

  return {
    translatedVariables,
    result: {
      translatableCount: Object.keys(pendingVariables).length,
      translatedCount: translatedEntries.size,
      rejections: [...rejectionsByKey.values()],
    },
  };
}

export function isPending(targetValue: unknown, sourceValue: unknown) {
  return isBlank(targetValue) || isSameValue(targetValue, sourceValue);
}

/** Compares like PHP's `===`, since the variables come from PHP. */
function isSameValue(value: unknown, otherValue: unknown) {
  return JSON.stringify(value) === JSON.stringify(otherValue);
}

function hasTextToTranslate(value: unknown, options: VariableTextOptions) {
  return textEntries(value).some(([, entry]) =>
    splitVariable(entry, options).unitTexts.some(
      (unitText) => !isUntranslatable(unitText),
    ),
  );
}

/**
 * Lists the texts of a variable: the value itself, or each form of the
 * multiple values of `tc()`, keyed by its position.
 */
function textEntries(value: unknown): [entryKey: string, text: string][] {
  if (typeof value === "string") return [["", value]];
  if (!isEntryList(value)) return [];

  return Object.entries(value).filter(
    (entry): entry is [string, string] => typeof entry[1] === "string",
  );
}

/**
 * Splits a variable like `splitKirbyText()` and masks each variable
 * placeholder as `<cN/>`, numbered after the KirbyTag placeholders, so a
 * translation that loses one is rejected.
 */
function splitVariable(text: string, options: VariableTextOptions) {
  const { unitTexts, restore } = splitKirbyText(text, options.kirbyTags);
  const offsets: number[] = [];
  const variablePlaceholders: string[][] = [];

  const maskedUnitTexts = unitTexts.map((unitText) => {
    const offset = [...unitText.matchAll(PLACEHOLDER_PATTERN)].length;
    const placeholders: string[] = [];
    offsets.push(offset);
    variablePlaceholders.push(placeholders);

    return unitText.replace(VARIABLE_PLACEHOLDER_PATTERN, (placeholder) => {
      placeholders.push(placeholder);
      return `<c${offset + placeholders.length - 1}/>`;
    });
  });

  function restoreVariable(translatedUnitTexts: string[]) {
    return restore(
      translatedUnitTexts.map((translatedUnitText, unitIndex) =>
        translatedUnitText.replace(
          PLACEHOLDER_PATTERN,
          // A lower index belongs to a KirbyTag, which `restore` rebuilds.
          (match, index: string) =>
            variablePlaceholders[unitIndex]![
              Number(index) - offsets[unitIndex]!
            ] ?? match,
        ),
      ),
    );
  }

  return { unitTexts: maskedUnitTexts, restore: restoreVariable };
}

function isBlank(value: unknown) {
  if (value === undefined || value === null) return true;
  if (typeof value === "string") return !value.trim();
  if (!isEntryList(value)) return false;

  return Object.values(value).every(
    (entry) => typeof entry === "string" && !entry.trim(),
  );
}

function isEntryList(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}

function replaceTextEntries(value: unknown, entries: Map<string, string>) {
  if (typeof value === "string") return entries.get("")!;
  if (Array.isArray(value)) {
    return value.map((entry, index) => entries.get(String(index)) ?? entry);
  }

  return Object.fromEntries(
    Object.entries(value as Record<string, unknown>).map(
      ([entryKey, entry]) => [entryKey, entries.get(entryKey) ?? entry],
    ),
  );
}
