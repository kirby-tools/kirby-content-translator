import type { PanelModelData } from "kirby-types";
import { usePanel } from "kirbyuse";

const modelDataCache = new Map<string, PanelModelData>();
let isListenerRegistered = false;

export function useModel() {
  const panel = usePanel();
  const defaultLanguage = panel.languages.find((language) => language.default);

  // Ensure event listener is only set once
  if (!isListenerRegistered) {
    panel.events.on("model.update", clearModelData);
    panel.events.on("page.changeSlug", clearModelData);
    panel.events.on("page.changeTitle", clearModelData);
    isListenerRegistered = true;
  }

  async function getModelData() {
    const { path: id } = panel.view;

    if (modelDataCache.has(id)) {
      return modelDataCache.get(id)!;
    }

    const response = await panel.api.get<PanelModelData>(
      id,
      { language: defaultLanguage?.code },
      undefined,
      // Silent
      true,
    );

    modelDataCache.set(id, response);
    return response;
  }

  function clearModelData() {
    modelDataCache.delete(panel.view.path);
  }

  return {
    getModelData,
    clearModelData,
  };
}
