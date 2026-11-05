import type { PanelModelData } from "kirby-types";
import { usePanel } from "kirbyuse";

export function useModel() {
  const panel = usePanel();
  const defaultLanguage = panel.languages.find((language) => language.default);

  function getModelData(path: string) {
    return panel.api.get<PanelModelData>(
      path,
      { language: defaultLanguage?.code },
      undefined,
      // Avoid showing Panel loading indicator.
      true,
    );
  }

  return {
    getModelData,
  };
}
