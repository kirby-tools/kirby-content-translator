import type { PanelLanguageInfo } from "kirby-types";
import type { useContentTranslator } from "./translation";
import { useHelpers, usePanel } from "kirbyuse";
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
  const helpers = useHelpers();
  const {
    openConfirmableTextDialog,
    openTranslationDialog,
    openBatchTranslationDialog,
    showCopilotLicenseToastOnce,
  } = useTranslationDialogs();

  const defaultLanguage = panel.languages.find((language) => language.default)!;

  async function handleImport(sourceLanguage?: PanelLanguageInfo) {
    const { path } = panel.view;
    // Kirby switches the language by mutating `panel.language` in place, never
    // its `panel.languages` entry.
    const targetLanguage = panel.languages.find(
      (language) => language.code === panel.language.code,
    )!;
    const text = panel.t(
      "johannschopplich.content-translator.dialog.importConfirmation",
      {
        language: helpers.string.escapeHTML(
          sourceLanguage?.name ?? defaultLanguage.name,
        ),
      },
    );

    await openConfirmableTextDialog(text, shouldConfirm.value, async () => {
      await initialization;
      await importModelContent(path, targetLanguage, sourceLanguage);
    });
  }

  async function handleTranslate(sourceLanguage?: PanelLanguageInfo) {
    const { path } = panel.view;
    // Kirby switches the language by mutating `panel.language` in place, never
    // its `panel.languages` entry.
    const targetLanguage = panel.languages.find(
      (language) => language.code === panel.language.code,
    )!;
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
    const { path } = panel.view;
    await initialization;
    const result = await openBatchTranslationDialog(
      [
        panel.t("johannschopplich.content-translator.dialog.batchHelp", {
          language: helpers.string.escapeHTML(defaultLanguage.name),
        }),
        await getCascadeHelp(path),
      ]
        .filter(Boolean)
        .join(" "),
    );
    if (result) {
      strategyName.value = result.strategyName;
      await batchTranslateModelContent(path, result.languages);
      if (result.strategyName === "ai") {
        showCopilotLicenseToastOnce();
      }
    }
  }

  return { handleImport, handleTranslate, handleBatchTranslate };
}
