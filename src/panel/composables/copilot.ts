import type {
  LanguageModelV3,
  SharedV3ProviderOptions,
} from "@ai-sdk/provider";
import type { LicenseStatus } from "@kirby-tools/licensing";
import type { Output as OutputNamespace, StreamTextResult, ToolSet } from "ai";
import type { PluginAsset } from "kirbyuse";
import { usePanel } from "kirbyuse";

export type AISDKModule = typeof import("@ai-sdk/anthropic") &
  typeof import("@ai-sdk/google") &
  typeof import("@ai-sdk/mistral") &
  typeof import("@ai-sdk/openai") &
  typeof import("ai");

export const LOG_LEVELS = ["error", "warn", "info", "debug"] as const;
export type LogLevel = (typeof LOG_LEVELS)[number];

export const REASONING_EFFORTS = ["none", "low", "medium", "high"] as const;
export type ReasoningEffort = (typeof REASONING_EFFORTS)[number];

export type OutputFormat = "text" | "markdown" | "rich-text";

export interface ProviderConfig {
  baseUrl?: string;
  hasApiKey?: boolean;
  model?: string;
  completionModel?: string;
  options?: Record<string, any>;
}

export interface CompletionConfig {
  debounce: number;
}

export interface PluginConfig {
  provider: string;
  providers: Record<string, ProviderConfig>;
  systemPrompt?: string;
  reasoningEffort?: ReasoningEffort;
  excludedBlocks?: string[];
  completion?: false | CompletionConfig;
  logLevel?: LogLevel;
}

/** Response from `__copilot__/context` API endpoint. */
export interface PluginContextResponse {
  config: PluginConfig;
  assets: PluginAsset[];
  licenseStatus?: LicenseStatus;
}

export interface StreamTextOptions {
  userPrompt: string;
  systemPrompt?: string;
  output?: OutputNamespace.Output;
  responseFormat?: OutputFormat;
  files?: File[];
  logLevel?: number;
  abortSignal?: AbortSignal;
}

export interface ResolveLanguageModelResult {
  model: LanguageModelV3;
  providerOptions?: SharedV3ProviderOptions;
}

export interface CopilotThirdPartyApi {
  resolvePluginContext: () => Promise<PluginContextResponse>;
  streamText: (
    options: StreamTextOptions,
  ) => Promise<Promise<StreamTextResult<ToolSet, OutputNamespace.Output>>>;
  resolveLanguageModel: (options?: {
    forCompletion?: boolean;
  }) => Promise<ResolveLanguageModelResult>;
  loadAISDK: () => Promise<AISDKModule>;
}

export function useCopilot(): Partial<CopilotThirdPartyApi> {
  const panel = usePanel();
  const { copilot } = panel.plugins.thirdParty;

  if (!copilot) {
    // eslint-disable-next-line no-console
    console.log("Kirby Copilot is not installed");
    return {};
  }

  return copilot;
}
