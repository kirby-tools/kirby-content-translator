import type { PanelLanguage, PanelLanguageInfo } from "kirby-types";
import type { TranslationProvider } from "../types";
import type { PluginContextResponse } from "../utils/copilot";
import { isLocalDev, useDialog, usePanel } from "kirbyuse";
import { STORAGE_KEY_PREFIX } from "../constants";
import { resolveCopilot } from "../utils/copilot";
import { usePluginContext } from "./plugin";
import { getProviderAvailability } from "./translation";

const LICENSE_TOAST_COUNT_KEY = `${STORAGE_KEY_PREFIX}session$licenseToastCount`;
const PROVIDER_PREFERENCE_KEY = `${STORAGE_KEY_PREFIX}preferences$provider`;
const LICENSE_TOAST_THRESHOLD = 2;

const PROVIDER_CONFIG: Record<string, { labelKey: string; icon: string }> = {
  openai: {
    labelKey: "johannschopplich.content-translator.provider.openai",
    icon: "content-translator-openai",
  },
  anthropic: {
    labelKey: "johannschopplich.content-translator.provider.anthropic",
    icon: "content-translator-anthropic",
  },
  google: {
    labelKey: "johannschopplich.content-translator.provider.google",
    icon: "content-translator-google",
  },
  mistral: {
    labelKey: "johannschopplich.content-translator.provider.mistral",
    icon: "content-translator-mistral",
  },
};

export interface TranslationDialogResult {
  provider: TranslationProvider;
}

export interface BatchTranslationDialogResult {
  provider: TranslationProvider;
  languages: (PanelLanguageInfo | PanelLanguage)[];
}

export function useTranslationDialogs() {
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

  async function openTranslationDialog(): Promise<
    TranslationDialogResult | undefined
  > {
    const { provider, providerField } = await getProviderConfig();

    if (!providerField) {
      return { provider };
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
        provider: providerField,
      },
      value: {
        provider,
      },
    });

    if (result?.provider) {
      storeProviderPreference(result.provider);
      return { provider: result.provider };
    }
  }

  async function openBatchTranslationDialog(): Promise<
    BatchTranslationDialogResult | undefined
  > {
    const { provider, providerField } = await getProviderConfig();

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
          label: panel.t("johannschopplich.content-translator.translateTo"),
          options: translationLanguages.map((language) => ({
            value: language.code,
            text: language.name,
          })),
          help: panel.t(
            "johannschopplich.content-translator.dialog.batchTranslation",
            { language: defaultLanguage.name },
          ),
        },
        ...(providerField && { provider: providerField }),
      },
      value: {
        provider,
        languages: translationLanguages.map((language) => language.code),
      },
    });

    if (result?.languages?.length) {
      if (result.provider) {
        storeProviderPreference(result.provider);
      }
      return {
        provider: result.provider ?? provider,
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

    const storedValue = sessionStorage.getItem(LICENSE_TOAST_COUNT_KEY);
    if (storedValue === "done") return;

    let translationCount = Number(storedValue) || 0;
    translationCount++;
    sessionStorage.setItem(LICENSE_TOAST_COUNT_KEY, String(translationCount));

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

    sessionStorage.setItem(LICENSE_TOAST_COUNT_KEY, "done");
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

/**
 * Determines the available translation provider(s) and builds the
 * provider field definition for dialogs.
 */
async function getProviderConfig() {
  const panel = usePanel();
  const context = await usePluginContext();
  const copilot = resolveCopilot();

  const { isCopilotAvailable, hasDefaultProvider, hasMultipleProviders } =
    getProviderAvailability(context.config);

  const provider = getValidStoredProvider({
    isCopilotAvailable,
    hasDefaultProvider,
  });

  if (!hasMultipleProviders) {
    return { provider, providerField: undefined };
  }

  // Fetch Copilot context for provider name
  let copilotContext: PluginContextResponse | undefined;
  if (copilot) {
    try {
      copilotContext = await copilot.resolvePluginContext();
    } catch {
      // Copilot context not available
    }
  }

  const aiProviderKey = copilotContext?.config?.provider;
  const aiProviderConfig = aiProviderKey
    ? PROVIDER_CONFIG[aiProviderKey]
    : undefined;

  const providerField = {
    type: "toggles",
    label: panel.t("johannschopplich.content-translator.dialog.translateWith"),
    labels: true,
    grow: true,
    options: [
      {
        value: "deepl",
        text: panel.t(
          context.config.translateFn
            ? "johannschopplich.content-translator.provider.custom"
            : "johannschopplich.content-translator.provider.deepl",
        ),
        icon: "translate",
      },
      {
        value: "ai",
        text: aiProviderConfig
          ? panel.t(aiProviderConfig.labelKey)
          : panel.t("johannschopplich.content-translator.provider.copilot"),
        // Fallback: https://getkirby.com/docs/reference/panel/icons/ai
        icon: aiProviderConfig?.icon ?? "ai",
      },
    ],
  };

  return { provider, providerField };
}

/**
 * Validates the stored provider preference against current availability.
 *
 * @remarks
 * Returns a valid provider, falling back if the stored one is no longer available.
 */
function getValidStoredProvider(availability: {
  isCopilotAvailable: boolean;
  hasDefaultProvider: boolean;
}): TranslationProvider {
  const storedProvider = localStorage.getItem(PROVIDER_PREFERENCE_KEY);

  // Validate stored preference is still available
  if (storedProvider === "ai" && availability.isCopilotAvailable) {
    return "ai";
  }
  if (storedProvider === "deepl" && availability.hasDefaultProvider) {
    return "deepl";
  }

  // Fall back to first available provider
  if (availability.hasDefaultProvider) return "deepl";
  if (availability.isCopilotAvailable) return "ai";

  return "deepl";
}

function storeProviderPreference(provider: TranslationProvider) {
  localStorage.setItem(PROVIDER_PREFERENCE_KEY, provider);
}
