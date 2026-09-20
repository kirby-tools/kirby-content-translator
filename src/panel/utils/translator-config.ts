import type { PluginConfig, TranslatorOptions } from "../types";
import type { CopilotReadiness } from "./copilot";
import { DEFAULT_FIELD_TYPES } from "../constants";

/**
 * Translator configuration resolved from section/view button props and the
 * global plugin config. The resolution precedence is documented blueprint
 * API – don't reorder it.
 */
export interface ResolvedTranslatorConfig {
  isImportEnabled: boolean;
  importFrom: string | undefined;
  isBatchTranslationEnabled: boolean;
  isTitleTranslationEnabled: boolean;
  isSlugTranslationEnabled: boolean;
  shouldConfirm: boolean;
  fieldTypes: string[];
  includeFields: string[];
  excludeFields: string[];
  kirbyTags: Record<string, string[]>;
  systemPrompt: string | undefined;
  hasCascade: boolean;
}

export interface StrategyAvailability {
  isCopilotReady: boolean;
  hasDefaultStrategy: boolean;
  hasMultipleStrategies: boolean;
  hasAnyStrategy: boolean;
}

export function resolveTranslatorConfig(
  config: PluginConfig,
  options: TranslatorOptions = {},
): ResolvedTranslatorConfig {
  return {
    isImportEnabled: toBool(options.import ?? config.import, true),
    importFrom: options.importFrom ?? config.importFrom ?? undefined,
    isBatchTranslationEnabled: toBool(options.batch ?? config.batch, true),
    isTitleTranslationEnabled: toBool(options.title ?? config.title, false),
    isSlugTranslationEnabled: toBool(options.slug ?? config.slug, false),
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
    hasCascade: [options.cascade ?? []].flat().length > 0,
  };
}

export function getStrategyAvailability(
  config: PluginConfig,
  copilotReadiness: CopilotReadiness,
): StrategyAvailability {
  const isCopilotReady = copilotReadiness === "ready";

  // A DeepL key stays in config even when the strategy no longer uses it.
  const hasDefaultStrategy =
    config.strategy === "custom" ||
    (config.strategy !== "ai" && !!config.DeepL?.apiKey);

  return {
    isCopilotReady,
    hasDefaultStrategy,
    hasMultipleStrategies: isCopilotReady && hasDefaultStrategy,
    hasAnyStrategy: isCopilotReady || hasDefaultStrategy,
  };
}

/**
 * Names what keeps each strategy from being offered. Addressed to whoever
 * configures the site, so it names the options in English rather than through
 * `panel.t`.
 */
export function describeMissingStrategy(
  config: PluginConfig,
  copilotReadiness: CopilotReadiness,
) {
  const copilotHint = {
    ready: undefined,
    missing: "install Kirby Copilot for AI translations",
    outdated:
      "update Kirby Copilot, as the installed version cannot run AI translations",
    missingApiKey:
      'add an API key to "johannschopplich.copilot.providers" for the provider Kirby Copilot uses',
    unavailable:
      "check the Kirby Copilot configuration, as its Panel context could not be loaded",
  }[copilotReadiness];

  if (config.strategy === "ai") {
    return `The "johannschopplich.content-translator.strategy" option is set to "ai", so ${copilotHint}.`;
  }

  const deeplHint =
    'Set the "johannschopplich.content-translator.DeepL.apiKey" option or a custom "johannschopplich.content-translator.strategy"';

  return `${deeplHint}, or ${copilotHint}.`;
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
