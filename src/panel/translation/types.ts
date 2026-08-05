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
  signal?: AbortSignal;
}

export interface TranslationStrategy {
  /** Translates `units` and returns results in the same order as the input. */
  execute: (
    units: TranslationUnit[],
    options: TranslationExecutionOptions,
  ) => Promise<string[]>;
}

export interface CollectorOptions {
  fieldTypes: string[] | readonly string[];
  includeFields?: string[];
  excludeFields?: string[];
  fields: Record<string, KirbyFieldProps>;
  /** Translatable KirbyTag attributes per tag type, e.g. `{ link: ["text"] }`. */
  kirbyTags?: Record<string, string[]>;
}
