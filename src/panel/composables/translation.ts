import type {
  KirbyFieldProps,
  PanelLanguageInfo,
  PanelModelData,
} from "kirby-types";
import type {
  KirbyTagConfig,
  PluginConfig,
  PluginContextResponse,
} from "../types";
import slugify from "@sindresorhus/slugify";
import { ref, useContent, useDialog, useI18n, usePanel } from "kirbyuse";
import pMap from "p-map";
import {
  DEFAULT_BATCH_TRANSLATION_CONCURRENCY,
  DEFAULT_FIELD_TYPES,
  TRANSLATE_API_ROUTE,
  TRANSLATE_CONTENT_API_ROUTE,
} from "../constants";
import { filterSyncableContent } from "../utils/sync";
import { translateContent } from "../utils/translation";
import { useModel } from "./model";

export interface TranslatorOptions {
  label?: string;
  import?: boolean;
  importFrom?: string;
  batch?: boolean;
  title?: boolean;
  slug?: boolean;
  confirm?: boolean;
  fieldTypes?: string[];
  includeFields?: string[];
  excludeFields?: string[];
  kirbyTags?: Record<string, KirbyTagConfig>;
  fields?: Record<string, KirbyFieldProps>;
}

export function useContentTranslator() {
  const panel = usePanel();
  const { currentContent, update: updateContent } = useContent();
  const { t } = useI18n();
  const { openFieldsDialog } = useDialog();
  const { getModelData } = useModel();

  // Configuration state
  const label = ref<string>();
  const allowImport = ref<boolean>();
  const importFrom = ref<string>();
  const allowBatchTranslation = ref<boolean>();
  const translateTitle = ref<boolean>();
  const translateSlug = ref<boolean>();
  const confirm = ref<boolean>();
  const fieldTypes = ref<string[]>([]);
  const includeFields = ref<string[]>([]);
  const excludeFields = ref<string[]>([]);
  const kirbyTags = ref<Record<string, KirbyTagConfig>>({});

  // Runtime state
  const fields = ref<Record<string, KirbyFieldProps>>();
  const config = ref<PluginConfig>();
  const homePageId = ref<string>();
  const errorPageId = ref<string>();
  const licenseStatus = ref<string>();

  // Panel constants
  const defaultLanguage = panel.languages.find((language) => language.default)!;
  const translationLanguages = panel.languages.filter(
    (language) => language.code !== defaultLanguage.code,
  );

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
    confirm.value = toBool(options.confirm ?? context.config.confirm, true);
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
  }

  async function syncModelContent(language?: PanelLanguageInfo) {
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
    targetLanguage: PanelLanguageInfo,
    sourceLanguage?: PanelLanguageInfo,
  ) {
    if (panel.view.isLoading) return;
    panel.view.isLoading = true;

    const clone: Record<string, unknown> = JSON.parse(
      JSON.stringify(currentContent.value),
    );

    try {
      await translateContent(clone, {
        sourceLanguage: sourceLanguage?.code,
        targetLanguage: targetLanguage.code,
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

    await updateContent(clone);
    const _isHomePage = await isHomePage();
    const _isErrorPage = await isErrorPage();
    const shouldTranslateSlug =
      translateSlug.value &&
      !targetLanguage.default &&
      !_isHomePage &&
      !_isErrorPage;

    if (translateTitle.value || shouldTranslateSlug) {
      const { text } = await panel.api.post<{ text: string }>(
        TRANSLATE_API_ROUTE,
        {
          sourceLanguage: sourceLanguage?.code,
          targetLanguage: targetLanguage.code,
          text: panel.view.title,
        },
      );

      if (translateTitle.value) {
        await panel.api.patch(`${panel.view.path}/title`, { title: text });
      }

      // Translating the slug is only possible for non-default languages,
      // as the page folder would be renamed otherwise.
      // See: https://github.com/kirby-tools/kirby-content-translator/issues/5
      if (shouldTranslateSlug) {
        const slug = slugify(text);
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
    selectedLanguages: PanelLanguageInfo[],
  ) {
    if (panel.view.isLoading) return;
    panel.view.isLoading = true;

    const defaultLanguageData = await getModelData();

    try {
      await pMap(
        selectedLanguages,
        (language) =>
          panel.api.post(TRANSLATE_CONTENT_API_ROUTE, {
            selectedLanguage: language.code,
            id: defaultLanguageData.id ?? "site",
            title: translateTitle.value,
            slug: translateSlug.value,
            fieldTypes: fieldTypes.value,
            includeFields: includeFields.value,
            excludeFields: excludeFields.value,
            kirbyTags: kirbyTags.value,
          }),
        {
          concurrency:
            config.value?.batchConcurrency ||
            DEFAULT_BATCH_TRANSLATION_CONCURRENCY,
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

  async function isHomePage() {
    const defaultLanguageData = await getModelData();
    return defaultLanguageData.id === homePageId.value;
  }

  async function isErrorPage() {
    const defaultLanguageData = await getModelData();
    return defaultLanguageData.id === errorPageId.value;
  }

  async function openBatchTranslationDialog() {
    const options = await openFieldsDialog({
      submitButton: {
        icon: "translate",
        theme: "positive",
        text: panel.t(
          "johannschopplich.content-translator.dialog.button.translate",
        ),
      },
      fields: {
        text: {
          type: "info",
          theme: "notice",
          text: panel.t(
            "johannschopplich.content-translator.dialog.batchTranslation",
            { language: defaultLanguage.name },
          ),
        },
        languages: {
          type: "checkboxes",
          label: `${panel.t("johannschopplich.content-translator.translateTo")}:`,
          options: translationLanguages.map((language) => ({
            value: language.code,
            text: language.name,
          })),
        },
      },
      value: {
        languages: translationLanguages.map((language) => language.code),
      },
    });

    if (options?.languages?.length) {
      await batchTranslateModelContent(
        translationLanguages.filter((language) =>
          options.languages.includes(language.code),
        ),
      );
    }
  }

  return {
    // Configuration state
    label,
    allowImport,
    importFrom,
    allowBatchTranslation,
    translateTitle,
    translateSlug,
    confirm,
    fieldTypes,
    includeFields,
    excludeFields,
    kirbyTags,

    // Runtime state
    fields,
    config,
    licenseStatus,

    // Panel constants
    defaultLanguage,
    translationLanguages,

    // Methods
    initializeConfig,
    syncModelContent,
    translateModelContent,

    // Dialogs
    openBatchTranslationDialog,
  };
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
