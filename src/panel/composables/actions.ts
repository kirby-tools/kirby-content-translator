import type { PanelLanguageInfo } from "kirby-types";
import type { useContentTranslator } from "./translation";
import { usePanel } from "kirbyuse";
import { useTranslationDialogs } from "./dialogs";

export function useTranslationActions(
  {
    shouldConfirm,
    strategyName,
    importModelContent,
    translateModelContent,
    batchTranslateModelContent,
    getCascadeHelp,
  }: ReturnType<typeof useContentTranslator>,
  initialization: Promise<void> = Promise.resolve(),
) {
  const panel = usePanel();
  const {
    openConfirmableTextDialog,
    openTranslationDialog,
    openBatchTranslationDialog,
    showCopilotLicenseToastOnce,
  } = useTranslationDialogs();

  const defaultLanguage = panel.languages.find((language) => language.default)!;

  async function handleImport(sourceLanguage?: PanelLanguageInfo) {
    const { path } = panel.view;
    // Kirby switches the language by mutating `panel.language` in place.
    const targetLanguage = { ...panel.language };
    const text = panel.t(
      "johannschopplich.content-translator.dialog.importConfirmation",
      {
        language: sourceLanguage?.name ?? defaultLanguage.name,
      },
    );

    await openConfirmableTextDialog(text, shouldConfirm.value, async () => {
      await initialization;
      await importModelContent(path, targetLanguage, sourceLanguage);
    });
  }

  async function handleTranslate(sourceLanguage?: PanelLanguageInfo) {
    const { path } = panel.view;
    // Kirby switches the language by mutating `panel.language` in place.
    const targetLanguage = { ...panel.language };
    await initialization;
    // A translation into the default language has no cascade.
    const result = await openTranslationDialog(
      targetLanguage.default ? undefined : await getCascadeHelp(path),
    );
    if (result) {
      strategyName.value = result.strategyName;
      await translateModelContent(path, targetLanguage, sourceLanguage);
      if (result.strategyName === "ai") {
        showCopilotLicenseToastOnce();
      }
    }
  }

  async function handleBatchTranslate() {
    await initialization;
    const result = await openBatchTranslationDialog(
      await getCascadeHelp(panel.view.path),
    );
    if (result) {
      strategyName.value = result.strategyName;
      await batchTranslateModelContent(result.languages);
      if (result.strategyName === "ai") {
        showCopilotLicenseToastOnce();
      }
    }
  }

  return { handleImport, handleTranslate, handleBatchTranslate };
}
