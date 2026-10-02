import type { LicenseStatus } from "@kirby-tools/licensing";
import type {
  KirbyFieldProps,
  NotificationTheme,
  PanelLanguage,
  PanelLanguageInfo,
  PanelModelData,
} from "kirby-types";
import type { BatchOutcome } from "../translation/batch";
import type { BatchCandidate } from "../translation/batch-plan";
import type {
  ContentTranslationResult,
  KirbyTagRules,
  TranslationStrategy,
} from "../translation/types";
import type {
  BatchStatusResponse,
  BatchWriteResponse,
  CascadeModelResponse,
  PluginConfig,
  PluginContextResponse,
  StrategyName,
  TranslatorOptions,
} from "../types";
import { isKirby5, ref, useContent, useI18n, usePanel } from "kirbyuse";
import pAll from "p-all";
import {
  BATCH_STATUS_API_ROUTE,
  BATCH_WRITE_API_ROUTE,
  CASCADE_API_ROUTE,
  DEFAULT_BATCH_TRANSLATION_CONCURRENCY,
} from "../constants";
import {
  AIStrategy,
  DeepLStrategy,
  translateContent,
  translateTitle,
} from "../translation";
import { runBatchTranslation } from "../translation/batch";
import { planBatchRun } from "../translation/batch-plan";
import { planImport, planSingleTranslation } from "../translation/plan";
import {
  describeBatchOutcomes,
  listKeptSourceFields,
  shouldReportBatchOutcome,
} from "../translation/report";
import {
  mergeTranslationResults,
  reportRejections,
} from "../translation/result";
import { resolveCopilotReadiness } from "../utils/copilot";
import { filterEligibleContent, isEligibleField } from "../utils/filter";
import { formatList, translatePlural } from "../utils/i18n";
import { isFileModelPath, isSiteModelPath } from "../utils/model-path";
import {
  describeMissingStrategy,
  getStrategyAvailability,
  resolveTranslatorConfig,
} from "../utils/translator-config";
import { useModel } from "./model";
import { createGlobalState } from "./state";

/**
 * Long enough that a notification stays until something replaces it. A falsy
 * timeout would be coerced back to four seconds, and `type: "error"` – the
 * other way out of that coercion – keeps the view shell from rendering it.
 */
const PERSISTENT_TIMEOUT = 60 * 60 * 1000;

export const useTranslationState = createGlobalState(() => {
  const isTranslating = ref(false);

  return {
    isTranslating,
  };
});

export function useContentTranslator() {
  const panel = usePanel();
  const {
    currentContent,
    isEditable: isContentEditable,
    update: updateContent,
  } = useContent();
  const { t } = useI18n();
  const { getModelData } = useModel();
  const { isTranslating } = useTranslationState();

  // #region Configuration state
  const label = ref<string>();
  const isImportEnabled = ref<boolean>();
  const importFrom = ref<string>();
  const isBatchTranslationEnabled = ref<boolean>();
  const isTitleTranslationEnabled = ref<boolean>();
  const isSlugTranslationEnabled = ref<boolean>();
  const shouldConfirm = ref<boolean>();
  const fieldTypes = ref<string[]>([]);
  const includeFields = ref<string[]>([]);
  const excludeFields = ref<string[]>([]);
  const kirbyTags = ref<KirbyTagRules>({ types: [], attributes: {} });
  const strategyName = ref<StrategyName>("deepl");
  const systemPrompt = ref<string>();
  const hasCascade = ref(false);
  // #endregion

  // #region Runtime state
  const fields = ref<Record<string, KirbyFieldProps>>();
  const config = ref<PluginConfig>();
  const homePageId = ref<string>();
  const errorPageId = ref<string>();
  const licenseStatus = ref<LicenseStatus>();
  const hasAnyStrategy = ref(false);
  const missingStrategyMessage = ref<string>();

  // #endregion

  /**
   * Assigns the configuration synchronously. The returned promise settles once
   * the usable strategies are known – resolving them asks Kirby Copilot for its
   * configuration.
   */
  async function initializeConfig(
    context: PluginContextResponse,
    options: TranslatorOptions = {},
  ) {
    label.value =
      t(options.label) || panel.t("johannschopplich.content-translator.label");

    const resolvedConfig = resolveTranslatorConfig(context.config, options);
    isImportEnabled.value = resolvedConfig.isImportEnabled;
    importFrom.value = resolvedConfig.importFrom;
    // TODO: Drop K4 compat in v4 – remove the `isKirby5()` check once Kirby 5 is the floor.
    isBatchTranslationEnabled.value =
      isKirby5() && resolvedConfig.isBatchTranslationEnabled;
    isTitleTranslationEnabled.value = resolvedConfig.isTitleTranslationEnabled;
    isSlugTranslationEnabled.value = resolvedConfig.isSlugTranslationEnabled;
    shouldConfirm.value = resolvedConfig.shouldConfirm;
    fieldTypes.value = resolvedConfig.fieldTypes;
    includeFields.value = resolvedConfig.includeFields;
    excludeFields.value = resolvedConfig.excludeFields;
    kirbyTags.value = {
      types: context.kirbyTagTypes,
      attributes: resolvedConfig.kirbyTags,
    };
    systemPrompt.value = resolvedConfig.systemPrompt;
    // The cascade is saved through `batch-write`, which needs Kirby 5.
    // TODO: Drop K4 compat in v4 – remove the `isKirby5()` check once Kirby 5 is the floor.
    hasCascade.value = isKirby5() && resolvedConfig.hasCascade;

    fields.value = options.fields ?? {};
    config.value = context.config;
    homePageId.value = context.homePageId;
    errorPageId.value = context.errorPageId;
    licenseStatus.value = __PLAYGROUND__ ? "active" : context.licenseStatus;

    const copilotReadiness = await resolveCopilotReadiness();
    hasAnyStrategy.value = getStrategyAvailability(
      context.config,
      copilotReadiness,
    ).hasAnyStrategy;
    missingStrategyMessage.value = hasAnyStrategy.value
      ? undefined
      : describeMissingStrategy(context.config, copilotReadiness);
  }

  async function hasResolvedBlueprint(path: string) {
    if (fields.value && Object.keys(fields.value).length > 0) return true;

    // Kirby falls back to the `default` blueprint when the template's own is
    // missing. A blueprint of the template's own without fields is a title-only
    // model, whatever keys an earlier template left in its content.
    const { template, blueprint } = await panel.api.get<{
      template?: string | null;
      blueprint: { name: string };
    }>(path, { select: "template,blueprint" }, undefined, true);
    const isBlueprintMissing =
      Boolean(template) &&
      template !== "default" &&
      blueprint.name.endsWith("/default");
    if (!isBlueprintMissing) return true;

    console.error(
      `No blueprint fields could be resolved for "${path}". Check that the model's blueprint exists and that its filename matches the template exactly, including case.`,
    );
    panel.notification.error(
      panel.t("johannschopplich.content-translator.error.unresolvedFields"),
    );

    return false;
  }

  /**
   * Separates a configuration that rules out every field of the host from
   * content with nothing to translate.
   */
  function hasEligibleFields() {
    return Object.entries(fields.value ?? {}).some(([name, field]) =>
      isEligibleField(name, field, {
        fieldTypes: fieldTypes.value,
        includeFields: includeFields.value,
        excludeFields: excludeFields.value,
      }),
    );
  }

  function notifyProgress(current: number, total: number) {
    panel.notification.open({
      message: panel.t(
        "johannschopplich.content-translator.notification.batchTranslating",
        { current, total },
      ),
      icon: "loader",
      theme: "info",
      timeout: PERSISTENT_TIMEOUT,
    });
  }

  function notifyPartialTranslation(message: string) {
    panel.notification.open({
      message,
      icon: "alert",
      theme: "notice" as NotificationTheme,
      timeout: PERSISTENT_TIMEOUT,
    });
  }

  // Only one notification is visible at a time, so the most specific outcome wins.
  function notifyTranslationResult(
    result: ContentTranslationResult,
    successMessage: string,
  ) {
    if (result.translatableCount === 0) {
      panel.notification.open({
        message: hasEligibleFields()
          ? panel.t(
              "johannschopplich.content-translator.notification.nothingToTranslate",
            )
          : panel.t(
              "johannschopplich.content-translator.notification.noEligibleFields",
              { fieldTypes: fieldTypes.value.join(", ") },
            ),
        icon: "info",
        theme: "info",
      });
      return;
    }

    const untranslatedCount = result.translatableCount - result.translatedCount;

    if (untranslatedCount === 0) {
      panel.notification.success(successMessage);
      return;
    }

    if (result.translatedCount === 0) {
      // Not `notification.error`, which in a view also opens Kirby's blocking
      // error dialog.
      panel.notification.open({
        message: panel.t(
          "johannschopplich.content-translator.notification.noSegmentTranslated",
          { total: result.translatableCount },
        ),
        icon: "alert",
        theme: "negative",
        timeout: PERSISTENT_TIMEOUT,
      });
      return;
    }

    notifyPartialTranslation(
      translatePlural(
        panel.t,
        "johannschopplich.content-translator.notification.partiallyTranslated",
        {
          untranslated: untranslatedCount,
          total: result.translatableCount,
          fields: listKeptSourceFields(result.rejections, {
            fields: fields.value,
            t: panel.t,
          }),
        },
        untranslatedCount,
      ),
    );
  }

  /**
   * Notifies per language rather than summing across them: one total would
   * fold a language at 0 of 10 into "10 of 20 kept their source text" and hide
   * which language went wrong.
   */
  function notifyBatchTranslationResult(
    outcomes: BatchOutcome[],
    labelOutcome: (outcome: BatchOutcome) => string,
  ) {
    const savedResults = outcomes.flatMap((outcome) =>
      outcome.status === "saved" ? [outcome.result] : [],
    );
    const languagesWithKeptSource = outcomes.flatMap((outcome) => {
      if (
        outcome.status !== "saved" ||
        outcome.result.translatedCount === outcome.result.translatableCount
      ) {
        return [];
      }

      return [
        `${labelOutcome(outcome)} (${listKeptSourceFields(outcome.result.rejections, { fields: outcome.model.fields, t: panel.t })})`,
      ];
    });

    if (languagesWithKeptSource.length === 0) {
      notifyTranslationResult(
        mergeTranslationResults(savedResults),
        panel.t(
          "johannschopplich.content-translator.notification.batchTranslated",
        ),
      );
      return;
    }

    notifyPartialTranslation(
      panel.t(
        "johannschopplich.content-translator.notification.batchPartiallyTranslated",
        {
          languages: formatList(
            languagesWithKeptSource,
            panel.translation.code,
          ),
        },
      ),
    );
  }

  // TODO: Next major version – unify import flow through a server-side
  // `copyContent` API endpoint. When importing from the default language,
  // delete the content file (Kirby inherits automatically) and reload the
  // Panel instead of using `updateContent()`. For non-default `importFrom`
  // sources, the server-side `copyContent` behavior is kept. This also removes
  // the need for client-side `filterEligibleContent` during import and the
  // title/slug patching for default-language imports.
  // TODO: Next major version – remove confirm dialog options entirely.
  /**
   * Imports the content of `sourceLanguage`, or of the default language, into
   * the form of the view at `path` in `targetLanguage`, both taken when the
   * editor starts the import. Its title and slug requests name
   * `targetLanguage`, since Kirby's API otherwise sends the language open then.
   */
  async function importModelContent(
    path: string,
    targetLanguage: PanelLanguageInfo | PanelLanguage,
    sourceLanguage?: PanelLanguageInfo | PanelLanguage,
  ) {
    if (!(await hasResolvedBlueprint(path))) return;

    let title: string;
    let content: Record<string, unknown>;

    if (sourceLanguage) {
      const data = await panel.api.get<PanelModelData>(
        path,
        { language: sourceLanguage.code },
        undefined,
        // Avoid showing Panel loading indicator.
        true,
      );
      title = data.title;
      content = data.content;
    } else {
      const data = await getModelData(path);
      title = data.title;
      content = data.content;
    }

    const eligibleContent = filterEligibleContent(content, {
      fields: fields.value!,
      fieldTypes: fieldTypes.value,
      includeFields: includeFields.value,
      excludeFields: excludeFields.value,
    });

    const plan = planImport({
      isHomePage: await isHomePage(path),
      isErrorPage: await isErrorPage(path),
      isFileModel: isFileModelPath(path),
      isSiteModel: isSiteModelPath(path),
      isTitleTranslationEnabled: isTitleTranslationEnabled.value === true,
      isSlugTranslationEnabled: isSlugTranslationEnabled.value === true,
      isCurrentLanguageDefault: targetLanguage.default === true,
    });

    // Checked before the first write, so an import that stops has written
    // nothing.
    if (!isViewOpen(path, targetLanguage.code)) {
      panel.notification.error(
        panel.t("johannschopplich.content-translator.error.viewChanged"),
      );
      return;
    }

    const hasEligibleContent = Object.keys(eligibleContent).length > 0;

    if (
      !hasEligibleContent &&
      !plan.shouldPatchTitle &&
      !plan.shouldPatchSlug
    ) {
      panel.notification.open({
        message: panel.t(
          "johannschopplich.content-translator.notification.nothingToImport",
        ),
        icon: "info",
        theme: "info",
      });
      return;
    }

    await updateContent(eligibleContent);

    if (plan.shouldPatchTitle) {
      await panel.api.patch(
        `${path}/title`,
        { title },
        { headers: { "x-language": targetLanguage.code } },
      );
    }
    if (plan.shouldPatchSlug) {
      // Kirby sanitizes the slug with the slug rules of the language the
      // request names.
      await panel.api.patch(
        `${path}/slug`,
        { slug: title },
        { headers: { "x-language": targetLanguage.code } },
      );
    }
    if (plan.shouldPatchTitle || plan.shouldPatchSlug) {
      await panel.view.reload();
    }

    panel.notification.success(
      panel.t("johannschopplich.content-translator.notification.imported"),
    );
  }

  /**
   * Translates the form of the view at `path` into `targetLanguage`, both taken
   * when the editor starts the translation. Its title and slug requests name
   * `targetLanguage`, since Kirby's API otherwise sends the language open then.
   */
  async function translateModelContent(
    path: string,
    targetLanguage: PanelLanguageInfo | PanelLanguage,
    sourceLanguage?: PanelLanguageInfo | PanelLanguage,
  ) {
    if (panel.view.isLoading || isTranslating.value) return;
    if (!(await hasResolvedBlueprint(path))) return;

    // Checked before the view's title and form are read.
    if (!isViewOpen(path, targetLanguage.code)) {
      panel.notification.error(
        panel.t("johannschopplich.content-translator.error.viewChanged"),
      );
      return;
    }

    const { title } = panel.view;
    panel.view.isLoading = true;
    isTranslating.value = true;

    panel.notification.open({
      message: panel.t(
        "johannschopplich.content-translator.notification.translating",
      ),
      icon: "loader",
      theme: "info",
      timeout: PERSISTENT_TIMEOUT,
    });

    try {
      const contentCopy: Record<string, unknown> = JSON.parse(
        JSON.stringify(currentContent.value),
      );

      const strategy =
        strategyName.value === "ai"
          ? new AIStrategy({ systemPrompt: systemPrompt.value })
          : new DeepLStrategy();

      const contentResult = await translateContent(contentCopy, {
        strategy,
        sourceLanguage,
        targetLanguage,
        fieldTypes: fieldTypes.value,
        includeFields: includeFields.value,
        excludeFields: excludeFields.value,
        kirbyTags: kirbyTags.value,
        fields: fields.value!,
      });

      // Reported before the content is written, because everything from here
      // to the notification can throw and would take the rejections with it.
      reportRejections(contentResult, targetLanguage);

      // Checked before the first write, so a run that stops has written
      // nothing.
      if (!isViewOpen(path, targetLanguage.code)) {
        throw new Error(
          panel.t("johannschopplich.content-translator.error.viewChanged"),
        );
      }

      await updateContent(contentCopy);
      const plan = planSingleTranslation({
        isHomePage: await isHomePage(path),
        isErrorPage: await isErrorPage(path),
        isFileModel: isFileModelPath(path),
        isSiteModel: isSiteModelPath(path),
        isTitleTranslationEnabled: isTitleTranslationEnabled.value === true,
        isSlugTranslationEnabled: isSlugTranslationEnabled.value === true,
        isTargetLanguageDefault: targetLanguage.default === true,
        hasViewTitle: Boolean(title),
      });

      const languageResults = [contentResult];

      if (plan.shouldRequestTitleTranslation) {
        const translatedTitle = await translateTitle(
          // Non-null: the plan requests a title translation only when the view has one.
          title!,
          { strategy, targetLanguage, sourceLanguage },
        );
        languageResults.push(translatedTitle.result);

        if (translatedTitle.text !== undefined && plan.shouldPatchTitle) {
          await panel.api.patch(
            `${path}/title`,
            { title: translatedTitle.text },
            { headers: { "x-language": targetLanguage.code } },
          );
        }

        if (translatedTitle.text !== undefined && plan.shouldPatchSlug) {
          // Kirby sanitizes the slug with the slug rules of the language the
          // request names.
          await panel.api.patch(
            `${path}/slug`,
            { slug: translatedTitle.text },
            { headers: { "x-language": targetLanguage.code } },
          );
        }
      }

      // A cascade that fails is reported only after the view reloaded: the
      // host's fields are in the form and its title is saved by now.
      let cascadeOutcomes: BatchOutcome[] = [];
      let cascadeError: unknown;

      try {
        cascadeOutcomes = await translateCascade(
          path,
          targetLanguage,
          strategy,
        );
      } catch (error) {
        cascadeError = error;
      }

      isTranslating.value = false;

      if (plan.shouldRequestTitleTranslation || cascadeOutcomes.length > 0) {
        // Reload will also end Panel loading state.
        await panel.view.reload();
      } else {
        panel.view.isLoading = false;
      }

      if (cascadeError !== undefined) throw cascadeError;

      const hostResult = mergeTranslationResults(languageResults);
      const translatedCascadePaths = cascadeOutcomes.flatMap((outcome) =>
        outcome.status === "saved" && outcome.result.translatedCount > 0
          ? [outcome.model.path]
          : [],
      );

      const notifyHostResult = () => {
        if (translatedCascadePaths.length === 0) {
          notifyTranslationResult(
            hostResult,
            panel.t(
              "johannschopplich.content-translator.notification.translated",
            ),
          );
        } else if (hostResult.translatableCount === 0) {
          // A host without text of its own, such as a page that only holds
          // modules, would otherwise report that there was nothing to translate.
          panel.notification.success(
            panel.t(
              "johannschopplich.content-translator.notification.translatedCascade",
              { models: describeCascadeModels(translatedCascadePaths) },
            ),
          );
        } else {
          notifyTranslationResult(
            hostResult,
            panel.t(
              "johannschopplich.content-translator.notification.translatedWithCascade",
              { models: describeCascadeModels(translatedCascadePaths) },
            ),
          );
        }
      };

      // The notification speaks for the host only, whose fields it can name,
      // so every cascaded model that kept a source text is reported too.
      const hasCascadeReport = cascadeOutcomes.some(
        (outcome) =>
          shouldReportBatchOutcome(outcome) ||
          (outcome.status === "saved" && outcome.result.rejections.length > 0),
      );

      if (!hasCascadeReport) {
        notifyHostResult();
        return;
      }

      // Kirby closes every notification when a dialog opens, so the host's
      // result follows once the report is closed.
      openBatchReport(
        cascadeOutcomes,
        (outcome) => outcome.model.title,
        "johannschopplich.content-translator.batchReport.cascadeMessage",
        notifyHostResult,
      );
    } catch (error) {
      isTranslating.value = false;
      panel.view.isLoading = false;
      console.error("Failed to translate content:", error);
      panel.notification.error((error as Error).message);
    }
  }

  /**
   * Translates the model at `path`, taken when the editor starts the
   * translation, into the selected languages and saves each translation.
   */
  async function batchTranslateModelContent(
    path: string,
    selectedLanguages: (PanelLanguageInfo | PanelLanguage)[],
  ) {
    if (panel.view.isLoading || isTranslating.value) return;
    if (!(await hasResolvedBlueprint(path))) return;

    // Checked before the view's title is read. The run names every language it
    // reads or writes, so the language the view is open in does not matter.
    if (panel.view.path !== path) {
      panel.notification.error(
        panel.t("johannschopplich.content-translator.error.viewChanged"),
      );
      return;
    }

    const { title } = panel.view;
    panel.view.isLoading = true;
    isTranslating.value = true;

    try {
      const defaultLanguageData = await getModelData(path);
      const batchStatus = await fetchBatchStatus(path);

      // Nothing is translated that could not be saved: Kirby refuses the write
      // without the update permission, and refuses every language while
      // another user edits one of them.
      if (!batchStatus.isUpdateAllowed || batchStatus.lockedBy !== null) {
        isTranslating.value = false;
        panel.view.isLoading = false;
        panel.notification.error(
          batchStatus.isUpdateAllowed
            ? panel.t("johannschopplich.content-translator.error.batchLocked", {
                user: batchStatus.lockedBy,
              })
            : panel.t(
                "johannschopplich.content-translator.error.batchForbidden",
              ),
        );
        return;
      }

      const strategy =
        strategyName.value === "ai"
          ? new AIStrategy({ systemPrompt: systemPrompt.value })
          : new DeepLStrategy();

      const outcomes = await translateAndSave(
        path,
        {
          path,
          title: title ?? path,
          isHomePage: defaultLanguageData.id === homePageId.value,
          isErrorPage: defaultLanguageData.id === errorPageId.value,
          defaultLanguageData,
          fields: fields.value!,
          status: batchStatus,
        },
        selectedLanguages,
        { strategy, onProgress: notifyProgress },
      );
      const hasCascadedModel = outcomes.some(
        ({ model }) => model.path !== path,
      );
      const labelOutcome = (outcome: BatchOutcome) =>
        hasCascadedModel
          ? `${outcome.model.title} – ${outcome.language.name}`
          : outcome.language.name;

      const hasReport = outcomes.some(shouldReportBatchOutcome);

      if (!hasReport) {
        notifyBatchTranslationResult(outcomes, labelOutcome);
      }

      isTranslating.value = false;
      // Reload will also end Panel loading state.
      await panel.view.reload();

      if (hasReport) {
        // Opened after the reload, so the saved languages are on screen before
        // the dialog covers them.
        panel.notification.close();
        openBatchReport(
          outcomes,
          labelOutcome,
          hasCascadedModel
            ? "johannschopplich.content-translator.batchReport.cascadeMessage"
            : "johannschopplich.content-translator.batchReport.message",
        );
      }
    } catch (error) {
      isTranslating.value = false;
      panel.view.isLoading = false;
      console.error("Failed to batch translate content:", error);
      panel.notification.error((error as Error).message);
    }
  }

  /**
   * Translates the host at `hostPath`, if it takes part, and its cascade into
   * the selected languages and saves each translation directly, returning one
   * outcome per model and selected language with the host first.
   */
  async function translateAndSave(
    hostPath: string,
    host: BatchCandidate | undefined,
    selectedLanguages: (PanelLanguageInfo | PanelLanguage)[],
    {
      strategy,
      onProgress,
    }: {
      strategy: TranslationStrategy;
      onProgress?: (completed: number, total: number) => void;
    },
  ) {
    const sourceLanguage = panel.languages.find(
      (language) => language.default,
    )!;
    const globalConfig = resolveTranslatorConfig(config.value!);
    const models = planBatchRun(
      host,
      hasCascade.value
        ? await getCascadeCandidates(hostPath, sourceLanguage.code)
        : [],
      {
        selectedLanguages,
        defaultLanguageCode: sourceLanguage.code,
        settings: {
          fieldTypes: fieldTypes.value,
          includeFields: includeFields.value,
          excludeFields: excludeFields.value,
          kirbyTags: kirbyTags.value,
          isTitleTranslationEnabled: isTitleTranslationEnabled.value === true,
          isSlugTranslationEnabled: isSlugTranslationEnabled.value === true,
        },
        globalFieldLists: {
          includeFields: globalConfig.includeFields,
          excludeFields: globalConfig.excludeFields,
        },
      },
    );

    return await runBatchTranslation(models, {
      sourceLanguage,
      strategy,
      concurrency:
        config.value?.batchConcurrency ?? DEFAULT_BATCH_TRANSLATION_CONCURRENCY,
      write: (request) =>
        panel.api.post<BatchWriteResponse>(BATCH_WRITE_API_ROUTE, request, {
          silent: true,
        }),
      onProgress,
    });
  }

  function openBatchReport(
    outcomes: BatchOutcome[],
    labelOutcome: (outcome: BatchOutcome) => string,
    messageKey: string,
    onClose?: () => void,
  ) {
    panel.dialog.open({
      component: "k-error-dialog",
      props: {
        message: translatePlural(
          panel.t,
          messageKey,
          {
            saved: outcomes.filter(({ status }) => status === "saved").length,
            total: outcomes.length,
          },
          outcomes.length,
        ),
        details: describeBatchOutcomes(outcomes, { labelOutcome, t: panel.t }),
      },
      on: { close: onClose },
    });
  }

  /**
   * Translates the cascade of the host at `hostPath` into the target language
   * and saves it. The cascade is always translated from the default language,
   * even when the host's fields came from another language.
   */
  async function translateCascade(
    hostPath: string,
    targetLanguage: PanelLanguageInfo | PanelLanguage,
    strategy: TranslationStrategy,
  ) {
    if (!hasCascade.value || targetLanguage.default) return [];

    // For a host another user edits, Kirby's `content.save()` opens the lock
    // dialog instead of throwing, and reports the refused write only from
    // Kirby 5.6 on, so the status has to tell that the host was not written.
    const hostStatus = await fetchBatchStatus(hostPath);

    if (!hostStatus.isUpdateAllowed || hostStatus.lockedBy !== null) return [];

    return await translateAndSave(hostPath, undefined, [targetLanguage], {
      strategy,
      onProgress: notifyProgress,
    });
  }

  function fetchBatchStatus(path: string) {
    return panel.api.get<BatchStatusResponse>(
      BATCH_STATUS_API_ROUTE,
      { path },
      undefined,
      true,
    );
  }

  function fetchCascade(path: string) {
    return panel.api.get<CascadeModelResponse[]>(
      CASCADE_API_ROUTE,
      { path },
      undefined,
      true,
    );
  }

  /**
   * Describes the cascade of the model at `path` for the dialog that starts a
   * run, or returns nothing without a cascaded model.
   */
  async function getCascadeHelp(path: string) {
    if (!hasCascade.value) return;

    let cascade: CascadeModelResponse[];

    // The help is optional, so a failed request must not keep the dialog from
    // opening. The run requests the cascade again and reports the error.
    try {
      cascade = await fetchCascade(path);
    } catch (error) {
      console.error("Failed to load the cascade:", error);
      return;
    }

    if (cascade.length === 0) return;

    return translatePlural(
      panel.t,
      "johannschopplich.content-translator.dialog.cascadeHelp",
      { models: describeCascadeModels(cascade.map((model) => model.path)) },
      cascade.length,
    );
  }

  /**
   * Names the pages and the files among the paths in the Panel's language, as
   * in "3 pages and 2 files".
   */
  function describeCascadeModels(paths: string[]) {
    const fileCount = paths.filter(isFileModelPath).length;
    const pageCount = paths.length - fileCount;
    const countLabels = [
      ["pages", pageCount] as const,
      ["files", fileCount] as const,
    ]
      .filter(([, count]) => count > 0)
      .map(([key, count]) =>
        translatePlural(
          panel.t,
          `johannschopplich.content-translator.cascade.${key}`,
          { count },
          count,
        ),
      );

    return countLabels.length === 2
      ? panel.t("johannschopplich.content-translator.cascade.and", {
          first: countLabels[0],
          second: countLabels[1],
        })
      : (countLabels[0] ?? "");
  }

  /**
   * Loads the default-language content of every cascaded model afresh: a
   * cascaded model has no open view whose events could clear a cache.
   */
  async function getCascadeCandidates(
    hostPath: string,
    defaultLanguageCode: string,
  ): Promise<BatchCandidate[]> {
    const cascade = await fetchCascade(hostPath);

    return await pAll(
      cascade.map(({ path, title, fields, status }) => async () => {
        const defaultLanguageData = await panel.api.get<PanelModelData>(
          path,
          { language: defaultLanguageCode },
          undefined,
          true,
        );

        return {
          path,
          title,
          isHomePage: defaultLanguageData.id === homePageId.value,
          isErrorPage: defaultLanguageData.id === errorPageId.value,
          defaultLanguageData,
          fields,
          status,
        };
      }),
      {
        concurrency:
          config.value?.batchConcurrency ??
          DEFAULT_BATCH_TRANSLATION_CONCURRENCY,
      },
    );
  }

  async function isHomePage(path: string) {
    const defaultLanguageData = await getModelData(path);
    return defaultLanguageData.id === homePageId.value;
  }

  async function isErrorPage(path: string) {
    const defaultLanguageData = await getModelData(path);
    return defaultLanguageData.id === errorPageId.value;
  }

  /**
   * Tells whether the view of the model at `path` is open in the language with
   * `languageCode`. `currentContent` and `updateContent` read and write the
   * form of whichever view is open.
   */
  function isViewOpen(path: string, languageCode: string) {
    return panel.view.path === path && panel.language.code === languageCode;
  }

  return {
    label,
    isImportEnabled,
    importFrom,
    isBatchTranslationEnabled,
    shouldConfirm,
    strategyName,

    fields,
    licenseStatus,
    hasAnyStrategy,
    missingStrategyMessage,
    isContentEditable,

    initializeConfig,
    importModelContent,
    translateModelContent,
    batchTranslateModelContent,
    getCascadeHelp,
  };
}
