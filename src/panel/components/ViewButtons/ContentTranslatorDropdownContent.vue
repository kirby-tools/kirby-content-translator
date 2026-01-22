<script lang="ts">
import type { PanelLanguageInfo } from "kirby-types";
import type { PropType } from "vue";
import type { PluginContextResponse, TranslatorOptions } from "../../types";
import { LicensingDropdownItems } from "@kirby-tools/licensing/components";
import { usePanel } from "kirbyuse";
import { useTranslationDialogs } from "../../composables/dialogs";
import { useModel } from "../../composables/model";
import {
  useContentTranslator,
  useTranslationState,
} from "../../composables/translation";
import { MODEL_FIELDS_API_ROUTE } from "../../constants";

export default {
  inheritAttrs: false,
};
</script>

<script setup lang="ts">
const props = defineProps({
  context: {
    type: Object as PropType<PluginContextResponse>,
    required: true,
  },
  props: {
    type: Object as PropType<TranslatorOptions>,
    required: true,
  },
});

const panel = usePanel();
const { getModelData } = useModel();
const { isTranslating } = useTranslationState();

const defaultLanguage = panel.languages.find((language) => language.default)!;

const {
  // Configuration state
  allowImport,
  importFrom,
  allowBatchTranslation,
  shouldConfirm,
  provider,

  // Runtime state
  fields,
  licenseStatus,

  // Methods
  initializeConfig,
  syncModelContent,
  translateModelContent,
  batchTranslateModelContent,
} = useContentTranslator();

const {
  openConfirmableTextDialog,
  openTranslationDialog,
  openBatchTranslationDialog,
  showCopilotLicenseToastOnce,
} = useTranslationDialogs({
  defaultProvider: provider.value,
});

if (!props.context.config.translateFn && !props.context.config.DeepL?.apiKey) {
  panel.notification.error(
    'Either a custom "johannschopplich.content-translator.translateFn" or the "johannschopplich.content-translator.DeepL.apiKey" plugin option is required.',
  );
}

initializeConfig(props.context, props.props);

// Lazily fetch required view data (same as `computed` section methods)
const initializationPromise = (async () => {
  const defaultLanguageData = await getModelData();

  fields.value = await panel.api.get(
    MODEL_FIELDS_API_ROUTE,
    { id: defaultLanguageData.id ?? "site" },
    undefined,
    // Avoid showing Panel loading indicator
    true,
  );

  if (import.meta.env.DEV) {
    // eslint-disable-next-line no-console
    console.log("Model fields:", fields.value);
  }
})();

async function handleImport(sourceLanguage?: PanelLanguageInfo) {
  const text = panel.t("johannschopplich.content-translator.dialog.import", {
    language: sourceLanguage?.name ?? defaultLanguage.name,
  });

  await openConfirmableTextDialog(text, shouldConfirm.value, async () => {
    await initializationPromise;
    await syncModelContent(sourceLanguage);
  });
}

async function handleTranslate(sourceLanguage?: PanelLanguageInfo) {
  await initializationPromise;
  const result = await openTranslationDialog();
  if (result) {
    provider.value = result.provider;
    await translateModelContent(panel.language, sourceLanguage);
    if (result.provider === "ai") {
      showCopilotLicenseToastOnce();
    }
  }
}

async function handleBatchTranslate() {
  await initializationPromise;
  const result = await openBatchTranslationDialog();
  if (result) {
    provider.value = result.provider;
    await batchTranslateModelContent(result.languages);
    if (result.provider === "ai") {
      showCopilotLicenseToastOnce();
    }
  }
}
</script>

<template>
  <div>
    <template v-if="allowImport && importFrom === 'all'">
      <template v-if="allowImport">
        <k-dropdown-item
          v-for="language in panel.languages.filter(
            (language) => language.code !== panel.language.code,
          )"
          :key="language.code"
          :disabled="isTranslating"
          icon="import"
          @click="handleImport(language)"
        >
          {{
            panel.t("johannschopplich.content-translator.importFrom", {
              language: language.code.toUpperCase(),
            })
          }}
        </k-dropdown-item>
        <hr />
      </template>
      <k-dropdown-item
        :disabled="isTranslating"
        icon="translate"
        @click="handleTranslate()"
      >
        {{
          panel.t("johannschopplich.content-translator.translate", {
            language: panel.language.code?.toUpperCase(),
          })
        }}
      </k-dropdown-item>
      <k-dropdown-item
        v-if="allowBatchTranslation && panel.language.default"
        :disabled="isTranslating"
        icon="content-translator-global"
        @click="handleBatchTranslate()"
      >
        {{
          panel.t("johannschopplich.content-translator.batchTranslate", {
            language: defaultLanguage.code.toUpperCase(),
          })
        }}
      </k-dropdown-item>
    </template>

    <template v-else>
      <template
        v-if="
          allowImport && (!allowBatchTranslation || !panel.language.default)
        "
      >
        <k-dropdown-item
          :disabled="panel.language.default || isTranslating"
          icon="import"
          @click="handleImport()"
        >
          {{ panel.t("johannschopplich.content-translator.import") }}
        </k-dropdown-item>
        <hr />
      </template>
      <k-dropdown-item
        v-if="!allowBatchTranslation || !panel.language.default"
        :disabled="panel.language.default || isTranslating"
        icon="translate"
        @click="handleTranslate(defaultLanguage)"
      >
        {{
          panel.t("johannschopplich.content-translator.translate", {
            language: panel.language.code?.toUpperCase(),
          })
        }}
      </k-dropdown-item>
      <k-dropdown-item
        v-if="allowBatchTranslation && panel.language.default"
        :disabled="isTranslating"
        icon="content-translator-global"
        @click="handleBatchTranslate()"
      >
        {{
          panel.t("johannschopplich.content-translator.batchTranslate", {
            language: defaultLanguage.code.toUpperCase(),
          })
        }}
      </k-dropdown-item>
    </template>

    <template v-if="licenseStatus !== undefined && licenseStatus !== 'active'">
      <hr />
      <LicensingDropdownItems
        label="Kirby Content Translator"
        api-namespace="__content-translator__"
        :license-status="licenseStatus"
        pricing-url="https://kirby.tools/content-translator/buy"
      />
    </template>
  </div>
</template>
