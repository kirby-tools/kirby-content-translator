import type { PluginContextResponse } from "../types";
import { usePanel } from "kirbyuse";
import { LICENSE_ACTIVATE_EVENT, PLUGIN_CONTEXT_API_ROUTE } from "../constants";

let context: PluginContextResponse | undefined;
let pendingPromise: Promise<PluginContextResponse> | undefined;
let isSubscribed = false;

export function usePluginContext() {
  if (context) return Promise.resolve(context);
  if (pendingPromise) return pendingPromise;

  const panel = usePanel();

  pendingPromise = panel.api
    .get<PluginContextResponse>(
      PLUGIN_CONTEXT_API_ROUTE,
      undefined,
      undefined,
      // Avoid showing Panel loading indicator.
      true,
    )
    .then((response) => {
      context = response;

      // The cached context carries the license status from before an activation.
      if (!isSubscribed) {
        panel.events.on(LICENSE_ACTIVATE_EVENT, () => {
          context = undefined;
        });
        isSubscribed = true;
      }

      return context;
    })
    // Without this a failing request stays cached as a rejection and no later view recovers without a full reload.
    .finally(() => {
      pendingPromise = undefined;
    });

  return pendingPromise;
}
