import type { CopilotThirdPartyApi } from "./copilot-contract";
import { usePanel } from "kirbyuse";

export function resolveCopilot() {
  const panel = usePanel();
  return panel.plugins.thirdParty.copilot as CopilotThirdPartyApi | undefined;
}
