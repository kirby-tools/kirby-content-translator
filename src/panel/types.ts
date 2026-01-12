// =============================================================================
// Plugin Configuration
// =============================================================================

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
  batchConcurrency?: number;
}

export interface KirbyTagConfig {
  [key: string]: unknown;
}

// =============================================================================
// API Response Types
// =============================================================================

/**
 * Response from `__content-translator__/context` API endpoint.
 *
 * Returns plugin configuration and license status.
 * Called once on plugin initialization.
 */
export interface PluginContextResponse {
  config: PluginConfig;
  homePageId: string;
  errorPageId: string;
  licenseStatus?: string;
}
