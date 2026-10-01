import type { TranslationStrategy } from "../translation/types";
import type { PluginConfig, StrategyName } from "../types";
import { AIStrategy, DeepLStrategy } from "../translation";
import { resolveCopilotReadiness } from "./copilot";
import {
  describeMissingStrategy,
  getStrategyAvailability,
} from "./translator-config";

export function createStrategy(
  strategyName: StrategyName,
  systemPrompt: string | undefined,
): TranslationStrategy {
  return strategyName === "ai"
    ? new AIStrategy({ systemPrompt })
    : new DeepLStrategy();
}

/**
 * Asks Kirby Copilot for its configuration to tell whether any strategy is
 * usable, and if none is, what keeps each from being offered.
 */
export async function resolveStrategyReadiness(config: PluginConfig) {
  const copilotReadiness = await resolveCopilotReadiness();
  const { hasAnyStrategy } = getStrategyAvailability(config, copilotReadiness);

  return {
    hasAnyStrategy,
    missingStrategyMessage: hasAnyStrategy
      ? undefined
      : describeMissingStrategy(config, copilotReadiness),
  };
}
