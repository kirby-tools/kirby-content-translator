import type { PanelLanguage, PanelLanguageInfo } from "kirby-types";
import type { StrategyName } from "../types";
import type { PluginContextResponse } from "../utils/copilot-contract";
import type { StrategyAvailability } from "../utils/translator-config";
import { isLocalDev, useDialog, usePanel } from "kirbyuse";
import { STORAGE_KEY_PREFIX } from "../constants";
import { resolveCopilot, resolveCopilotReadiness } from "../utils/copilot";
import { getStrategyAvailability } from "../utils/translator-config";
import { usePluginContext } from "./plugin";

const LICENSE_TOAST_COUNT_KEY = `${STORAGE_KEY_PREFIX}licenseToastCount`;
// The key earlier versions stored the preference under, so a stored choice survives.
const STRATEGY_PREFERENCE_KEY = `${STORAGE_KEY_PREFIX}preferences$provider`;
const BATCH_LANGUAGES_PREFERENCE_KEY = `${STORAGE_KEY_PREFIX}preferences$batchLanguages`;
const LICENSE_TOAST_THRESHOLD = 2;

const AI_PROVIDER_CONFIG: Record<string, { labelKey: string; icon: string }> = {
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
  strategyName: StrategyName;
}

export interface BatchTranslationDialogResult {
  strategyName: StrategyName;
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
    callback?: () => void | Promise<void>,
  ) {
    if (!shouldConfirm) {
      await callback?.();
      return;
    }

    const isOk = await openTextDialog(text);
    if (isOk) callback?.();
  }

  async function openTranslationDialog(): Promise<
    TranslationDialogResult | undefined
  > {
    const { strategyName, strategyField } = await resolveStrategyField();

    if (!strategyField) {
      return { strategyName };
    }

    const result = await openFieldsDialog({
      submitButton: {
        icon: "translate",
        theme: "positive",
        text: panel.t(
          "johannschopplich.content-translator.dialog.translateButton",
        ),
      },
      fields: {
        strategyName: strategyField,
      },
      value: {
        strategyName,
      },
    });

    if (result?.strategyName) {
      storeStrategyPreference(result.strategyName);
      return { strategyName: result.strategyName };
    }
  }

  async function openBatchTranslationDialog(): Promise<
    BatchTranslationDialogResult | undefined
  > {
    const { strategyName, strategyField } = await resolveStrategyField();

    const result = await openFieldsDialog({
      submitButton: {
        icon: "translate",
        theme: "positive",
        text: panel.t(
          "johannschopplich.content-translator.dialog.translateButton",
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
            "johannschopplich.content-translator.dialog.batchHelp",
            { language: defaultLanguage.name },
          ),
        },
        ...(strategyField && { strategyName: strategyField }),
      },
      value: {
        strategyName,
        languages: getValidStoredBatchLanguages(
          translationLanguages.map((language) => language.code),
        ),
      },
    });

    if (result?.languages?.length) {
      storeBatchLanguagesPreference(result.languages);
      if (result.strategyName) {
        storeStrategyPreference(result.strategyName);
      }
      return {
        strategyName: result.strategyName ?? strategyName,
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

    // Only show toast for unlicensed users.
    if (["inactive", "invalid"].includes(context.licenseStatus!)) {
      panel.notification.info({
        icon: "key",
        message: panel.t(
          "johannschopplich.content-translator.copilot.licenseToast",
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
 * Determines the available strategies and builds the dialog field that picks
 * one when there is more than one.
 */
async function resolveStrategyField() {
  const panel = usePanel();
  const context = await usePluginContext();
  const copilot = resolveCopilot();

  const availability = getStrategyAvailability(
    context.config,
    await resolveCopilotReadiness(),
  );
  const strategyName = resolvePreferredStrategy(availability);

  if (!availability.hasMultipleStrategies) {
    return { strategyName, strategyField: undefined };
  }

  // Fetch Copilot context for the provider name.
  let copilotContext: PluginContextResponse | undefined;
  if (copilot) {
    try {
      copilotContext = await copilot.resolvePluginContext();
    } catch {
      // A missing Copilot context only costs the provider's own label.
    }
  }

  const aiProviderKey = copilotContext?.config?.provider;
  const aiProviderConfig = aiProviderKey
    ? AI_PROVIDER_CONFIG[aiProviderKey]
    : undefined;

  const strategyField = {
    type: "toggles",
    label: panel.t("johannschopplich.content-translator.dialog.strategyLabel"),
    labels: true,
    grow: true,
    options: [
      {
        value: "deepl",
        text: panel.t(
          context.config.strategy === "custom"
            ? "johannschopplich.content-translator.strategy.custom"
            : "johannschopplich.content-translator.strategy.deepl",
        ),
        icon: "translate",
      },
      {
        value: "ai",
        text: aiProviderConfig
          ? panel.t(aiProviderConfig.labelKey)
          : panel.t("johannschopplich.content-translator.strategy.copilot"),
        // Fallback: https://getkirby.com/docs/reference/panel/icons/ai
        icon: aiProviderConfig?.icon ?? "ai",
      },
    ],
  };

  return { strategyName, strategyField };
}

function resolvePreferredStrategy(
  availability: StrategyAvailability,
): StrategyName {
  const storedStrategyName = localStorage.getItem(STRATEGY_PREFERENCE_KEY);

  if (storedStrategyName === "ai" && availability.isCopilotReady) {
    return "ai";
  }
  if (storedStrategyName === "deepl" && availability.hasDefaultStrategy) {
    return "deepl";
  }

  return availability.hasDefaultStrategy ? "deepl" : "ai";
}

function storeStrategyPreference(strategyName: StrategyName) {
  localStorage.setItem(STRATEGY_PREFERENCE_KEY, strategyName);
}

function getValidStoredBatchLanguages(availableCodes: string[]): string[] {
  const storedValue = localStorage.getItem(BATCH_LANGUAGES_PREFERENCE_KEY);
  if (!storedValue) return availableCodes;

  try {
    const parsedValue = JSON.parse(storedValue);
    if (!Array.isArray(parsedValue)) return availableCodes;

    const validCodes = parsedValue.filter(
      (code): code is string =>
        typeof code === "string" && availableCodes.includes(code),
    );
    return validCodes.length > 0 ? validCodes : availableCodes;
  } catch {
    return availableCodes;
  }
}

function storeBatchLanguagesPreference(languages: string[]) {
  localStorage.setItem(
    BATCH_LANGUAGES_PREFERENCE_KEY,
    JSON.stringify(languages),
  );
}
