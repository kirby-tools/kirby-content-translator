import type { PanelLanguage, PanelLanguageInfo } from "kirby-types";
import { useDialog, usePanel } from "kirbyuse";

export interface TranslationDialogsOptions {
  batchTranslateModelContent: (
    languages: (PanelLanguageInfo | PanelLanguage)[],
  ) => Promise<void>;
}

export function useTranslationDialogs(options: TranslationDialogsOptions) {
  const panel = usePanel();
  const { openFieldsDialog, openTextDialog } = useDialog();

  const defaultLanguage = panel.languages.find((language) => language.default)!;
  const translationLanguages = panel.languages.filter(
    (language) => language.code !== defaultLanguage.code,
  );

  async function openConfirmableTextDialog(
    text: string,
    shouldConfirm: boolean | undefined,
    callback?: () => void,
  ) {
    if (!shouldConfirm) {
      callback?.();
      return;
    }

    const isOk = await openTextDialog(text);
    if (isOk) callback?.();
  }

  async function openBatchTranslationDialog() {
    const result = await openFieldsDialog({
      submitButton: {
        icon: "translate",
        theme: "positive",
        text: panel.t(
          "johannschopplich.content-translator.dialog.button.translate",
        ),
      },
      fields: {
        text: {
          type: "info",
          theme: "notice",
          text: panel.t(
            "johannschopplich.content-translator.dialog.batchTranslation",
            { language: defaultLanguage.name },
          ),
        },
        languages: {
          type: "checkboxes",
          label: `${panel.t("johannschopplich.content-translator.translateTo")}:`,
          options: translationLanguages.map((language) => ({
            value: language.code,
            text: language.name,
          })),
        },
      },
      value: {
        languages: translationLanguages.map((language) => language.code),
      },
    });

    if (result?.languages?.length) {
      await options.batchTranslateModelContent(
        translationLanguages.filter((language) =>
          result.languages.includes(language.code),
        ),
      );
    }
  }

  return {
    defaultLanguage,
    translationLanguages,
    openConfirmableTextDialog,
    openBatchTranslationDialog,
  };
}
