import { PLUGIN_CONTEXT_API_ROUTE } from "../constants";

let context;
let pendingPromise;

export function usePluginContext() {
  if (context) return Promise.resolve(context);
  if (pendingPromise) return pendingPromise;

  pendingPromise = window.panel.api
    .get(PLUGIN_CONTEXT_API_ROUTE)
    .then((response) => {
      context = response;
      pendingPromise = undefined;
      return context;
    });

  return pendingPromise;
}
