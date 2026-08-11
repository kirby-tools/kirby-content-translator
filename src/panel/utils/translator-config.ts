import type { PluginConfig, TranslatorOptions } from "../types";
import { DEFAULT_FIELD_TYPES } from "../constants";
import { resolveCopilot } from "./copilot";

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
  isCopilotAvailable: boolean;
  hasDefaultProvider: boolean;
  hasMultipleProviders: boolean;
  hasAnyProvider: boolean;
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
    fieldTypes: toLowercaseNames(
      options.fieldTypes ?? config.fieldTypes ?? [...DEFAULT_FIELD_TYPES],
    ),
    includeFields: toLowercaseNames(
      options.includeFields ?? config.includeFields ?? [],
    ),
    excludeFields: toLowercaseNames(
      options.excludeFields ?? config.excludeFields ?? [],
    ),
    kirbyTags: options.kirbyTags ?? config.kirbyTags ?? {},
    systemPrompt: options.systemPrompt ?? config.ai?.systemPrompt ?? undefined,
  };
}

export function getProviderAvailability(
  config: PluginConfig,
): ProviderAvailability {
  const isCopilotAvailable = !!resolveCopilot();

  // A DeepL key stays in config even when the strategy no longer uses it.
  const hasDefaultProvider =
    config.strategy === "custom" ||
    (config.strategy !== "ai" && !!config.DeepL?.apiKey);

  return {
    isCopilotAvailable,
    hasDefaultProvider,
    hasMultipleProviders: isCopilotAvailable && hasDefaultProvider,
    hasAnyProvider: isCopilotAvailable || hasDefaultProvider,
  };
}

function toLowercaseNames(names: string[]) {
  return names.map((name) => name.toLowerCase());
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
