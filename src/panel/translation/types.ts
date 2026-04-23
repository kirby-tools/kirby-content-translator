import type { KirbyFieldProps } from "kirby-types";

/** Language identifier for translation operations. */
export interface TranslationLanguage {
  /** Language code (e.g., `en`, `de`) */
  code: string;
  /** Display name (e.g., `English`, `German`) */
  name: string;
}

interface TranslationUnitBase {
  /** Text content to translate */
  text: string;
  /** Field key for error reporting (e.g., 'title', 'blocks[0].text') */
  fieldKey?: string;
}

export interface BatchTranslationUnit extends TranslationUnitBase {
  mode: "batch";
}

export interface KirbytextTranslationUnit extends TranslationUnitBase {
  mode: "kirbytext";
}

export interface SingleTranslationUnit extends TranslationUnitBase {
  mode: "single";
}

export type TranslationUnit =
  | BatchTranslationUnit
  | KirbytextTranslationUnit
  | SingleTranslationUnit;

/** A translation unit paired with a callback that writes the result back. */
export interface CollectedTranslation {
  unit: TranslationUnit;
  /** Callback to apply the translated text to the original content */
  apply: (translatedText: string) => void;
}

/** Collector output: translation units plus post-translation finalizers. */
export interface CollectorResult {
  translations: CollectedTranslation[];
  /** Post-translation callbacks (e.g., YAML serialization) */
  finalizers: (() => void)[];
}

/** Options passed to a translation strategy during execution. */
export interface TranslationExecutionOptions {
  sourceLanguage?: TranslationLanguage;
  targetLanguage: TranslationLanguage;
  kirbyTags?: Record<string, unknown>;
  signal?: AbortSignal;
}

/** Strategy interface for translation implementations. */
export interface TranslationStrategy {
  /** Translates `units` and returns results in the same order as the input. */
  execute: (
    units: TranslationUnit[],
    options: TranslationExecutionOptions,
  ) => Promise<string[]>;
}

/** Options for the content collector. */
export interface CollectorOptions {
  fieldTypes: string[] | readonly string[];
  includeFields?: string[];
  excludeFields?: string[];
  fields: Record<string, KirbyFieldProps>;
}
