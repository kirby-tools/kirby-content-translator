/**
 * Stands in for Copilot's third-party API, with `openai` as its configured
 * provider.
 */
export function copilotWith({
  apiVersion = 2,
  hasApiKey = true,
}: { apiVersion?: number; hasApiKey?: boolean } = {}) {
  return {
    apiVersion,
    resolvePluginContext: async () => ({
      config: { provider: "openai", providers: { openai: { hasApiKey } } },
    }),
  };
}
