import type { CopilotThirdPartyApi } from "./copilot-contract";
import { usePanel } from "kirbyuse";
import { REQUIRED_COPILOT_API_VERSION } from "./copilot-contract";

/** Whether Kirby Copilot can run AI translations, or why it cannot. */
export type CopilotReadiness =
  "ready" | "missing" | "outdated" | "missingApiKey" | "unavailable";

export function resolveCopilot() {
  const panel = usePanel();
  return panel.plugins.thirdParty.copilot as CopilotThirdPartyApi | undefined;
}

/**
 * Checks Copilot's `apiVersion` against `REQUIRED_COPILOT_API_VERSION` and the
 * API key of its configured provider, so the AI strategy is never offered when
 * it would fail every run.
 */
export async function resolveCopilotReadiness(): Promise<CopilotReadiness> {
  const copilot = resolveCopilot();
  if (!copilot) return "missing";

  if ((copilot.apiVersion ?? 1) < REQUIRED_COPILOT_API_VERSION) {
    return "outdated";
  }

  try {
    const { config } = await copilot.resolvePluginContext();
    return config.providers[config.provider]?.hasApiKey
      ? "ready"
      : "missingApiKey";
  } catch {
    return "unavailable";
  }
}
