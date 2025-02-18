import { usePanel } from "kirbyuse";

const languageDataCache = new Map();
let isListenerRegistered = false;

export function useModel() {
  const panel = usePanel();
  const defaultLanguage = panel.languages.find((language) => language.default);

  // Ensure event listener is only set once
  if (!isListenerRegistered) {
    panel.events.on("model.update", removeLanguageData);
    panel.events.on("page.changeSlug", removeLanguageData);
    panel.events.on("page.changeTitle", removeLanguageData);
    isListenerRegistered = true;
  }

  async function getDefaultLanguageData() {
    const { path: id } = panel.view;

    if (languageDataCache.has(id)) {
      return languageDataCache.get(id);
    }

    const response = await panel.api.get(
      id,
      { language: defaultLanguage.code },
      undefined,
      // Silent
      true,
    );

    languageDataCache.set(id, response);
    return response;
  }

  function removeLanguageData() {
    languageDataCache.delete(panel.view.path);
  }

  return {
    getDefaultLanguageData,
    removeLanguageData,
  };
}
