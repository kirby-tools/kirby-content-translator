import type { PanelLanguageInfo } from "kirby-types";
import type { BatchOutcome } from "../translation/batch";
import type { KirbyTagRules, TranslationStrategy } from "../translation/types";
import type { VariableTranslationPlan } from "../translation/variables";
import type {
  StrategyName,
  TranslatorOptions,
  VariablesResponse,
} from "../types";
import { useHelpers, usePanel } from "kirbyuse";
import pAll from "p-all";
import {
  DEFAULT_BATCH_TRANSLATION_CONCURRENCY,
  VARIABLES_API_ROUTE,
} from "../constants";
import { shouldReportBatchOutcome } from "../translation/report";
import { reportRejections } from "../translation/result";
import {
  isPending,
  planVariableTranslation,
  translateVariables,
} from "../translation/variables";
import { formatList, translatePlural } from "../utils/i18n";
import { createStrategy } from "../utils/strategy";
import {
  resolveKirbyTagRules,
  resolveTranslatorConfig,
} from "../utils/translator-config";
import { useTranslationDialogs } from "./dialogs";
import { useTranslationNotifications } from "./notifications";
import { usePluginContext } from "./plugin";
import { useTranslationState } from "./translation";

/** Message keys that say "not translated", since a missing variable has no current value to keep. */
const VARIABLE_REJECTION_MESSAGE_KEYS = {
  partiallyTranslated:
    "johannschopplich.content-translator.notification.variablesPartiallyTranslated",
  noneTranslated:
    "johannschopplich.content-translator.notification.variablesNoneTranslated",
  batchPartiallyTranslated:
    "johannschopplich.content-translator.notification.variablesBatchPartiallyTranslated",
  keptSource:
    "johannschopplich.content-translator.batchReport.variableNotTranslated",
};

export function useVariableTranslation(options: TranslatorOptions = {}) {
  const panel = usePanel();
  const helpers = useHelpers();
  const { isTranslating } = useTranslationState();
  const {
    openTranslationDialog,
    openBatchTranslationDialog,
    showCopilotLicenseToastOnce,
  } = useTranslationDialogs();
  const {
    notifyProgress,
    notifyTranslationResult,
    notifyBatchTranslationResult,
    openBatchReport,
  } = useTranslationNotifications(VARIABLE_REJECTION_MESSAGE_KEYS);

  const defaultLanguage = panel.languages.find((language) => language.default)!;

  /**
   * Translates the default language's variables that are missing or blank in
   * `targetLanguage`, or still identical to the default language, and saves
   * them directly.
   */
  async function translateLanguageVariables(targetLanguage: PanelLanguageInfo) {
    if (panel.view.isLoading || isTranslating.value) return;

    let strategyName: StrategyName;
    panel.view.isLoading = true;

    try {
      const context = await usePluginContext();
      const config = resolveTranslatorConfig(context.config, options);
      const kirbyTags = resolveKirbyTagRules(context, config);
      const plan = planLanguage(
        await fetchVariables(),
        targetLanguage,
        kirbyTags,
      );
      const pendingCount = Object.keys(plan.pending).length;
      // The view stays usable while the editor reads the count.
      panel.view.isLoading = false;

      if (pendingCount === 0) {
        panel.notification.open({
          message: describeNothingToTranslate(plan.translatableKeys, [
            targetLanguage,
          ]),
          icon: "info",
          theme: "info",
        });
        return;
      }

      const dialogResult = await openTranslationDialog(
        translatePlural(
          panel.t,
          "johannschopplich.content-translator.dialog.variablesHelp",
          {
            count: pendingCount,
            total: plan.translatableKeys.length,
            source: helpers.string.escapeHTML(defaultLanguage.name),
            target: helpers.string.escapeHTML(targetLanguage.name),
          },
          pendingCount,
        ),
      );
      if (!dialogResult) return;

      strategyName = dialogResult.strategyName;
      panel.view.isLoading = true;
      isTranslating.value = true;
      notifyProgress(
        panel.t(
          "johannschopplich.content-translator.notification.variablesTranslating",
        ),
      );

      const result = await translateAndSave(
        targetLanguage,
        plan,
        createStrategy(strategyName, config.systemPrompt),
        kirbyTags,
      );

      isTranslating.value = false;

      if (panel.view.path === `languages/${targetLanguage.code}`) {
        // Reload will also end Panel loading state.
        await panel.view.reload();
      } else {
        panel.view.isLoading = false;
      }

      notifyTranslationResult(result, {
        successMessage: panel.t(
          "johannschopplich.content-translator.notification.variablesTranslated",
        ),
        nothingToTranslateMessage: describeNothingToTranslate(
          plan.translatableKeys,
          [targetLanguage],
        ),
        labelField: labelVariable,
      });
    } catch (error) {
      isTranslating.value = false;
      panel.view.isLoading = false;
      console.error("Failed to translate language variables:", error);
      panel.notification.error((error as Error).message);
      return;
    }

    if (strategyName === "ai") {
      showCopilotLicenseToastOnce();
    }
  }

  /**
   * Translates the default language's variables into the secondary languages
   * the editor selects, each saved directly.
   */
  async function batchTranslateLanguageVariables() {
    if (panel.view.isLoading || isTranslating.value) return;

    const dialogResult = await openBatchTranslationDialog(
      panel.t("johannschopplich.content-translator.dialog.variablesBatchHelp", {
        language: helpers.string.escapeHTML(defaultLanguage.name),
      }),
    );
    if (!dialogResult) return;

    const selectedLanguages = dialogResult.languages;
    panel.view.isLoading = true;
    isTranslating.value = true;

    try {
      const context = await usePluginContext();
      const config = resolveTranslatorConfig(context.config, options);
      const kirbyTags = resolveKirbyTagRules(context, config);
      const languages = await fetchVariables();
      const strategy = createStrategy(
        dialogResult.strategyName,
        config.systemPrompt,
      );
      const notifyLanguageProgress = (current: number) =>
        notifyProgress(
          panel.t(
            "johannschopplich.content-translator.notification.variablesBatchTranslating",
            { current, total: selectedLanguages.length },
          ),
        );
      const translatableKeys = new Set<string>();
      let completedCount = 0;

      notifyLanguageProgress(completedCount);

      // A language is isolated so one dead provider call cannot discard what
      // is already saved or stop what is still queued.
      const outcomes = await pAll(
        selectedLanguages.map((language) => async (): Promise<BatchOutcome> => {
          try {
            const plan = planLanguage(languages, language, kirbyTags);
            for (const key of plan.translatableKeys) translatableKeys.add(key);

            const result = await translateAndSave(
              language,
              plan,
              strategy,
              kirbyTags,
            );
            return { language, status: "saved", result };
          } catch (error) {
            console.error(
              `Failed to translate the language variables into "${language.code}":`,
              error,
            );
            return {
              language,
              status: "failed",
              message: error instanceof Error ? error.message : String(error),
            };
          } finally {
            notifyLanguageProgress(++completedCount);
          }
        }),
        {
          concurrency:
            context.config.batchConcurrency ??
            DEFAULT_BATCH_TRANSLATION_CONCURRENCY,
        },
      );

      isTranslating.value = false;
      panel.view.isLoading = false;

      const labelOutcome = (outcome: BatchOutcome) => outcome.language.name;

      if (outcomes.some(shouldReportBatchOutcome)) {
        panel.notification.close();
        openBatchReport(outcomes, {
          labelOutcome,
          labelField: labelVariable,
          messageKey: "johannschopplich.content-translator.batchReport.message",
        });
      } else {
        notifyBatchTranslationResult(outcomes, {
          labelOutcome,
          labelField: labelVariable,
          successMessage: panel.t(
            "johannschopplich.content-translator.notification.variablesBatchTranslated",
          ),
          nothingToTranslateMessage: describeNothingToTranslate(
            [...translatableKeys],
            selectedLanguages,
          ),
        });
      }
    } catch (error) {
      isTranslating.value = false;
      panel.view.isLoading = false;
      console.error("Failed to batch translate language variables:", error);
      panel.notification.error((error as Error).message);
      return;
    }

    if (dialogResult.strategyName === "ai") {
      showCopilotLicenseToastOnce();
    }
  }

  async function translateAndSave(
    targetLanguage: PanelLanguageInfo,
    plan: VariableTranslationPlan,
    strategy: TranslationStrategy,
    kirbyTags: KirbyTagRules,
  ) {
    const { translatedVariables, result } = await translateVariables(
      plan.pending,
      {
        strategy,
        sourceLanguage: defaultLanguage,
        targetLanguage,
        kirbyTags,
      },
    );

    reportRejections(result, targetLanguage);

    if (Object.keys(translatedVariables).length === 0) return result;

    // Kirby replaces the whole set with the one it receives, so the set is read
    // again right before the save, and a variable another editor translated
    // since the plan keeps their value.
    const { variables } = findLanguageVariables(
      await fetchVariables(),
      targetLanguage,
    );
    const savedVariables = Object.fromEntries(
      Object.entries(translatedVariables).filter(([key]) =>
        isPending(variables[key], plan.pending[key]),
      ),
    );

    if (Object.keys(savedVariables).length > 0) {
      await panel.api.patch(`languages/${targetLanguage.code}`, {
        translations: { ...variables, ...savedVariables },
      });
    }

    const resolvedByOthersCount =
      Object.keys(translatedVariables).length -
      Object.keys(savedVariables).length;

    return {
      ...result,
      translatableCount: result.translatableCount - resolvedByOthersCount,
      translatedCount: result.translatedCount - resolvedByOthersCount,
    };
  }

  function planLanguage(
    languages: VariablesResponse,
    targetLanguage: PanelLanguageInfo,
    kirbyTags: KirbyTagRules,
  ): VariableTranslationPlan {
    const target = findLanguageVariables(languages, targetLanguage);
    return planVariableTranslation(
      findLanguageVariables(languages, defaultLanguage).variables,
      target.variables,
      {
        reservedKeys: target.reservedKeys,
        kirbyTags,
      },
    );
  }

  function findLanguageVariables(
    languages: VariablesResponse,
    language: PanelLanguageInfo,
  ) {
    const entry = languages[language.code];
    // `panel.languages` can still list a language another tab deleted.
    if (!entry) throw new Error(panel.t("error.language.notFound"));
    return entry;
  }

  function describeNothingToTranslate(
    translatableKeys: string[],
    targetLanguages: PanelLanguageInfo[],
  ) {
    return translatableKeys.length === 0
      ? panel.t(
          "johannschopplich.content-translator.notification.variablesNothingToTranslate",
          { source: defaultLanguage.name },
        )
      : panel.t(
          "johannschopplich.content-translator.notification.variablesAllTranslated",
          {
            target: formatList(
              targetLanguages.map((language) => language.name),
              panel.translation.code,
            ),
          },
        );
  }

  function fetchVariables() {
    return panel.api.get<VariablesResponse>(
      VARIABLES_API_ROUTE,
      undefined,
      undefined,
      // Avoid showing Panel loading indicator.
      true,
    );
  }

  return {
    translateLanguageVariables,
    batchTranslateLanguageVariables,
  };
}

/** Names a variable by its whole key: a dot in it is part of the name, not a path to a parent field. */
function labelVariable(fieldKey: string | undefined) {
  return fieldKey ?? "";
}
