import type { PanelModelData } from "kirby-types";
import { usePanel } from "kirbyuse";

export function useModel() {
  const panel = usePanel();
  const defaultLanguage = panel.languages.find((language) => language.default);

  function getModelData(path: string, languageCode = defaultLanguage?.code) {
    return panel.api.get<PanelModelData>(
      path,
      { language: languageCode },
      undefined,
      // Avoid showing Panel loading indicator.
      true,
    );
  }

  return {
    getModelData,
  };
}
