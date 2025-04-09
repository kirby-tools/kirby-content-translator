import { usePanel } from "kirbyuse";

const modelDataCache = new Map();
let isListenerRegistered = false;

export function useModel() {
  const panel = usePanel();
  const defaultLanguage = panel.languages.find((language) => language.default);

  // Ensure event listener is only set once
  if (!isListenerRegistered) {
    panel.events.on("model.update", clearViewModelData);
    panel.events.on("page.changeSlug", clearViewModelData);
    panel.events.on("page.changeTitle", clearViewModelData);
    isListenerRegistered = true;
  }

  async function getViewModelData() {
    const { path: id } = panel.view;

    if (modelDataCache.has(id)) {
      return modelDataCache.get(id);
    }

    const response = await panel.api.get(
      id,
      { language: defaultLanguage?.code },
      undefined,
      // Silent
      true,
    );

    modelDataCache.set(id, response);
    return response;
  }

  function clearViewModelData() {
    modelDataCache.delete(panel.view.path);
  }

  return {
    getViewModelData,
    clearViewModelData,
  };
}
