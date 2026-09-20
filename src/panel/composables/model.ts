import type { PanelModelData } from "kirby-types";
import { usePanel } from "kirbyuse";

const modelDataCache = new Map<string, PanelModelData>();
let isListenerRegistered = false;

export function useModel() {
  const panel = usePanel();
  const defaultLanguage = panel.languages.find((language) => language.default);

  // `useModel` runs once per component instance, but the cache and its
  // invalidation listeners are module-global.
  if (!isListenerRegistered) {
    panel.events.on("model.update", clearModelDataCache);
    panel.events.on("page.changeSlug", clearModelDataCache);
    panel.events.on("page.changeTitle", clearModelDataCache);
    isListenerRegistered = true;
  }

  async function getModelData() {
    const { path } = panel.view;

    if (modelDataCache.has(path)) {
      return modelDataCache.get(path)!;
    }

    const response = await panel.api.get<PanelModelData>(
      path,
      { language: defaultLanguage?.code },
      undefined,
      // Avoid showing Panel loading indicator.
      true,
    );

    modelDataCache.set(path, response);
    return response;
  }

  // `model.update` fires without naming its model, so the open view's entry
  // is not always the stale one.
  function clearModelDataCache() {
    modelDataCache.clear();
  }

  return {
    getModelData,
  };
}
