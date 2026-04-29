import type { LicenseStatus } from "@kirby-tools/licensing";
import type { KirbyFieldProps } from "kirby-types";
import type { TRANSLATION_PROVIDERS } from "./constants";

/** Loose boolean type for raw props coming from PHP/YAML */
type BooleanInput = boolean | string | number | null;

/** Available translation providers */
export type TranslationProvider = (typeof TRANSLATION_PROVIDERS)[number];

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
  /** Concurrency for batch translations */
  batchConcurrency?: number;
  /** Whether a custom translate function is configured */
  translateFn?: boolean;
  /** DeepL API configuration (sanitized, only contains boolean indicating if API key is set) */
  DeepL?: {
    apiKey?: boolean;
  };
  /** AI translation configuration */
  ai?: {
    /** Custom system prompt to replace the default translation instructions */
    systemPrompt?: string;
  };
  /** @deprecated Will be removed in Kirby 6 release */
  viewButton?: boolean;
}

/** Response from `__content-translator__/context` API endpoint. */
export interface PluginContextResponse {
  config: PluginConfig;
  homePageId: string;
  errorPageId: string;
  licenseStatus?: LicenseStatus;
}

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
  /** Custom system prompt for AI translation (overrides global `ai.systemPrompt`) */
  systemPrompt?: string;
  provider?: TranslationProvider;
  /** Only available when passed from section computed props */
  fields?: Record<string, KirbyFieldProps>;
}

export interface TranslationLanguageStatus {
  code: string;
  name: string;
  totalFields: number;
  translatedFields: number;
  percentage: number;
  incompletePageCount: number;
}

export interface TranslationTreeEntry {
  id: string;
  label: string;
  icon: string | null;
  link: string;
  hasChildren: boolean;
  incompleteDescendants: number;
  missing: { code: string; name: string }[] | null;
  isFullyUntranslated: boolean;
  open: boolean;
  loading?: boolean;
  children?: TranslationTreeEntry[];
}

/** Response from `__content-translator__/status` API endpoint. */
export interface TranslationStatusResponse {
  languages: TranslationLanguageStatus[];
  tree: TranslationTreeEntry[];
}
