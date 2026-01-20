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
  kirbyTags?: Record<string, KirbyTagConfig>;
  /** Concurrency for batch translations */
  batchConcurrency?: number;
  /** Whether a custom translate function is configured */
  translateFn?: boolean;
  /** DeepL API configuration (sanitized, only contains boolean indicating if API key is set) */
  DeepL?: {
    apiKey?: boolean;
  };
  /** @deprecated Will be removed in Kirby 6 release */
  viewButton?: boolean;
}

export interface KirbyTagConfig {
  [key: string]: unknown;
}

/** Response from `__content-translator__/context` API endpoint. */
export interface PluginContextResponse {
  config: PluginConfig;
  homePageId: string;
  errorPageId: string;
  licenseStatus?: LicenseStatus;
}

/**
 * Translator options as received from section/view button props.
 *
 * @remarks
 * Boolean fields accept loose types that are normalized by `initializeConfig()`.
 */
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
  kirbyTags?: Record<string, KirbyTagConfig>;
  provider?: TranslationProvider;
  /** Only available when passed from section computed props */
  fields?: Record<string, KirbyFieldProps>;
}
