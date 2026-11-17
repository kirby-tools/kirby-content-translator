import type { PluginContextResponse } from "../types";
import { createPluginContext } from "@kirby-tools/licensing";
import { PLUGIN_CONTEXT_API_ROUTE } from "../constants";

export const usePluginContext = createPluginContext<PluginContextResponse>({
  route: PLUGIN_CONTEXT_API_ROUTE,
  pluginId: "content-translator",
});
