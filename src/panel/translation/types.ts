import type { KirbyFieldProps } from "kirby-types";

interface TranslationUnitBase {
  /** Text content to translate */
  text: string;
  /** Field key, mostly for debugging purposes */
  fieldKey?: string;
}

export interface BatchTranslationUnit extends TranslationUnitBase {
  mode: "batch";
  fieldType: "text" | "writer" | "list" | "tags";
}

export interface KirbytextTranslationUnit extends TranslationUnitBase {
  mode: "kirbytext";
  fieldType: "textarea" | "markdown";
}

export interface PlainTranslationUnit extends TranslationUnitBase {
  mode: "plain";
  fieldType: "table";
}

export type TranslationUnit =
  | BatchTranslationUnit
  | KirbytextTranslationUnit
  | PlainTranslationUnit;

/**
 * Collected translation with its apply callback.
 */
export interface CollectedTranslation {
  unit: TranslationUnit;
  /** Callback to apply the translated text to the original content */
  apply: (translatedText: string) => void;
}

/**
 * Output of the collector: translations to execute and finalizers to run after.
 */
export interface CollectorResult {
  translations: CollectedTranslation[];
  /** Post-translation callbacks (e.g., YAML serialization) */
  finalizers: (() => void)[];
}

/**
 * Options passed to the translation strategy during execution.
 */
export interface TranslationExecutionOptions {
  sourceLanguage?: string;
  targetLanguage: string;
  kirbyTags?: Record<string, unknown>;
}

/**
 * Strategy interface for translation implementations.
 */
export interface TranslationStrategy {
  /**
   * Execute translation for the given units.
   * Returns translated texts in the same order as input units.
   */
  execute: (
    units: TranslationUnit[],
    options: TranslationExecutionOptions,
  ) => Promise<string[]>;
}

/**
 * Options for the content collector.
 */
export interface CollectorOptions {
  fieldTypes: string[] | readonly string[];
  includeFields?: string[];
  excludeFields?: string[];
  fields: Record<string, KirbyFieldProps>;
}

/**
 * Full options for the translateContent function.
 */
export interface TranslateContentOptions extends CollectorOptions {
  strategy: TranslationStrategy;
  sourceLanguage?: string;
  targetLanguage: string;
  kirbyTags: Record<string, unknown>;
}
