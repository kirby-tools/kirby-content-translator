import type { LicenseStatus } from "@kirby-tools/licensing";
import type {
  KirbyFieldProps,
  PanelLanguage,
  PanelLanguageInfo,
  PanelModelData,
} from "kirby-types";
import type {
  KirbyTagConfig,
  PluginConfig,
  PluginContextResponse,
  TranslationProvider,
  TranslatorOptions,
} from "../types";
import slugify from "@sindresorhus/slugify";
import { ref, useContent, useI18n, usePanel } from "kirbyuse";
import pAll from "p-all";
import {
  DEFAULT_BATCH_TRANSLATION_CONCURRENCY,
  DEFAULT_FIELD_TYPES,
  TRANSLATION_PROVIDERS,
} from "../constants";
import { AIStrategy, DeepLStrategy, translateContent } from "../translation";
import { resolveCopilot } from "../utils/copilot";
import { filterSyncableContent } from "../utils/filter";
import { useModel } from "./model";

export function useContentTranslator() {
  const panel = usePanel();
  const { currentContent, update: updateContent } = useContent();
  const { t } = useI18n();
  const { getModelData } = useModel();

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
  const kirbyTags = ref<Record<string, KirbyTagConfig>>({});
  const provider = ref<TranslationProvider>("deepl");

  // Runtime state
  const fields = ref<Record<string, KirbyFieldProps>>();
  const config = ref<PluginConfig>();
  const homePageId = ref<string>();
  const errorPageId = ref<string>();
  const licenseStatus = ref<LicenseStatus>();

  function initializeConfig(
    context: PluginContextResponse,
    options: TranslatorOptions = {},
  ) {
    label.value =
      t(options.label) || panel.t("johannschopplich.content-translator.label");
    allowImport.value = toBool(options.import ?? context.config.import, true);
    importFrom.value =
      options.importFrom ?? context.config.importFrom ?? undefined;
    allowBatchTranslation.value = toBool(
      options.batch ?? context.config.batch,
      true,
    );
    translateTitle.value = toBool(options.title ?? context.config.title, false);
    translateSlug.value = toBool(options.slug ?? context.config.slug, false);
    shouldConfirm.value = toBool(
      options.confirm ?? context.config.confirm,
      false,
    );
    fieldTypes.value = options.fieldTypes ??
      context.config.fieldTypes ?? [...DEFAULT_FIELD_TYPES];
    includeFields.value =
      options.includeFields ?? context.config.includeFields ?? [];
    excludeFields.value =
      options.excludeFields ?? context.config.excludeFields ?? [];
    kirbyTags.value = options.kirbyTags ?? context.config.kirbyTags ?? {};
    fields.value = options.fields ?? {};
    config.value = context.config;
    homePageId.value = context.homePageId;
    errorPageId.value = context.errorPageId;
    licenseStatus.value = __PLAYGROUND__ ? "active" : context.licenseStatus;

    // Determine available providers and set initial provider
    const requestedProvider = isValidProvider(options.provider)
      ? options.provider
      : undefined;
    const { hasDefaultProvider, hasMultipleProviders } =
      getProviderAvailability(context.config);

    if (requestedProvider === "ai" && hasMultipleProviders) {
      provider.value = "ai";
    } else if (requestedProvider === "deepl" && hasDefaultProvider) {
      provider.value = "deepl";
    } else if (!hasDefaultProvider) {
      // Only AI available
      provider.value = "ai";
    } else {
      // Default to DeepL (translateFn or DeepL API)
      provider.value = "deepl";
    }
  }

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
        // Silent
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
    const _isHomePage = await isHomePage();
    const _isErrorPage = await isErrorPage();
    const shouldTranslateSlug =
      translateSlug.value &&
      !language?.default &&
      !_isHomePage &&
      !_isErrorPage;

    if (translateTitle.value) {
      await panel.api.patch(`${panel.view.path}/title`, { title });
    }
    if (shouldTranslateSlug) {
      const slug = slugify(title);
      await panel.api.patch(`${panel.view.path}/slug`, { slug });
    }
    if (translateTitle.value || shouldTranslateSlug) {
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
    if (panel.view.isLoading) return;
    panel.view.isLoading = true;

    panel.notification.open({
      message: panel.t(
        "johannschopplich.content-translator.notification.translating",
      ),
      icon: "loader",
      theme: "info",
      timeout: false,
    });

    const contentCopy: Record<string, unknown> = JSON.parse(
      JSON.stringify(currentContent.value),
    );

    const strategy =
      provider.value === "ai" ? new AIStrategy() : new DeepLStrategy();

    try {
      await translateContent(contentCopy, {
        strategy,
        sourceLanguage: sourceLanguage?.code ?? undefined,
        targetLanguage: targetLanguage.code!,
        fieldTypes: fieldTypes.value,
        includeFields: includeFields.value,
        excludeFields: excludeFields.value,
        kirbyTags: kirbyTags.value,
        fields: fields.value!,
      });
    } catch (error) {
      panel.view.isLoading = false;
      console.error("Failed to translate content:", error);
      panel.notification.error((error as Error).message);
      return;
    }

    await updateContent(contentCopy);
    const _isHomePage = await isHomePage();
    const _isErrorPage = await isErrorPage();
    const shouldTranslateSlug =
      translateSlug.value &&
      !targetLanguage.default &&
      !_isHomePage &&
      !_isErrorPage;

    if ((translateTitle.value || shouldTranslateSlug) && panel.view.title) {
      const translatedTitle = await translateText(panel.view.title, {
        provider: provider.value,
        targetLanguage: targetLanguage.code!,
        sourceLanguage: sourceLanguage?.code ?? undefined,
      });

      if (translateTitle.value) {
        await panel.api.patch(`${panel.view.path}/title`, {
          title: translatedTitle,
        });
      }

      // Translating the slug is only possible for non-default languages,
      // as the page folder would be renamed otherwise.
      // See: https://github.com/kirby-tools/kirby-content-translator/issues/5
      if (shouldTranslateSlug) {
        const slug = slugify(translatedTitle);
        await panel.api.patch(`${panel.view.path}/slug`, { slug });
      }

      // Reload will also end loading state
      await panel.view.reload();
    } else {
      panel.view.isLoading = false;
    }

    panel.notification.success(
      panel.t("johannschopplich.content-translator.notification.translated"),
    );
  }

  async function batchTranslateModelContent(
    selectedLanguages: (PanelLanguageInfo | PanelLanguage)[],
  ) {
    if (panel.view.isLoading) return;
    panel.view.isLoading = true;

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
      provider.value === "ai" ? new AIStrategy() : new DeepLStrategy();

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
    } catch (error) {
      panel.view.isLoading = false;
      console.error("Failed to batch translate content:", error);
      panel.notification.error((error as Error).message);
      return;
    }

    // Reload will also end loading state
    await panel.view.reload();
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
          sourceLanguage: defaultLanguage.code,
          targetLanguage: targetLanguage.code!,
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

        const _isHomePage = defaultLanguageData.id === homePageId.value;
        const _isErrorPage = defaultLanguageData.id === errorPageId.value;

        if (translateTitle.value) {
          const translatedTitle = await translateText(
            defaultLanguageData.title,
            {
              provider: provider.value,
              targetLanguage: targetLanguage.code!,
              sourceLanguage: defaultLanguage.code,
            },
          );
          await panel.api.patch(
            `${modelApiPath}/title`,
            { title: translatedTitle },
            {
              headers: { "x-language": targetLanguage.code! },
              silent: true,
            },
          );

          // Translate slug for non-home/error pages
          if (
            translateSlug.value &&
            !targetLanguage.default &&
            !_isHomePage &&
            !_isErrorPage
          ) {
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
    config,
    licenseStatus,

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
    hasDefaultProvider,
    hasMultipleProviders: isCopilotAvailable && hasDefaultProvider,
  };
}

async function translateText(
  text: string,
  {
    provider,
    targetLanguage,
    sourceLanguage,
  }: {
    provider: TranslationProvider;
    targetLanguage: string;
    sourceLanguage?: string;
  },
): Promise<string> {
  const strategy = provider === "ai" ? new AIStrategy() : new DeepLStrategy();
  const results = await strategy.execute([{ text, mode: "batch" }], {
    sourceLanguage,
    targetLanguage,
  });
  return results[0] ?? text;
}

function isValidProvider(value: unknown): value is TranslationProvider {
  return TRANSLATION_PROVIDERS.includes(value as TranslationProvider);
}

/**
 * Normalize boolean values from YAML/PHP to JavaScript booleans
 */
function toBool(value: unknown, defaultValue = false) {
  if (value == null) return defaultValue;
  if (typeof value === "boolean") return value;
  if (typeof value === "string") return value === "true" || value === "1";
  if (typeof value === "number") return value === 1;

  return Boolean(value);
}
