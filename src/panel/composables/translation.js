import slugify from "@sindresorhus/slugify";
import { computed, ref, useContent, usePanel } from "kirbyuse";
import { TRANSLATE_API_ROUTE, TRANSLATE_CONTENT_API_ROUTE } from "../constants";
import { translateContent } from "../utils/translation";
import { useI18n } from "./i18n";

export function useContentTranslator() {
  const panel = usePanel();
  const { currentContent, update: updateContent } = useContent();
  const { t } = useI18n();

  // Configuration state
  const label = ref();
  const allowImport = ref();
  const importFrom = ref();
  const allowBulkTranslation = ref();
  const translateTitle = ref();
  const translateSlug = ref();
  const confirm = ref();
  const fieldTypes = ref([]);
  const includeFields = ref([]);
  const excludeFields = ref([]);

  // Runtime state
  const fields = ref();
  const config = ref();
  const homePageId = ref();
  const licenseStatus = ref();
  const defaultLanguageData = ref({});

  // Panel constants
  const defaultLanguage = panel.languages.find((language) => language.default);
  const nonDefaultLanguages = panel.languages.filter(
    (language) => language.code !== defaultLanguage.code,
  );

  // Computed properties
  const isHomePage = computed(
    () => defaultLanguageData.value.id === homePageId.value,
  );

  function initializeConfig(context, response = {}) {
    label.value =
      t(response.label) || panel.t("johannschopplich.content-translator.label");
    allowImport.value = response.import ?? context.config.import ?? true;
    importFrom.value =
      response.importFrom ?? context.config.importFrom ?? undefined;
    allowBulkTranslation.value = response.bulk ?? context.config.bulk ?? true;
    translateTitle.value = response.title ?? context.config.title ?? false;
    translateSlug.value = response.slug ?? context.config.slug ?? false;
    confirm.value = response.confirm ?? context.config.confirm ?? true;
    fieldTypes.value = response.fieldTypes ??
      context.config.fieldTypes ?? [
        "blocks",
        "layout",
        "list",
        "object",
        "structure",
        "tags",
        "text",
        "textarea",
        "writer",
        "markdown",
      ];
    includeFields.value =
      response.includeFields ?? context.config.includeFields ?? [];
    excludeFields.value =
      response.excludeFields ?? context.config.excludeFields ?? [];
    fields.value = response.fields ?? {};
    config.value = context.config;
    homePageId.value = context.homePageId;
    licenseStatus.value =
      // eslint-disable-next-line no-undef
      __PLAYGROUND__ ? "active" : context.licenseStatus;
  }

  async function syncModelContent(language) {
    let { title, content } = defaultLanguageData.value;

    // If a language is passed, use the content of that language as the source,
    // otherwise use the default language
    if (language) {
      const data = await panel.api.get(panel.view.path, {
        language: language.code,
      });
      title = data.title;
      content = data.content;
    }

    const syncableContent = Object.fromEntries(
      Object.entries(content).filter(
        ([key]) =>
          fieldTypes.value.includes(fields.value[key]?.type) &&
          (includeFields.value.length
            ? includeFields.value.includes(key)
            : true) &&
          (excludeFields.value.length
            ? !excludeFields.value.includes(key)
            : true),
      ),
    );

    await updateContent(syncableContent);

    if (translateTitle.value) {
      await panel.api.patch(`${panel.view.path}/title`, { title });
    }
    if (translateSlug.value && !language.default && !isHomePage.value) {
      const slug = slugify(title);
      await panel.api.patch(`${panel.view.path}/slug`, { slug });
    }
    if (
      translateTitle.value ||
      (translateSlug.value && !language.default && !isHomePage.value)
    ) {
      await panel.view.reload();
    }

    panel.notification.success(
      panel.t("johannschopplich.content-translator.notification.imported"),
    );
  }

  async function translateModelContent(targetLanguage, sourceLanguage) {
    if (panel.view.isLoading) return;
    panel.view.isLoading = true;

    const clone = JSON.parse(JSON.stringify(currentContent.value));
    try {
      await translateContent(clone, {
        sourceLanguage: sourceLanguage?.code,
        targetLanguage: targetLanguage.code,
        fieldTypes: fieldTypes.value,
        includeFields: includeFields.value,
        excludeFields: excludeFields.value,
        fields: fields.value,
      });
    } catch (error) {
      console.error("Failed to translate content:", error);
      panel.notification.error(error.message);
      panel.view.isLoading = false;
      return;
    }

    await updateContent(clone);

    if (
      translateTitle.value ||
      (translateSlug.value && !targetLanguage.default && !isHomePage.value)
    ) {
      const { text } = await panel.api.post(TRANSLATE_API_ROUTE, {
        sourceLanguage: sourceLanguage?.code,
        targetLanguage: targetLanguage.code,
        text: panel.view.title,
      });

      if (translateTitle.value) {
        await panel.api.patch(`${panel.view.path}/title`, { title: text });
      }

      // Translating the slug is only possible for non-default languages,
      // as the page folder would be renamed otherwise.
      // See: https://github.com/kirby-tools/kirby-content-translator/issues/5
      if (translateSlug.value && !targetLanguage.default && !isHomePage.value) {
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

  async function bulkTranslateModelContent() {
    if (panel.view.isLoading) return;
    panel.view.isLoading = true;

    try {
      await Promise.all(
        nonDefaultLanguages.map(async (language) => {
          await panel.api.post(TRANSLATE_CONTENT_API_ROUTE, {
            selectedLanguage: language.code,
            id: defaultLanguageData.value.id ?? "site",
            title: translateTitle.value,
            slug: translateSlug.value,
          });
        }),
      );

      panel.notification.success(
        panel.t(
          "johannschopplich.content-translator.notification.bulkTranslated",
        ),
      );
    } catch (error) {
      console.error("Failed to bulk translate content:", error);
      panel.notification.error(error.message);
      panel.view.isLoading = false;
      return;
    }

    // Reload will also end loading state
    await panel.view.reload();
  }

  async function updateModelDefaultLanguageData() {
    defaultLanguageData.value = await panel.api.get(panel.view.path, {
      language: defaultLanguage.code,
    });
  }

  return {
    // Configuration state
    label,
    allowImport,
    importFrom,
    allowBulkTranslation,
    translateTitle,
    translateSlug,
    confirm,
    fieldTypes,
    includeFields,
    excludeFields,

    // Runtime state
    fields,
    config,
    licenseStatus,
    defaultLanguageData,

    // Panel constants
    defaultLanguage,
    nonDefaultLanguages,

    // Methods
    initializeConfig,
    syncModelContent,
    translateModelContent,
    bulkTranslateModelContent,
    updateModelDefaultLanguageData,
  };
}
