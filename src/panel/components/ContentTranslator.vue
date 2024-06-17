<script>
import slugify from "@sindresorhus/slugify";
import {
  computed,
  onBeforeUnmount,
  ref,
  usePanel,
  useSection,
  useStore,
} from "kirbyuse";
import { section } from "kirbyuse/props";
import { useLicense } from "@kirby-tools/licensing";
import { TRANSLATION_API_ROUTE } from "../constants";
import { useTranslation } from "../composables/translation";

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
const store = useStore();
const { translateContent } = useTranslation();
const { isLocalhost, openLicenseModal } = useLicense({
  label: "Kirby Content Translator",
  apiNamespace: "__content-translator__",
});

// Section props
const label = ref();
const allowImport = ref(true);
const importFrom = ref();
const translateTitle = ref(false);
const translateSlug = ref(false);
const confirm = ref(true);
const fieldTypes = ref([]);
const includeFields = ref([]);
const excludeFields = ref([]);

// Section computed
const fields = ref();
const config = ref();
const license = ref();

// Generic data
const defaultLanguageData = ref({});

// Static data
const defaultLanguage = panel.languages.find((language) => language.default);

const currentContent = computed(() => store.getters["content/values"]());

(async () => {
  const { load } = useSection();
  const response = await load({
    parent: props.parent,
    name: props.name,
  });
  label.value =
    t(response.label) || panel.t("johannschopplich.content-translator.label");
  allowImport.value = response.import ?? response.config.import;
  importFrom.value = response.importFrom ?? response.config.importFrom;
  translateTitle.value = response.title ?? response.config.title;
  translateSlug.value = response.slug ?? response.config.slug;
  confirm.value = response.confirm ?? response.config.confirm;
  fieldTypes.value = response.fieldTypes ??
    response.config.fieldTypes ?? [
      "blocks",
      "layout",
      "list",
      "object",
      "structure",
      "text",
      "textarea",
      "writer",
    ];
  includeFields.value =
    response.includeFields ?? response.config.includeFields ?? [];
  excludeFields.value =
    response.excludeFields ?? response.config.excludeFields ?? [];
  fields.value = response.fields ?? {};
  config.value = response.config ?? {};
  license.value = response.license;

  // Re-fetch default content whenever the page gets saved
  panel.events.on("model.update", updateModelDefaultLanguageData);
  panel.events.on("page.changeTitle", updateModelDefaultLanguageData);
  updateModelDefaultLanguageData();
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

  for (const [key, value] of Object.entries(syncableContent)) {
    store.dispatch("content/update", [key, value]);
  }

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
    console.error(error);
    panel.notification.error(panel.t("error"));
    return;
  }

  // Update content
  for (const [key, value] of Object.entries(clone)) {
    store.dispatch("content/update", [key, value]);
  }

  if (translateTitle.value || translateSlug.value) {
    const { text } = await panel.api.post(TRANSLATION_API_ROUTE, {
      sourceLanguage: sourceLanguage?.code,
      targetLanguage: targetLanguage.code,
      text: panel.view.title,
    });
    if (translateTitle.value) {
      await panel.api.patch(`${panel.view.path}/title`, { title: text });
    }
    if (translateSlug.value) {
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

async function updateModelDefaultLanguageData() {
  defaultLanguageData.value = await getModelData(defaultLanguage);
}

function getModelData(language) {
  return panel.api.get(panel.view.path, {
    language: language.code,
  });
}

function openModal(text, callback) {
  if (!confirm.value) {
    callback?.();
    return;
  }

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
  <k-section v-if="config" :label="label">
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
              openModal(
                panel.t(
                  'johannschopplich.content-translator.dialog.importFrom',
                  {
                    language: language.name,
                  },
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
            openModal(
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
      </k-button-group>
    </k-box>
    <template v-else>
      <k-box theme="none">
        <k-button-group layout="collapsed">
          <k-button
            v-if="allowImport"
            :disabled="panel.language.default"
            icon="import"
            size="sm"
            variant="filled"
            @click="
              openModal(
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
            :disabled="panel.language.default"
            icon="translate"
            size="sm"
            variant="filled"
            theme="notice"
            @click="
              openModal(
                panel.t(
                  'johannschopplich.content-translator.dialog.translate',
                  {
                    language: panel.language.name,
                  },
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
        </k-button-group>
      </k-box>

      <k-box
        v-show="panel.language.default"
        class="kct-mt-1"
        theme="none"
        :text="
          panel.t(
            'johannschopplich.content-translator.help.disallowDefaultLanguage',
          )
        "
      />
    </template>

    <k-box
      v-show="license === false"
      class="kct-mt-3"
      :class="[!isLocalhost && 'kct-w-max']"
      :theme="isLocalhost ? 'empty' : 'love'"
      :icon="!isLocalhost ? 'key' : undefined"
      :style="{
        '--link-color': 'var(--color-text)',
      }"
    >
      <k-text>
        <p>
          <span
            v-show="isLocalhost"
            v-html="
              panel.t('johannschopplich.content-translator.license.local')
            "
          />
          {{ " " }}
          <span
            v-html="panel.t('johannschopplich.content-translator.license.buy')"
          />
          &
          <span
            class="k-link kct-cursor-pointer"
            @click="openLicenseModal"
            v-html="
              panel.t('johannschopplich.content-translator.license.activate')
            "
          />
        </p>
      </k-text>
    </k-box>
  </k-section>
</template>
