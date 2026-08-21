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
export type TranslationOutcome = string | { reason: string };

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

export interface BatchTranslationResult {
  /** Final text per unit, in input order – source text wherever no translation was applied. */
  texts: string[];
  /** Units handed to the strategy, i.e. everything `isUntranslatable` did not filter out. */
  translatableCount: number;
  /** Units the strategy translated, excluding those rejected for a placeholder mismatch. */
  translatedCount: number;
}

export type ContentTranslationResult = Omit<BatchTranslationResult, "texts">;
