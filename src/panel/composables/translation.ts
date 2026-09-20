import type { LicenseStatus } from "@kirby-tools/licensing";
import type {
  KirbyFieldProps,
  NotificationTheme,
  PanelLanguage,
  PanelLanguageInfo,
  PanelModelData,
} from "kirby-types";
import type { BatchModel, BatchOutcome } from "../translation/batch";
import type {
  ContentTranslationResult,
  TranslationRejection,
} from "../translation/types";
import type {
  BatchStatusResponse,
  BatchWriteResponse,
  PluginConfig,
  PluginContextResponse,
  StrategyName,
  TranslatorOptions,
} from "../types";
import { isKirby5, ref, useContent, useI18n, usePanel } from "kirbyuse";
import {
  BATCH_STATUS_API_ROUTE,
  BATCH_WRITE_API_ROUTE,
  DEFAULT_BATCH_TRANSLATION_CONCURRENCY,
} from "../constants";
import {
  AIStrategy,
  DeepLStrategy,
  translateContent,
  translateTitle,
} from "../translation";
import { runBatchTranslation } from "../translation/batch";
import { planImport, planSingleTranslation } from "../translation/plan";
import {
  mergeTranslationResults,
  reportRejections,
} from "../translation/result";
import { resolveCopilotReadiness } from "../utils/copilot";
import { filterEligibleContent, isEligibleField } from "../utils/filter";
import { formatPlural } from "../utils/i18n";
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

/** How many fields of one language a notice names before it counts the rest. */
const MAX_NAMED_FIELDS = 3;

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
  const kirbyTags = ref<Record<string, string[]>>({});
  const strategyName = ref<StrategyName>("deepl");
  const systemPrompt = ref<string>();
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
    kirbyTags.value = resolvedConfig.kirbyTags;
    systemPrompt.value = resolvedConfig.systemPrompt;

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

  async function hasResolvedBlueprint() {
    if (fields.value && Object.keys(fields.value).length > 0) return true;

    // Kirby falls back to the `default` blueprint when the template's own is
    // missing. A blueprint of the template's own without fields is a title-only
    // model, whatever keys an earlier template left in its content.
    const { template, blueprint } = await panel.api.get<{
      template?: string | null;
      blueprint: { name: string };
    }>(panel.view.path, { select: "template,blueprint" }, undefined, true);
    const isBlueprintMissing =
      Boolean(template) &&
      template !== "default" &&
      blueprint.name.endsWith("/default");
    if (!isBlueprintMissing) return true;

    console.error(
      `No blueprint fields could be resolved for "${panel.view.path}". Check that the model's blueprint exists and that its filename matches the template exactly, including case.`,
    );
    panel.notification.error(
      panel.t("johannschopplich.content-translator.error.unresolvedFields"),
    );

    return false;
  }

  /**
   * Separates a configuration that rules out every field from content with
   * nothing to translate.
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
    successMessageKey: string,
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
      panel.notification.success(panel.t(successMessageKey));
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
      formatPlural(
        panel.t(
          "johannschopplich.content-translator.notification.partiallyTranslated",
          {
            untranslated: untranslatedCount,
            total: result.translatableCount,
            fields: listKeptSourceFields(result.rejections),
          },
        ),
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
    languages: (PanelLanguageInfo | PanelLanguage)[],
    outcomes: BatchOutcome[],
  ) {
    const savedResults = outcomes.flatMap((outcome) =>
      outcome.status === "saved" ? [outcome.result] : [],
    );
    const languagesWithKeptSource = languages.flatMap((language, index) => {
      const outcome = outcomes[index]!;
      if (
        outcome.status !== "saved" ||
        outcome.result.translatedCount === outcome.result.translatableCount
      ) {
        return [];
      }

      return [
        `${language.name} (${listKeptSourceFields(outcome.result.rejections)})`,
      ];
    });

    if (languagesWithKeptSource.length === 0) {
      notifyTranslationResult(
        mergeTranslationResults(savedResults),
        "johannschopplich.content-translator.notification.batchTranslated",
      );
      return;
    }

    notifyPartialTranslation(
      panel.t(
        "johannschopplich.content-translator.notification.batchPartiallyTranslated",
        {
          languages: languagesWithKeptSource.join(", "),
        },
      ),
    );
  }

  function listKeptSourceFields(rejections: TranslationRejection[]) {
    const uniqueLabels = [
      ...new Set(rejections.map(({ fieldKey }) => fieldLabel(fieldKey))),
    ];
    const namedLabels = uniqueLabels.slice(0, MAX_NAMED_FIELDS).join(", ");
    if (uniqueLabels.length <= MAX_NAMED_FIELDS) return namedLabels;

    const remainingCount = uniqueLabels.length - MAX_NAMED_FIELDS;

    return formatPlural(
      panel.t("johannschopplich.content-translator.notification.andMore", {
        fields: namedLabels,
        count: remainingCount,
      }),
      remainingCount,
    );
  }

  function describeBatchOutcomes(
    languages: (PanelLanguageInfo | PanelLanguage)[],
    outcomes: BatchOutcome[],
  ) {
    return languages.flatMap((language, index) => {
      const lines = describeBatchOutcome(outcomes[index]!);
      return lines.length > 0 ? [{ label: language.name, message: lines }] : [];
    });
  }

  function describeBatchOutcome(outcome: BatchOutcome): string[] {
    const reportLine = (key: string, data?: Record<string, unknown>) =>
      panel.t(`johannschopplich.content-translator.batchReport.${key}`, data);

    if (outcome.status === "failed") {
      return [reportLine("failed", { message: outcome.message })];
    }

    if (outcome.status === "unsavedChanges") {
      return [reportLine("unsavedChanges")];
    }

    if (outcome.status === "locked") {
      return [reportLine("locked", { user: outcome.lockedBy })];
    }

    if (outcome.status === "notStarted") {
      return [reportLine("notStarted", { user: outcome.lockedBy })];
    }

    // A textarea or a structure yields several units per field, which would
    // otherwise name the same field once per unit.
    const lines = new Set<string>();

    for (const rejection of outcome.result.rejections) {
      lines.add(
        reportLine("keptSource", {
          field: fieldLabel(rejection.fieldKey),
          reason: describeRejection(rejection),
        }),
      );
    }

    if (outcome.titleError) {
      lines.add(
        reportLine("notChanged", {
          field: panel.t("title"),
          message: outcome.titleError,
        }),
      );
    }

    if (outcome.slugError) {
      lines.add(
        reportLine("notChanged", {
          field: panel.t("slug"),
          message: outcome.slugError,
        }),
      );
    }

    for (const [name, { label, message }] of Object.entries(
      outcome.invalidFields ?? {},
    )) {
      for (const validationMessage of Object.values(message)) {
        lines.add(
          reportLine("invalidField", {
            field: label || name,
            message: validationMessage,
          }),
        );
      }
    }

    return [...lines];
  }

  function describeRejection({ reason, detail }: TranslationRejection) {
    switch (reason) {
      case "missing translation":
      case "non-string translation":
        return panel.t(
          "johannschopplich.content-translator.rejection.missingTranslation",
        );
      case "empty translation":
        return panel.t(
          "johannschopplich.content-translator.rejection.emptyTranslation",
        );
      case "placeholder mismatch":
        return panel.t(
          "johannschopplich.content-translator.rejection.placeholderMismatch",
        );
      default:
        return detail ?? reason;
    }
  }

  /**
   * Names a unit's field by the label of its top-level field, which a nested
   * unit's key leads with.
   */
  function fieldLabel(fieldKey = "") {
    const name = fieldKey.split(/[.[]/)[0]!;
    const label = fields.value?.[name]?.label;
    if (label) return label;
    return name === "title" ? panel.t("title") : name;
  }

  // TODO: Next major version – unify import flow through a server-side
  // `copyContent` API endpoint. When importing from the default language,
  // delete the content file (Kirby inherits automatically) and reload the
  // Panel instead of using `updateContent()`. For non-default `importFrom`
  // sources, the server-side `copyContent` behavior is kept. This also removes
  // the need for client-side `filterEligibleContent` during import and the
  // title/slug patching for default-language imports.
  // TODO: Next major version – remove confirm dialog options entirely.
  async function importModelContent(
    language?: PanelLanguageInfo | PanelLanguage,
  ) {
    if (!(await hasResolvedBlueprint())) return;

    let title: string;
    let content: Record<string, unknown>;

    if (language) {
      const data = await panel.api.get<PanelModelData>(
        panel.view.path,
        { language: language.code },
        undefined,
        // Avoid showing Panel loading indicator.
        true,
      );
      title = data.title;
      content = data.content;
    } else {
      const data = await getModelData();
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
      isHomePage: await isHomePage(),
      isErrorPage: await isErrorPage(),
      isFileModel: isFileModelPath(panel.view.path),
      isSiteModel: isSiteModelPath(panel.view.path),
      isTitleTranslationEnabled: isTitleTranslationEnabled.value === true,
      isSlugTranslationEnabled: isSlugTranslationEnabled.value === true,
      isCurrentLanguageDefault: panel.language.default,
    });

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
      await panel.api.patch(`${panel.view.path}/title`, { title });
    }
    if (plan.shouldPatchSlug) {
      // Kirby sanitizes the slug with the slug rules of the current language.
      await panel.api.patch(`${panel.view.path}/slug`, { slug: title });
    }
    if (plan.shouldPatchTitle || plan.shouldPatchSlug) {
      await panel.view.reload();
    }

    panel.notification.success(
      panel.t("johannschopplich.content-translator.notification.imported"),
    );
  }

  async function translateModelContent(
    targetLanguage: PanelLanguageInfo | PanelLanguage,
    sourceLanguage?: PanelLanguageInfo | PanelLanguage,
  ) {
    if (panel.view.isLoading || isTranslating.value) return;
    if (!(await hasResolvedBlueprint())) return;
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

      await updateContent(contentCopy);
      const plan = planSingleTranslation({
        isHomePage: await isHomePage(),
        isErrorPage: await isErrorPage(),
        isFileModel: isFileModelPath(panel.view.path),
        isSiteModel: isSiteModelPath(panel.view.path),
        isTitleTranslationEnabled: isTitleTranslationEnabled.value === true,
        isSlugTranslationEnabled: isSlugTranslationEnabled.value === true,
        isTargetLanguageDefault: targetLanguage.default === true,
        hasViewTitle: Boolean(panel.view.title),
      });

      const languageResults = [contentResult];

      if (plan.shouldRequestTitleTranslation) {
        const translatedTitle = await translateTitle(
          // Non-null: the plan requests a title translation only when the view has one.
          panel.view.title!,
          { strategy, targetLanguage, sourceLanguage },
        );
        languageResults.push(translatedTitle.result);

        if (translatedTitle.text !== undefined && plan.shouldPatchTitle) {
          await panel.api.patch(`${panel.view.path}/title`, {
            title: translatedTitle.text,
          });
        }

        if (translatedTitle.text !== undefined && plan.shouldPatchSlug) {
          // Kirby sanitizes the slug with the slug rules of the current
          // language, which is the target language.
          await panel.api.patch(`${panel.view.path}/slug`, {
            slug: translatedTitle.text,
          });
        }

        isTranslating.value = false;
        // Reload will also end Panel loading state.
        await panel.view.reload();
      } else {
        isTranslating.value = false;
        panel.view.isLoading = false;
      }

      const mergedResult = mergeTranslationResults(languageResults);
      notifyTranslationResult(
        mergedResult,
        "johannschopplich.content-translator.notification.translated",
      );
    } catch (error) {
      isTranslating.value = false;
      panel.view.isLoading = false;
      console.error("Failed to translate content:", error);
      panel.notification.error((error as Error).message);
    }
  }

  async function batchTranslateModelContent(
    selectedLanguages: (PanelLanguageInfo | PanelLanguage)[],
  ) {
    if (panel.view.isLoading || isTranslating.value) return;
    if (!(await hasResolvedBlueprint())) return;
    panel.view.isLoading = true;
    isTranslating.value = true;

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

    try {
      const model: BatchModel = {
        path: panel.view.path,
        defaultLanguageData: await getModelData(),
        fields: fields.value!,
      };
      const batchStatus = await panel.api.get<BatchStatusResponse>(
        BATCH_STATUS_API_ROUTE,
        { path: model.path },
        undefined,
        true,
      );

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

      const languagesToTranslate = selectedLanguages.filter(
        ({ code }) => !batchStatus.languagesWithUnsavedChanges.includes(code),
      );

      if (languagesToTranslate.length > 0) {
        notifyProgress(0, languagesToTranslate.length);
      }

      const strategy =
        strategyName.value === "ai"
          ? new AIStrategy({ systemPrompt: systemPrompt.value })
          : new DeepLStrategy();

      const translatedOutcomes = await runBatchTranslation(
        model,
        languagesToTranslate,
        {
          sourceLanguage: panel.languages.find((language) => language.default)!,
          settings: {
            fieldTypes: fieldTypes.value,
            includeFields: includeFields.value,
            excludeFields: excludeFields.value,
            kirbyTags: kirbyTags.value,
            homePageId: homePageId.value,
            errorPageId: errorPageId.value,
            isTitleTranslationEnabled: isTitleTranslationEnabled.value === true,
            isSlugTranslationEnabled: isSlugTranslationEnabled.value === true,
            concurrency:
              config.value?.batchConcurrency ??
              DEFAULT_BATCH_TRANSLATION_CONCURRENCY,
          },
          strategy,
          write: (request) =>
            panel.api.post<BatchWriteResponse>(BATCH_WRITE_API_ROUTE, request, {
              // Avoid showing Panel loading indicator.
              silent: true,
            }),
          onProgress: notifyProgress,
        },
      );

      const outcomes = selectedLanguages.map((language): BatchOutcome => {
        const index = languagesToTranslate.indexOf(language);
        return index === -1
          ? { status: "unsavedChanges" }
          : translatedOutcomes[index]!;
      });

      const hasReport = outcomes.some(shouldReportBatchOutcome);

      if (!hasReport) {
        notifyBatchTranslationResult(selectedLanguages, outcomes);
      }

      isTranslating.value = false;
      // Reload will also end Panel loading state.
      await panel.view.reload();

      if (hasReport) {
        // Opened after the reload, so the saved languages are on screen before
        // the dialog covers them.
        panel.notification.close();
        panel.dialog.open({
          component: "k-error-dialog",
          props: {
            message: formatPlural(
              panel.t(
                "johannschopplich.content-translator.batchReport.message",
                {
                  saved: outcomes.filter(({ status }) => status === "saved")
                    .length,
                  total: selectedLanguages.length,
                },
              ),
              selectedLanguages.length,
            ),
            details: describeBatchOutcomes(selectedLanguages, outcomes),
          },
        });
      }
    } catch (error) {
      isTranslating.value = false;
      panel.view.isLoading = false;
      console.error("Failed to batch translate content:", error);
      panel.notification.error((error as Error).message);
    }
  }

  async function isHomePage() {
    const defaultLanguageData = await getModelData();
    return defaultLanguageData.id === homePageId.value;
  }

  async function isErrorPage() {
    const defaultLanguageData = await getModelData();
    return defaultLanguageData.id === errorPageId.value;
  }

  return {
    label,
    isImportEnabled,
    importFrom,
    isBatchTranslationEnabled,
    isTitleTranslationEnabled,
    isSlugTranslationEnabled,
    shouldConfirm,
    fieldTypes,
    includeFields,
    excludeFields,
    kirbyTags,
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
  };
}

/**
 * Keeps a language with nothing but kept source text in a notice, since it
 * was saved. A title whose translation failed is reported like failed
 * content.
 */
function shouldReportBatchOutcome(outcome: BatchOutcome) {
  return (
    outcome.status !== "saved" ||
    outcome.result.rejections.some(
      ({ reason }) => reason === "request failed",
    ) ||
    Boolean(outcome.titleError) ||
    Boolean(outcome.slugError) ||
    Object.keys(outcome.invalidFields ?? {}).length > 0
  );
}
