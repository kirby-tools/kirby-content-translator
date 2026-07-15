import type { LicenseStatus } from "@kirby-tools/licensing";
import type {
  KirbyFieldProps,
  PanelLanguage,
  PanelLanguageInfo,
  PanelModelData,
} from "kirby-types";
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
import { resolveCopilot } from "../utils/copilot";
import { filterSyncableContent } from "../utils/filter";
import {
  resolveInitialProvider,
  resolveTranslatorConfig,
} from "../utils/translator-config";
import { useModel } from "./model";
import { createGlobalState } from "./state";

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

  // Configuration state
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

  // Runtime state
  const fields = ref<Record<string, KirbyFieldProps>>();
  const config = ref<PluginConfig>();
  const homePageId = ref<string>();
  const errorPageId = ref<string>();
  const licenseStatus = ref<LicenseStatus>();
  const hasAnyProvider = ref(false);

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

    const availability = getProviderAvailability(context.config);
    hasAnyProvider.value = availability.hasAnyProvider;
    provider.value = resolveInitialProvider(options.provider, availability);
  }

  // TODO: Next major version – unify import flow through a server-side
  // `copyContent` API endpoint. When importing from the default language,
  // delete the content file (Kirby inherits automatically) and reload the
  // Panel instead of using `updateContent()`. For non-default `importFrom`
  // sources, the server-side copy behavior is kept. This also removes the
  // need for client-side `filterSyncableContent` during import and the
  // title/slug patching for default-language imports.
  // TODO: Next major version – remove confirm dialog options entirely
  async function syncModelContent(
    language?: PanelLanguageInfo | PanelLanguage,
  ) {
    let title: string;
    let content: Record<string, unknown>;

    // If a language is passed, use the content of that language as the source,
    // otherwise use the default language
    if (language) {
      const data = await panel.api.get<PanelModelData>(
        panel.view.path,
        { language: language.code },
        undefined,
        // Avoid showing Panel loading indicator
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

    await updateContent(syncableContent);
    const plan = planImport({
      isHomePage: await isHomePage(),
      isErrorPage: await isErrorPage(),
      isFileModel: isFileModel(),
      isSiteModel: isSiteModel(),
      isTitleTranslationEnabled: translateTitle.value === true,
      isSlugTranslationEnabled: translateSlug.value === true,
      isCurrentLanguageDefault: panel.language.default,
    });

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
      timeout: false,
    });

    try {
      const contentCopy: Record<string, unknown> = JSON.parse(
        JSON.stringify(currentContent.value),
      );

      const strategy =
        provider.value === "ai"
          ? new AIStrategy({ systemPrompt: systemPrompt.value })
          : new DeepLStrategy();

      await translateContent(contentCopy, {
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

      if (plan.shouldRequestTitleTranslation) {
        // Non-null: the plan requests a title translation only when the view has one
        const translatedTitle = await translateText(panel.view.title!, {
          provider: provider.value,
          targetLanguage,
          sourceLanguage,
          systemPrompt: systemPrompt.value,
        });

        if (plan.shouldPatchTitle) {
          await panel.api.patch(`${panel.view.path}/title`, {
            title: translatedTitle,
          });
        }

        if (plan.shouldPatchSlug) {
          const slug = slugify(translatedTitle);
          await panel.api.patch(`${panel.view.path}/slug`, { slug });
        }

        isTranslating.value = false;
        // Reload will also end Panel loading state
        await panel.view.reload();
      } else {
        isTranslating.value = false;
        panel.view.isLoading = false;
      }

      panel.notification.success(
        panel.t("johannschopplich.content-translator.notification.translated"),
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
      timeout: false,
    });

    const defaultLanguageData = await getModelData();
    const strategy =
      provider.value === "ai"
        ? new AIStrategy({ systemPrompt: systemPrompt.value })
        : new DeepLStrategy();

    try {
      await batchTranslateLanguages(
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
            timeout: false,
          });
        },
      );

      panel.notification.success(
        panel.t(
          "johannschopplich.content-translator.notification.batchTranslated",
        ),
      );

      isTranslating.value = false;
      // Reload will also end Panel loading state
      await panel.view.reload();
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

    await pAll(
      selectedLanguages.map((targetLanguage) => async () => {
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

        await translateContent(contentCopy, {
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

        if (plan.shouldRequestTitleTranslation) {
          const translatedTitle = await translateText(
            defaultLanguageData.title,
            {
              provider: provider.value,
              targetLanguage,
              sourceLanguage: defaultLanguage,
              systemPrompt: systemPrompt.value,
            },
          );

          if (plan.shouldPatchTitle) {
            await panel.api.patch(
              `${modelApiPath}/title`,
              { title: translatedTitle },
              {
                headers: { "x-language": targetLanguage.code! },
                silent: true,
              },
            );
          }

          if (plan.shouldPatchSlug) {
            const slug = slugify(translatedTitle);
            await panel.api.patch(
              `${modelApiPath}/slug`,
              { slug },
              {
                headers: { "x-language": targetLanguage.code! },
                silent: true,
              },
            );
          }
        }

        completed++;
        onProgress?.(completed, selectedLanguages.length);
      }),
      { concurrency },
    );
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
    // Configuration state
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

    // Runtime state
    fields,
    licenseStatus,
    hasAnyProvider,

    // Methods
    initializeConfig,
    syncModelContent,
    translateModelContent,
    batchTranslateModelContent,
  };
}

export function getProviderAvailability(config: PluginConfig) {
  const isCopilotAvailable = !!resolveCopilot();
  const hasDefaultProvider = !!(config.translateFn || config.DeepL?.apiKey);

  return {
    isCopilotAvailable,
    hasDefaultProvider,
    hasMultipleProviders: isCopilotAvailable && hasDefaultProvider,
    hasAnyProvider: isCopilotAvailable || hasDefaultProvider,
  };
}


