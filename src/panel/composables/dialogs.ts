import type { PanelLanguage, PanelLanguageInfo } from "kirby-types";
import type { PluginConfig, TranslationProvider } from "../types";
import { useDialog, usePanel } from "kirbyuse";
import { usePluginContext } from "./plugin";
import { getProviderAvailability } from "./translation";

export interface TranslationDialogResult {
  provider: TranslationProvider;
}

export interface BatchTranslationDialogResult {
  provider: TranslationProvider;
  languages: (PanelLanguageInfo | PanelLanguage)[];
}

export function useTranslationDialogs(options: {
  defaultProvider: TranslationProvider;
}) {
  const panel = usePanel();
  const { openFieldsDialog, openTextDialog } = useDialog();

  const defaultLanguage = panel.languages.find((language) => language.default)!;
  const translationLanguages = panel.languages.filter(
    (language) => language.code !== defaultLanguage.code,
  );

  async function openConfirmableTextDialog(
    text: string,
    shouldConfirm?: boolean,
    callback?: () => void,
  ) {
    if (!shouldConfirm) {
      callback?.();
      return;
    }

    const isOk = await openTextDialog(text);
    if (isOk) callback?.();
  }

  /**
   * Determines the available translation provider(s) based on the configuration.
   */
  function getProviderConfig(config: PluginConfig) {
    const { hasDefaultProvider, hasMultipleProviders } =
      getProviderAvailability(config);

    const defaultProviderLabel = config.translateFn
      ? "johannschopplich.content-translator.provider.custom"
      : "johannschopplich.content-translator.provider.deepl";

    const singleProvider: TranslationProvider = hasDefaultProvider
      ? "deepl"
      : "ai";

    return {
      hasMultipleProviders,
      defaultProviderLabel,
      singleProvider,
    };
  }

  async function openTranslationDialog(): Promise<
    TranslationDialogResult | undefined
  > {
    const context = await usePluginContext();
    const { hasMultipleProviders, defaultProviderLabel, singleProvider } =
      getProviderConfig(context.config);

    if (!hasMultipleProviders) {
      return { provider: singleProvider };
    }

    const result = await openFieldsDialog({
      submitButton: {
        icon: "translate",
        theme: "positive",
        text: panel.t(
          "johannschopplich.content-translator.dialog.button.translate",
        ),
      },
      fields: {
        provider: {
          type: "toggles",
          label: panel.t("johannschopplich.content-translator.dialog.provider"),
          labels: true,
          grow: true,
          options: [
            {
              value: "deepl",
              text: panel.t(defaultProviderLabel),
              icon: "translate",
            },
            {
              value: "ai",
              text: panel.t(
                "johannschopplich.content-translator.provider.copilot",
              ),
              icon: "content-translator-ai",
            },
          ],
        },
      },
      value: {
        provider: options.defaultProvider,
      },
    });

    if (result?.provider) {
      return { provider: result.provider };
    }
  }

  async function openBatchTranslationDialog(): Promise<
    BatchTranslationDialogResult | undefined
  > {
    const context = await usePluginContext();
    const { hasMultipleProviders, defaultProviderLabel, singleProvider } =
      getProviderConfig(context.config);

    const result = await openFieldsDialog({
      submitButton: {
        icon: "translate",
        theme: "positive",
        text: panel.t(
          "johannschopplich.content-translator.dialog.button.translate",
        ),
      },
      fields: {
        languages: {
          type: "checkboxes",
          label: `${panel.t("johannschopplich.content-translator.translateTo")}:`,
          options: translationLanguages.map((language) => ({
            value: language.code,
            text: language.name,
          })),
          help: panel.t(
            "johannschopplich.content-translator.dialog.batchTranslation",
            { language: defaultLanguage.name },
          ),
        },
        ...(hasMultipleProviders && {
          provider: {
            type: "toggles",
            label: panel.t(
              "johannschopplich.content-translator.dialog.provider",
            ),
            labels: true,
            grow: true,
            options: [
              {
                value: "deepl",
                text: panel.t(defaultProviderLabel),
                icon: "translate",
              },
              {
                value: "ai",
                text: panel.t(
                  "johannschopplich.content-translator.provider.copilot",
                ),
                icon: "content-translator-ai",
              },
            ],
          },
        }),
      },
      value: {
        provider: options.defaultProvider,
        languages: translationLanguages.map((language) => language.code),
      },
    });

    if (result?.languages?.length) {
      return {
        provider: result.provider ?? singleProvider,
        languages: translationLanguages.filter((language) =>
          result.languages.includes(language.code),
        ),
      };
    }
  }

  return {
    defaultLanguage,
    translationLanguages,
    openConfirmableTextDialog,
    openTranslationDialog,
    openBatchTranslationDialog,
  };
}
