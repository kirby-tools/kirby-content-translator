import type { PanelLanguageInfo } from "kirby-types";
import type { StrategyName } from "../types";
import type { PluginContextResponse } from "../utils/copilot-contract";
import type { StrategyAvailability } from "../utils/translator-config";
import { isLocalDev, useDialog, usePanel } from "kirbyuse";
import { STORAGE_KEY_PREFIX } from "../constants";
import { resolveCopilot, resolveCopilotReadiness } from "../utils/copilot";
import {
  createBatchTranslationDialogFields,
  createStrategyField,
  createTranslationDialogFields,
  createTranslationSubmitButton,
} from "../utils/dialog-fields";
import { getStrategyAvailability } from "../utils/translator-config";
import { usePluginContext } from "./plugin";

const LICENSE_TOAST_COUNT_KEY = `${STORAGE_KEY_PREFIX}licenseToastCount`;
// The key earlier versions stored the preference under, so a stored choice survives.
const STRATEGY_PREFERENCE_KEY = `${STORAGE_KEY_PREFIX}preferences$provider`;
const BATCH_LANGUAGES_PREFERENCE_KEY = `${STORAGE_KEY_PREFIX}preferences$batchLanguages`;
const LICENSE_TOAST_THRESHOLD = 2;

export interface TranslationDialogResult {
  strategyName: StrategyName;
}

export interface BatchTranslationDialogResult {
  strategyName: StrategyName;
  languages: PanelLanguageInfo[];
}

export function useTranslationDialogs() {
  const panel = usePanel();
  const { openFieldsDialog } = useDialog();

  const defaultLanguage = panel.languages.find((language) => language.default)!;
  const translationLanguages = panel.languages.filter(
    (language) => language.code !== defaultLanguage.code,
  );

  async function openTranslationDialog(
    help?: string,
  ): Promise<TranslationDialogResult | undefined> {
    const { strategyName, strategyField } = await resolveStrategyField();

    // Opens for the help alone, since it warns of a direct save that cannot be
    // taken back.
    if (!strategyField && !help) {
      return { strategyName };
    }

    const result = await openFieldsDialog({
      submitButton: createTranslationSubmitButton({ t: panel.t }),
      fields: createTranslationDialogFields({ help, strategyField }),
      value: {
        strategyName,
      },
    });

    if (result) {
      if (strategyField && result.strategyName) {
        storeStrategyPreference(result.strategyName);
      }
      return { strategyName: result.strategyName ?? strategyName };
    }
  }

  async function openBatchTranslationDialog(
    help: string,
  ): Promise<BatchTranslationDialogResult | undefined> {
    const { strategyName, strategyField } = await resolveStrategyField();

    const result = await openFieldsDialog({
      submitButton: createTranslationSubmitButton({ t: panel.t }),
      fields: createBatchTranslationDialogFields({
        help,
        strategyField,
        translationLanguages,
        t: panel.t,
      }),
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
    openTranslationDialog,
    openBatchTranslationDialog,
    showCopilotLicenseToastOnce,
  };
}

/**
 * Builds the field that picks a strategy, which a site with a single usable
 * strategy does not need.
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

  let copilotContext: PluginContextResponse | undefined;
  if (copilot) {
    try {
      copilotContext = await copilot.resolvePluginContext();
    } catch {
      // A missing Copilot context only costs the provider's own label.
    }
  }

  const strategyField = createStrategyField({
    isCustomStrategy: context.config.strategy === "custom",
    aiProvider: copilotContext?.config.provider,
    t: panel.t,
  });

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
