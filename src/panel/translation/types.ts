import type { KirbyFieldProps } from "kirby-types";

export interface TranslationLanguage {
  /** Language code (e.g., `en`, `de`). */
  code: string;
  /** Display name (e.g., `English`, `German`). */
  name: string;
}

export interface TranslationUnit {
  text: string;
  /** Field key for error reporting (e.g. `title`, `blocks[0].text`). */
  fieldKey?: string;
}

/** A translation unit paired with a callback that writes the result back. */
export interface CollectedTranslation {
  unit: TranslationUnit;
  apply: (translatedText: string) => void;
}

export interface CollectorResult {
  translations: CollectedTranslation[];
  /** Post-translation callbacks (e.g., YAML serialization). */
  finalizers: (() => void)[];
}

export interface TranslationExecutionOptions {
  sourceLanguage?: TranslationLanguage;
  targetLanguage: TranslationLanguage;
}

/**
 * One slot of a strategy's answer: the translation, or the reason there is
 * none. Returning the source text instead of a rejection would be
 * indistinguishable from a translation that legitimately equals its source.
 */
export type TranslationOutcome =
  | string
  | {
      reason: string;
      /** Placeholder indexes the source text carries, set for a `placeholder mismatch`. */
      expected?: number[];
      /** Placeholder indexes the answer carried instead. */
      actual?: number[];
    };

export interface TranslationStrategy {
  /** Translates `units` and returns one outcome per unit, in input order. */
  execute: (
    units: TranslationUnit[],
    options: TranslationExecutionOptions,
  ) => Promise<TranslationOutcome[]>;
}

export interface CollectorOptions {
  fieldTypes: string[] | readonly string[];
  includeFields?: string[];
  excludeFields?: string[];
  fields: Record<string, KirbyFieldProps>;
  /** Translatable KirbyTag attributes per tag type, e.g. `{ link: ["text"] }`. */
  kirbyTags?: Record<string, string[]>;
}

/** A unit that kept its source text, and the reason it did. */
export interface TranslationRejection {
  fieldKey?: string;
  /** One of the terms `tests/fixtures/contract.json` pins. */
  reason: string;
  /** The reason spelled out, where the check that raised it has specifics to add. */
  detail?: string;
}

export interface UnitTranslationResult {
  /** Final text per unit, in input order – source text wherever no translation was applied. */
  texts: string[];
  /** Units handed to the strategy, i.e. everything `isUntranslatable` did not filter out. */
  translatableCount: number;
  translatedCount: number;
  /** In input order. A unit `isUntranslatable` filtered out is skipped, not rejected. */
  rejections: TranslationRejection[];
}

export type ContentTranslationResult = Omit<UnitTranslationResult, "texts">;
