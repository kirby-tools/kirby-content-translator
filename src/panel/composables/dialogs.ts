import type { LicenseStatus } from "@kirby-tools/licensing";
import type { PanelLanguage, PanelLanguageInfo } from "kirby-types";
import type { PluginConfig, TranslationProvider } from "../types";
import type { PluginContextResponse } from "../utils/copilot";
import { isLocalDev, useDialog, usePanel } from "kirbyuse";
import { STORAGE_KEY_PREFIX } from "../constants";
import { resolveCopilot } from "../utils/copilot";
import { usePluginContext } from "./plugin";
import { getProviderAvailability } from "./translation";

const AI_TRANSLATION_COUNT_STORAGE_KEY = `${STORAGE_KEY_PREFIX}aiTranslationCount`;
const LICENSE_TOAST_THRESHOLD = 3;

const PROVIDER_LABELS: Record<string, string> = {
  openai: "OpenAI",
  anthropic: "Anthropic",
  google: "Google",
  mistral: "Mistral",
};

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
  async function getProviderConfig(config: PluginConfig) {
    const copilot = resolveCopilot();
    const { hasDefaultProvider, hasMultipleProviders } =
      getProviderAvailability(config);

    const defaultProviderLabel = config.translateFn
      ? "johannschopplich.content-translator.provider.custom"
      : "johannschopplich.content-translator.provider.deepl";

    const singleProvider: TranslationProvider = hasDefaultProvider
      ? "deepl"
      : "ai";

    // Fetch Copilot context for provider name and license status
    let copilotContext: PluginContextResponse | undefined;
    if (copilot) {
      try {
        copilotContext = await copilot.resolvePluginContext();
      } catch {
        // Copilot context not available
      }
    }

    const aiProviderKey = copilotContext?.config?.provider;
    const aiProviderLabel = aiProviderKey
      ? (PROVIDER_LABELS[aiProviderKey] ?? aiProviderKey)
      : undefined;

    return {
      hasMultipleProviders,
      defaultProviderLabel,
      singleProvider,
      aiProviderLabel,
    };
  }

  async function openTranslationDialog(): Promise<
    TranslationDialogResult | undefined
  > {
    const context = await usePluginContext();
    const {
      hasMultipleProviders,
      defaultProviderLabel,
      singleProvider,
      aiProviderLabel,
    } = await getProviderConfig(context.config);

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
              text: aiProviderLabel ?? "AI",
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
    const {
      hasMultipleProviders,
      defaultProviderLabel,
      singleProvider,
      aiProviderLabel,
    } = await getProviderConfig(context.config);

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
                text: aiProviderLabel ?? "AI",
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

  async function showCopilotLicenseToastOnce() {
    if (isLocalDev()) return;

    const copilot = resolveCopilot();
    if (!copilot) return;

    const storedValue = sessionStorage.getItem(
      AI_TRANSLATION_COUNT_STORAGE_KEY,
    );
    if (storedValue === "done") return;

    let translationCount = Number(storedValue) || 0;
    translationCount++;
    sessionStorage.setItem(
      AI_TRANSLATION_COUNT_STORAGE_KEY,
      String(translationCount),
    );

    if (translationCount < LICENSE_TOAST_THRESHOLD) return;

    const context = await copilot.resolvePluginContext();

    // Only show toast for unlicensed users
    if (["inactive", "invalid"].includes(context.licenseStatus!)) {
      panel.notification.info({
        icon: "key",
        message: panel.t(
          "johannschopplich.content-translator.copilot.license.toast",
        ),
      });
    }

    sessionStorage.setItem(AI_TRANSLATION_COUNT_STORAGE_KEY, "done");
  }

  return {
    defaultLanguage,
    translationLanguages,
    openConfirmableTextDialog,
    openTranslationDialog,
    openBatchTranslationDialog,
    showCopilotLicenseToastOnce,
  };
}
