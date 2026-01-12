import type { PluginContextResponse } from "../types";
import { PLUGIN_CONTEXT_API_ROUTE } from "../constants";

let context: PluginContextResponse | undefined;
let pendingPromise: Promise<PluginContextResponse> | undefined;

export function usePluginContext() {
  if (context) return Promise.resolve(context);
  if (pendingPromise) return pendingPromise;

  pendingPromise = window.panel.api
    .get<PluginContextResponse>(PLUGIN_CONTEXT_API_ROUTE)
    .then((response) => {
      context = response;
      pendingPromise = undefined;
      return context;
    });

  return pendingPromise;
}
