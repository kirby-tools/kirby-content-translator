import type { LicenseStatus } from "@kirby-tools/licensing";
import type { FlexibleSchema } from "ai";
import type { LogLevel, PluginAsset } from "kirbyuse";

/**
 * Minimum Copilot seam version this plugin can talk to (plain-schema
 * structured output). Pinned by `tests/fixtures/copilot-seam-contract.json`,
 * which is mirrored in Kirby Copilot – update both when the contract changes.
 */
export const REQUIRED_COPILOT_API_VERSION = 2;

export const REASONING_EFFORTS = [
  "provider-default",
  "none",
  "minimal",
  "low",
  "medium",
  "high",
  "xhigh",
] as const;
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
  logLevel?: "error" | "warn" | "info" | "debug";
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
  /**
   * Plain schema for structured output; Copilot builds the SDK value on its
   * side of the seam, so no raw AI SDK values cross the plugin boundary.
   */
  outputSchema?: FlexibleSchema;
  responseFormat?: OutputFormat;
  files?: File[];
  logLevel?: LogLevel;
  abortSignal?: AbortSignal;
}

/** The subset of Copilot's `StreamTextResult` this plugin consumes. */
export interface StreamTextSeamResult {
  /** Resolves with the structured output parsed from `outputSchema`. */
  output: Promise<any>;
}

/** The subset of `panel.plugins.thirdParty.copilot` this plugin consumes. */
export interface CopilotThirdPartyApi {
  /** Absent on Copilot versions that predate the versioned seam. */
  apiVersion?: number;
  resolvePluginContext: () => Promise<PluginContextResponse>;
  streamText: (options: StreamTextOptions) => Promise<StreamTextSeamResult>;
}
