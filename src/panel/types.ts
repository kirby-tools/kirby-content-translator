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
  confirm?: boolean;
  fieldTypes?: string[];
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
    /** Custom system prompt to replace the default translation instructions. */
    systemPrompt?: string;
  };
}

export interface PluginContextResponse {
  config: PluginConfig;
  homePageId: string;
  errorPageId: string;
  licenseStatus?: LicenseStatus;
}

export interface BatchStatusResponse {
  isUpdateAllowed: boolean;
  /** Name of another user who edits the model in any language. */
  lockedBy: string | null;
  languagesWithUnsavedChanges: string[];
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

/** Translator options from section/view button props. */
export interface TranslatorOptions {
  label?: string;
  import?: BooleanInput;
  importFrom?: string;
  batch?: BooleanInput;
  title?: BooleanInput;
  slug?: BooleanInput;
  confirm?: BooleanInput;
  fieldTypes?: string[];
  includeFields?: string[];
  excludeFields?: string[];
  kirbyTags?: Record<string, string[]>;
  /** Custom system prompt for AI translation (overrides global `ai.systemPrompt`). */
  systemPrompt?: string;
  /** Only available when passed from section computed props. */
  fields?: Record<string, KirbyFieldProps>;
}

export interface TranslationLanguageCoverage {
  code: string;
  name: string;
  percentage: number;
  incompletePageCount: number;
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

export interface TranslationCoverageResponse {
  languages: TranslationLanguageCoverage[];
  tree: TranslationTreeEntry[];
}
