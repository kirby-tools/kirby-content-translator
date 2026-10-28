import type { LicenseStatus } from "@kirby-tools/licensing";
import type { KirbyFieldProps } from "kirby-types";
import type { STRATEGY_NAMES } from "./constants";

/** Loose boolean type for raw props coming from PHP/YAML. */
type BooleanInput = boolean | string | number | null;

export type StrategyName = (typeof STRATEGY_NAMES)[number];

export interface PluginConfig {
  import?: boolean;
  importFrom?: string;
  batch?: boolean;
  title?: boolean;
  slug?: boolean;
  fieldTypes: string[];
  includeFields?: string[];
  excludeFields?: string[];
  kirbyTags?: Record<string, string[]>;
  batchConcurrency?: number;
  /** Strategy the translate endpoint resolves to, named server-side. */
  strategy?: StrategyName | "custom";
  /** Sanitized DeepL configuration – only whether an API key is set, never the key. */
  DeepL?: {
    apiKey?: boolean;
  };
  ai?: {
    /** The `ai.systemPrompt` option, or the plugin's default prompt without one – resolved server-side. */
    systemPrompt?: string;
  };
}

export interface PluginContextResponse {
  config: PluginConfig;
  /** The registered KirbyTag types and aliases, lowercased. */
  kirbyTagTypes: string[];
  homePageId: string;
  errorPageId: string;
  licenseStatus: LicenseStatus;
  licenseDialog: string;
}

export interface TranslateUnitsResponse {
  texts: string[];
  rejections?: {
    index: number;
    reason: string;
    expectedIndexes?: number[];
    actualIndexes?: number[];
  }[];
}

export interface BatchStatusResponse {
  isUpdateAllowed: boolean;
  isTitleChangeAllowed: boolean;
  /** Name of another user who edits the model in any language. */
  lockedBy: string | null;
  languagesWithUnsavedChanges: string[];
}

export interface CascadeModelResponse {
  /** Panel API path, such as `pages/notes+exploring`. */
  path: string;
  /** Title of a page or filename of a file. */
  title: string;
  fields: Record<string, KirbyFieldProps>;
  status: BatchStatusResponse;
}

export interface BatchWriteRequest {
  path: string;
  language: string;
  content: Record<string, unknown>;
  title?: string;
  slug?: string;
}

export type BatchWriteResponse =
  | { status: "unsavedChanges" }
  | {
      status: "locked";
      lockedBy: string;
    }
  | {
      status: "saved";
      /** Fields of the saved language that fail validation, keyed by field name. */
      invalidFields?: Record<
        string,
        { label: string | null; message: Record<string, string> }
      >;
      titleError?: string;
      slugError?: string;
    };

/** Keyed by language code. */
export type VariablesResponse = Record<
  string,
  {
    variables: Record<string, unknown>;
    /** Keys of the default language's variables that Kirby refuses to save in this language. */
    reservedKeys: string[];
  }
>;

export interface TranslatorOptions {
  import?: BooleanInput;
  importFrom?: string;
  batch?: BooleanInput;
  title?: BooleanInput;
  slug?: BooleanInput;
  fieldTypes?: string[];
  includeFields?: string[];
  excludeFields?: string[];
  kirbyTags?: Record<string, string[]>;
  /** Queries for the models a run translates along with its host. */
  cascade?: string | string[];
  /** Override of the global `ai.systemPrompt`; the view button has no such prop. */
  systemPrompt?: string;
}

export interface TranslationLanguageCoverage {
  code: string;
  name: string;
  percentage: number;
  incompletePageCount: number;
}

/** Strings already translated by the caller. */
export interface CoverageLabels {
  title: string;
  pagesToTranslate: string;
  allTranslated: string;
  pagesIncomplete: (count: number) => string;
}

export interface TranslationTreeEntry {
  id: string;
  label: string;
  icon: string | null;
  link: string;
  hasChildren: boolean;
  incompleteDescendantCount: number;
  missingLanguages: { code: string; name: string }[];
  isOpen: boolean;
  isLoading: boolean;
  children: TranslationTreeEntry[] | null;
}
