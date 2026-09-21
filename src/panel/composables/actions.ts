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
    const text = panel.t(
      "johannschopplich.content-translator.dialog.importConfirmation",
      {
        language: sourceLanguage?.name ?? defaultLanguage.name,
      },
    );

    await openConfirmableTextDialog(text, shouldConfirm.value, async () => {
      await initialization;
      await importModelContent(sourceLanguage);
    });
  }

  async function handleTranslate(sourceLanguage?: PanelLanguageInfo) {
    await initialization;
    const result = await openTranslationDialog();
    if (result) {
      strategyName.value = result.strategyName;
      await translateModelContent(panel.language, sourceLanguage);
      if (result.strategyName === "ai") {
        showCopilotLicenseToastOnce();
      }
    }
  }

  async function handleBatchTranslate() {
    await initialization;
    const result = await openBatchTranslationDialog(await getCascadeHelp());
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
