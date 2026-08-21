import type { LicenseStatus } from "@kirby-tools/licensing";
import type {
  KirbyFieldProps,
  NotificationTheme,
  PanelLanguage,
  PanelLanguageInfo,
  PanelModelData,
} from "kirby-types";
import type { ContentTranslationResult } from "../translation/types";
import type {
  PluginConfig,
  PluginContextResponse,
  TranslationProvider,
  TranslatorOptions,
} from "../types";
import slugify from "@sindresorhus/slugify";
import { ref, useContent, useI18n, usePanel } from "kirbyuse";
import pAll from "p-all";
import { DEFAULT_BATCH_TRANSLATION_CONCURRENCY } from "../constants";
import {
  AIStrategy,
  DeepLStrategy,
  translateContent,
  translateText,
} from "../translation";
import {
  planBatchLanguageTranslation,
  planImport,
  planSingleTranslation,
} from "../translation/plan";
import { filterSyncableContent } from "../utils/filter";
import {
  getProviderAvailability,
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
  const { currentContent, update: updateContent } = useContent();
  const { t } = useI18n();
  const { getModelData, isFileModel, isSiteModel } = useModel();
  const { isTranslating } = useTranslationState();

  // #region Configuration state
  const label = ref<string>();
  const allowImport = ref<boolean>();
  const importFrom = ref<string>();
  const allowBatchTranslation = ref<boolean>();
  const translateTitle = ref<boolean>();
  const translateSlug = ref<boolean>();
  const shouldConfirm = ref<boolean>();
  const fieldTypes = ref<string[]>([]);
  const includeFields = ref<string[]>([]);
  const excludeFields = ref<string[]>([]);
  const kirbyTags = ref<Record<string, string[]>>({});
  const provider = ref<TranslationProvider>("deepl");
  const systemPrompt = ref<string>();
  // #endregion

  // #region Runtime state
  const fields = ref<Record<string, KirbyFieldProps>>();
  const config = ref<PluginConfig>();
  const homePageId = ref<string>();
  const errorPageId = ref<string>();
  const licenseStatus = ref<LicenseStatus>();
  const hasAnyProvider = ref(false);
  // #endregion

  function initializeConfig(
    context: PluginContextResponse,
    options: TranslatorOptions = {},
  ) {
    label.value =
      t(options.label) || panel.t("johannschopplich.content-translator.label");

    const resolvedConfig = resolveTranslatorConfig(context.config, options);
    allowImport.value = resolvedConfig.allowImport;
    importFrom.value = resolvedConfig.importFrom;
    allowBatchTranslation.value = resolvedConfig.allowBatchTranslation;
    translateTitle.value = resolvedConfig.translateTitle;
    translateSlug.value = resolvedConfig.translateSlug;
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

    hasAnyProvider.value = getProviderAvailability(
      context.config,
    ).hasAnyProvider;
  }

  function reportRejections(
    result: ContentTranslationResult,
    targetLanguage: PanelLanguageInfo | PanelLanguage,
  ) {
    for (const { fieldKey, reason, detail } of result.rejections) {
      console.warn(
        `Rejected "${fieldKey}" (${targetLanguage.code}): ${detail ?? reason}. Keeping source text.`,
      );
    }
  }

  // Only one notification is visible at a time, so the most specific outcome wins.
  function notifyTranslationResult(
    result: ContentTranslationResult,
    successMessageKey: string,
  ) {
    if (result.translatableCount === 0) {
      panel.notification.open({
        message: panel.t(
          "johannschopplich.content-translator.notification.nothingToTranslate",
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

    panel.notification.open({
      message: panel.t(
        "johannschopplich.content-translator.notification.partiallyTranslated",
        { untranslated: untranslatedCount, total: result.translatableCount },
      ),
      icon: "alert",
      // The Panel styles `notice`, but `kirby-types` omits it from the union.
      theme: "notice" as NotificationTheme,
      timeout: PERSISTENT_TIMEOUT,
    });
  }

  async function translateAndPatchTitle({
    title,
    plan,
    targetLanguage,
    sourceLanguage,
    patch,
  }: {
    title: string;
    plan: { shouldPatchTitle: boolean; shouldPatchSlug: boolean };
    targetLanguage: PanelLanguageInfo | PanelLanguage;
    sourceLanguage?: PanelLanguageInfo | PanelLanguage;
    patch: (
      endpoint: "title" | "slug",
      data: Record<string, unknown>,
    ) => Promise<unknown>;
  }): Promise<ContentTranslationResult> {
    let translatedTitle: { text: string; result: ContentTranslationResult };

    try {
      translatedTitle = await translateText(title, {
        provider: provider.value,
        targetLanguage,
        sourceLanguage,
        systemPrompt: systemPrompt.value,
        fieldKey: "title",
      });
    } catch (error) {
      // Every failure of the lone title unit lands here, not just `AIStrategy`
      // throwing when nothing came back usable – a route error or a rejected
      // DeepL key does too. The content is already saved, so the run reports
      // the title as untranslated rather than erroring out.
      console.error("Failed to translate the title:", error);
      translatedTitle = {
        text: title,
        result: {
          translatableCount: 1,
          translatedCount: 0,
          rejections: [
            {
              fieldKey: "title",
              reason: "missing translation",
              detail: error instanceof Error ? error.message : String(error),
            },
          ],
        },
      };
    }

    if (plan.shouldPatchTitle) {
      await patch("title", { title: translatedTitle.text });
    }

    if (plan.shouldPatchSlug) {
      await patch("slug", { slug: slugify(translatedTitle.text) });
    }

    return translatedTitle.result;
  }

  // TODO: Next major version – unify import flow through a server-side
  // `copyContent` API endpoint. When importing from the default language,
  // delete the content file (Kirby inherits automatically) and reload the
  // Panel instead of using `updateContent()`. For non-default `importFrom`
  // sources, the server-side copy behavior is kept. This also removes the
  // need for client-side `filterSyncableContent` during import and the
  // title/slug patching for default-language imports.
  // TODO: Next major version – remove confirm dialog options entirely.
  async function syncModelContent(
    language?: PanelLanguageInfo | PanelLanguage,
  ) {
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

    const syncableContent = filterSyncableContent(content, {
      fields: fields.value!,
      fieldTypes: fieldTypes.value,
      includeFields: includeFields.value,
      excludeFields: excludeFields.value,
    });

    const plan = planImport({
      isHomePage: await isHomePage(),
      isErrorPage: await isErrorPage(),
      isFileModel: isFileModel(),
      isSiteModel: isSiteModel(),
      isTitleTranslationEnabled: translateTitle.value === true,
      isSlugTranslationEnabled: translateSlug.value === true,
      isCurrentLanguageDefault: panel.language.default,
    });

    const hasSyncableContent = Object.keys(syncableContent).length > 0;

    if (
      !hasSyncableContent &&
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

    await updateContent(syncableContent);

    if (plan.shouldPatchTitle) {
      await panel.api.patch(`${panel.view.path}/title`, { title });
    }
    if (plan.shouldPatchSlug) {
      const slug = slugify(title);
      await panel.api.patch(`${panel.view.path}/slug`, { slug });
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
        provider.value === "ai"
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

      await updateContent(contentCopy);
      const plan = planSingleTranslation({
        isHomePage: await isHomePage(),
        isErrorPage: await isErrorPage(),
        isFileModel: isFileModel(),
        isSiteModel: isSiteModel(),
        isTitleTranslationEnabled: translateTitle.value === true,
        isSlugTranslationEnabled: translateSlug.value === true,
        isTargetLanguageDefault: targetLanguage.default === true,
        hasViewTitle: Boolean(panel.view.title),
      });

      const languageResults = [contentResult];

      if (plan.shouldRequestTitleTranslation) {
        languageResults.push(
          await translateAndPatchTitle({
            // Non-null: the plan requests a title translation only when the view has one.
            title: panel.view.title!,
            plan,
            targetLanguage,
            sourceLanguage,
            patch: (endpoint, data) =>
              panel.api.patch(`${panel.view.path}/${endpoint}`, data),
          }),
        );

        isTranslating.value = false;
        // Reload will also end Panel loading state.
        await panel.view.reload();
      } else {
        isTranslating.value = false;
        panel.view.isLoading = false;
      }

      const mergedResult = mergeTranslationResults(languageResults);
      reportRejections(mergedResult, targetLanguage);
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
    panel.view.isLoading = true;
    isTranslating.value = true;

    const total = selectedLanguages.length;

    panel.notification.open({
      message: panel.t(
        "johannschopplich.content-translator.notification.batchTranslating",
        { current: 0, total },
      ),
      icon: "loader",
      theme: "info",
      timeout: PERSISTENT_TIMEOUT,
    });

    const defaultLanguageData = await getModelData();
    const strategy =
      provider.value === "ai"
        ? new AIStrategy({ systemPrompt: systemPrompt.value })
        : new DeepLStrategy();

    try {
      const batchResults = await batchTranslateLanguages(
        selectedLanguages,
        defaultLanguageData,
        strategy,
        (current, total) => {
          panel.notification.open({
            message: panel.t(
              "johannschopplich.content-translator.notification.batchTranslating",
              { current, total },
            ),
            icon: "loader",
            theme: "info",
            timeout: PERSISTENT_TIMEOUT,
          });
        },
      );

      const failedLanguages = selectedLanguages.filter(
        (_, index) => batchResults[index] === null,
      );

      if (failedLanguages.length === 0) {
        notifyTranslationResult(
          mergeTranslationResults(
            batchResults.filter((result) => result !== null),
          ),
          "johannschopplich.content-translator.notification.batchTranslated",
        );
      }

      isTranslating.value = false;
      // Reload will also end Panel loading state.
      await panel.view.reload();

      if (failedLanguages.length > 0) {
        // Reported after the reload, so the languages that did land are on
        // screen before the dialog covers them. Folding a dead language into
        // the segment counts would report it as a handful of skipped segments.
        panel.notification.error(
          panel.t(
            "johannschopplich.content-translator.notification.batchLanguagesFailed",
            {
              languages: failedLanguages
                .map(({ code }) => `"${code}"`)
                .join(", "),
            },
          ),
        );
      }
    } catch (error) {
      isTranslating.value = false;
      panel.view.isLoading = false;
      console.error("Failed to batch translate content:", error);
      panel.notification.error((error as Error).message);
    }
  }

  async function batchTranslateLanguages(
    selectedLanguages: (PanelLanguageInfo | PanelLanguage)[],
    defaultLanguageData: PanelModelData,
    strategy: AIStrategy | DeepLStrategy,
    onProgress?: (completed: number, total: number) => void,
  ) {
    const defaultLanguage = panel.languages.find((lang) => lang.default)!;
    const modelApiPath = panel.view.path;
    const concurrency =
      config.value?.batchConcurrency ?? DEFAULT_BATCH_TRANSLATION_CONCURRENCY;

    let completed = 0;

    // A language is isolated so one dead provider call cannot discard the
    // languages already patched or skip the ones still queued.
    return await pAll(
      selectedLanguages.map((targetLanguage) => async () => {
        try {
          return await translateIntoLanguage(targetLanguage);
        } catch (error) {
          console.error(
            `Failed to translate into "${targetLanguage.code}":`,
            error,
          );
          return null;
        }
      }),
      { concurrency },
    );

    async function translateIntoLanguage(
      targetLanguage: PanelLanguageInfo | PanelLanguage,
    ) {
      const syncableContent = filterSyncableContent(
        defaultLanguageData.content,
        {
          fields: fields.value!,
          fieldTypes: fieldTypes.value,
          includeFields: includeFields.value,
          excludeFields: excludeFields.value,
        },
      );

      const contentCopy = JSON.parse(JSON.stringify(syncableContent));

      const contentResult = await translateContent(contentCopy, {
        strategy,
        sourceLanguage: defaultLanguage,
        targetLanguage,
        fieldTypes: fieldTypes.value,
        includeFields: includeFields.value,
        excludeFields: excludeFields.value,
        kirbyTags: kirbyTags.value,
        fields: fields.value!,
      });

      await panel.api.patch(modelApiPath, contentCopy, {
        headers: { "x-language": targetLanguage.code! },
        silent: true,
      });

      const plan = planBatchLanguageTranslation({
        isHomePage: defaultLanguageData.id === homePageId.value,
        isErrorPage: defaultLanguageData.id === errorPageId.value,
        isFileModel: isFileModel(),
        isSiteModel: isSiteModel(),
        isTitleTranslationEnabled: translateTitle.value === true,
        isSlugTranslationEnabled: translateSlug.value === true,
        isTargetLanguageDefault: targetLanguage.default === true,
      });

      const languageResults = [contentResult];

      if (plan.shouldRequestTitleTranslation) {
        languageResults.push(
          await translateAndPatchTitle({
            title: defaultLanguageData.title,
            plan,
            targetLanguage,
            sourceLanguage: defaultLanguage,
            patch: (endpoint, data) =>
              panel.api.patch(`${modelApiPath}/${endpoint}`, data, {
                headers: { "x-language": targetLanguage.code! },
                silent: true,
              }),
          }),
        );
      }

      completed++;
      onProgress?.(completed, selectedLanguages.length);

      const mergedResult = mergeTranslationResults(languageResults);
      reportRejections(mergedResult, targetLanguage);
      return mergedResult;
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
    allowImport,
    importFrom,
    allowBatchTranslation,
    translateTitle,
    translateSlug,
    shouldConfirm,
    fieldTypes,
    includeFields,
    excludeFields,
    kirbyTags,
    provider,

    fields,
    licenseStatus,
    hasAnyProvider,

    initializeConfig,
    syncModelContent,
    translateModelContent,
    batchTranslateModelContent,
  };
}

function mergeTranslationResults(
  results: ContentTranslationResult[],
): ContentTranslationResult {
  return {
    translatableCount: results.reduce(
      (total, result) => total + result.translatableCount,
      0,
    ),
    translatedCount: results.reduce(
      (total, result) => total + result.translatedCount,
      0,
    ),
    rejections: results.flatMap((result) => result.rejections),
  };
}
