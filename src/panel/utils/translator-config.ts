import type {
  PluginConfig,
  TranslationProvider,
  TranslatorOptions,
} from "../types";
import { DEFAULT_FIELD_TYPES, TRANSLATION_PROVIDERS } from "../constants";

/**
 * Translator configuration resolved from section/view button props and the
 * global plugin config. The resolution precedence is documented blueprint
 * API – don't reorder it.
 */
export interface ResolvedTranslatorConfig {
  allowImport: boolean;
  importFrom: string | undefined;
  allowBatchTranslation: boolean;
  translateTitle: boolean;
  translateSlug: boolean;
  shouldConfirm: boolean;
  fieldTypes: string[];
  includeFields: string[];
  excludeFields: string[];
  kirbyTags: Record<string, string[]>;
  systemPrompt: string | undefined;
}

export interface ProviderAvailability {
  hasDefaultProvider: boolean;
  hasMultipleProviders: boolean;
}

export function resolveTranslatorConfig(
  config: PluginConfig,
  options: TranslatorOptions = {},
): ResolvedTranslatorConfig {
  return {
    allowImport: toBool(options.import ?? config.import, true),
    importFrom: options.importFrom ?? config.importFrom ?? undefined,
    allowBatchTranslation: toBool(options.batch ?? config.batch, true),
    translateTitle: toBool(options.title ?? config.title, false),
    translateSlug: toBool(options.slug ?? config.slug, false),
    shouldConfirm: toBool(options.confirm ?? config.confirm, false),
    fieldTypes: options.fieldTypes ??
      config.fieldTypes ?? [...DEFAULT_FIELD_TYPES],
    includeFields: options.includeFields ?? config.includeFields ?? [],
    excludeFields: options.excludeFields ?? config.excludeFields ?? [],
    kirbyTags: options.kirbyTags ?? config.kirbyTags ?? {},
    systemPrompt: options.systemPrompt ?? config.ai?.systemPrompt ?? undefined,
  };
}

export function resolveInitialProvider(
  requestedProvider: unknown,
  availability: ProviderAvailability,
): TranslationProvider {
  const provider = isValidProvider(requestedProvider)
    ? requestedProvider
    : undefined;

  if (provider === "ai" && availability.hasMultipleProviders) return "ai";
  if (provider === "deepl" && availability.hasDefaultProvider) return "deepl";
  // Only AI available
  if (!availability.hasDefaultProvider) return "ai";
  // Default to DeepL
  return "deepl";
}

function isValidProvider(value: unknown): value is TranslationProvider {
  return TRANSLATION_PROVIDERS.includes(value as TranslationProvider);
}

/**
 * Coerces loose boolean values from YAML/PHP into real JavaScript booleans.
 */
function toBool(value: unknown, defaultValue = false) {
  if (value == null) return defaultValue;
  if (typeof value === "boolean") return value;
  if (typeof value === "string") return value === "true" || value === "1";
  if (typeof value === "number") return value === 1;

  return Boolean(value);
}
