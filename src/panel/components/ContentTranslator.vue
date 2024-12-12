<script>
import { LicensingButtonGroup } from "@kirby-tools/licensing/components";
import slugify from "@sindresorhus/slugify";
import {
  onBeforeUnmount,
  ref,
  useContent,
  usePanel,
  useSection,
} from "kirbyuse";
import { section } from "kirbyuse/props";
import { usePluginContext } from "../composables/plugin";
import { useTranslation } from "../composables/translation";
import {
  TRANSLATION_API_ROUTE,
  TRANSLATION_CONTENT_API_ROUTE,
} from "../constants";

const propsDefinition = {
  ...section,
};

export default {
  inheritAttrs: false,
};
</script>

<script setup>
const props = defineProps(propsDefinition);

const panel = usePanel();
const { currentContent, update: updateContent } = useContent();
const { translateContent } = useTranslation();

// Section props
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

// Section computed
const modelMeta = ref();
const fields = ref();
const config = ref();

// Generic data
const isInitialized = ref(false);
const defaultLanguageData = ref({});
const licenseStatus = ref();

// Static data
const defaultLanguage = panel.languages.find((language) => language.default);
const nonDefaultLanguages = panel.languages.filter(
  (language) => language.code !== defaultLanguage.code,
);

(async () => {
  const { load } = useSection();
  const [context, response] = await Promise.all([
    usePluginContext(),
    load({
      parent: props.parent,
      name: props.name,
    }),
  ]);

  label.value =
    t(response.label) || panel.t("johannschopplich.content-translator.label");
  allowImport.value = response.import ?? context.config.import ?? true;
  importFrom.value = response.importFrom ?? context.config.importFrom;
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
  modelMeta.value = response.modelMeta ?? {};
  fields.value = response.fields ?? {};
  config.value = context.config ?? {};
  licenseStatus.value = context.licenseStatus;

  // Re-fetch default content whenever the page gets saved
  panel.events.on("model.update", updateModelDefaultLanguageData);
  panel.events.on("page.changeTitle", updateModelDefaultLanguageData);
  updateModelDefaultLanguageData();

  isInitialized.value = true;
})();

onBeforeUnmount(() => {
  panel.events.off("model.update", updateModelDefaultLanguageData);
  panel.events.off("page.changeTitle", updateModelDefaultLanguageData);
});

function t(value) {
  if (!value || typeof value === "string") return value;
  return value[panel.translation.code] ?? Object.values(value)[0];
}

async function syncModelContent(language) {
  let { title, content } = defaultLanguageData.value;

  // If a language is passed, use the content of that language as the source,
  // otherwise use the default language
  if (language) {
    const data = await getModelData(language);
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
  if (translateSlug.value) {
    const slug = slugify(title);
    await panel.api.patch(`${panel.view.path}/slug`, { slug });
  }
  if (translateTitle.value || translateSlug.value) {
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
    (translateSlug.value && !targetLanguage.default)
  ) {
    const { text } = await panel.api.post(TRANSLATION_API_ROUTE, {
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
    if (translateSlug.value && !targetLanguage.default) {
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
        await panel.api.post(TRANSLATION_CONTENT_API_ROUTE, {
          selectedLanguage: language.code,
          context: modelMeta.value.context,
          id: modelMeta.value.id,
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
  defaultLanguageData.value = await getModelData(defaultLanguage);
}

function getModelData(language) {
  return panel.api.get(panel.view.path, {
    language: language.code,
  });
}

function openMaybeConfirmableModal(text, callback) {
  if (!confirm.value) {
    callback?.();
    return;
  }

  openModal(text, callback);
}

function openModal(text, callback) {
  panel.dialog.open({
    component: "k-text-dialog",
    props: { text },
    on: {
      submit: () => {
        panel.dialog.close();
        callback?.();
      },
    },
  });
}
</script>

<template>
  <k-section v-if="isInitialized" :label="label">
    <template v-if="licenseStatus !== undefined" slot="options">
      <LicensingButtonGroup
        label="Kirby Content Translator"
        api-namespace="__content-translator__"
        :license-status="licenseStatus"
        pricing-url="https://kirby.tools/content-translator#pricing"
      />
    </template>

    <k-box v-if="!panel.multilang" theme="empty">
      <k-text>
        This section requires multi-language support to be enabled.
      </k-text>
    </k-box>
    <k-box
      v-else-if="!config.translateFn && !config.DeepL?.apiKey"
      theme="empty"
    >
      <k-text>
        You need to set the either a custom <code>translateFn</code> or the
        <code>DeepL.apiKey</code> option for the
        <code>johannschopplich.content-translator</code> namespace in your Kirby
        configuration.
      </k-text>
    </k-box>
    <k-box v-else-if="allowImport && importFrom === 'all'" theme="none">
      <k-button-group layout="collapsed">
        <template v-if="allowImport">
          <k-button
            v-for="language in panel.languages.filter(
              (language) => language.code !== panel.language.code,
            )"
            :key="language.code"
            icon="import"
            size="sm"
            variant="filled"
            @click="
              openMaybeConfirmableModal(
                panel.t(
                  'johannschopplich.content-translator.dialog.importFrom',
                  { language: language.name },
                ),
                () => syncModelContent(language),
              )
            "
          >
            {{
              panel.t("johannschopplich.content-translator.importFrom", {
                language: language.code.toUpperCase(),
              })
            }}
          </k-button>
        </template>
        <k-button
          icon="translate"
          size="sm"
          variant="filled"
          theme="notice"
          @click="
            openMaybeConfirmableModal(
              panel.t('johannschopplich.content-translator.dialog.translate', {
                language: panel.language.name,
              }),
              () => translateModelContent(panel.language),
            )
          "
        >
          {{
            panel.t("johannschopplich.content-translator.translate", {
              language: panel.language.code.toUpperCase(),
            })
          }}
        </k-button>
        <k-button
          v-if="allowBulkTranslation && panel.language.default"
          icon="content-translator-flag"
          size="sm"
          variant="filled"
          theme="notice"
          @click="
            openModal(
              panel.t(
                'johannschopplich.content-translator.dialog.bulkTranslation',
                { language: defaultLanguage.name },
              ),
              bulkTranslateModelContent,
            )
          "
        >
          {{
            panel.t("johannschopplich.content-translator.bulkTranslate", {
              language: defaultLanguage.code.toUpperCase(),
            })
          }}
        </k-button>
      </k-button-group>
    </k-box>
    <template v-else>
      <k-box theme="none">
        <k-button-group layout="collapsed">
          <k-button
            v-if="
              allowImport && (!allowBulkTranslation || !panel.language.default)
            "
            :disabled="panel.language.default"
            icon="import"
            size="sm"
            variant="filled"
            @click="
              openMaybeConfirmableModal(
                panel.t('johannschopplich.content-translator.dialog.import', {
                  language: defaultLanguage.name,
                }),
                () => syncModelContent(),
              )
            "
          >
            {{ panel.t("johannschopplich.content-translator.import") }}
          </k-button>
          <k-button
            v-if="!allowBulkTranslation || !panel.language.default"
            :disabled="panel.language.default"
            icon="translate"
            size="sm"
            variant="filled"
            theme="notice"
            @click="
              openMaybeConfirmableModal(
                panel.t(
                  'johannschopplich.content-translator.dialog.translate',
                  { language: panel.language.name },
                ),
                () => translateModelContent(panel.language, defaultLanguage),
              )
            "
          >
            {{
              panel.t("johannschopplich.content-translator.translate", {
                language: panel.language.code.toUpperCase(),
              })
            }}
          </k-button>
          <k-button
            v-if="allowBulkTranslation && panel.language.default"
            icon="content-translator-flag"
            size="sm"
            variant="filled"
            theme="notice"
            @click="
              openModal(
                panel.t(
                  'johannschopplich.content-translator.dialog.bulkTranslation',
                  { language: defaultLanguage.name },
                ),
                bulkTranslateModelContent,
              )
            "
          >
            {{
              panel.t("johannschopplich.content-translator.bulkTranslate", {
                language: defaultLanguage.code.toUpperCase(),
              })
            }}
          </k-button>
        </k-button-group>
      </k-box>

      <k-box
        v-show="!allowBulkTranslation && panel.language.default"
        class="kct-mt-1"
        theme="none"
        :text="
          panel.t(
            'johannschopplich.content-translator.help.disabledDefaultLanguage',
          )
        "
      />
    </template>
  </k-section>
</template>
