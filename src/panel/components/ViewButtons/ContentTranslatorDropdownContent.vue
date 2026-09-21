<script lang="ts">
import type { PropType } from "vue";
import type { PluginContextResponse, TranslatorOptions } from "../../types";
import { LicensingDropdownItems } from "@kirby-tools/licensing/components";
import { usePanel } from "kirbyuse";
import { useTranslationActions } from "../../composables/actions";
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

const translator = useContentTranslator();
const {
  isImportEnabled,
  importFrom,
  isBatchTranslationEnabled,

  fields,
  licenseStatus,
  hasAnyStrategy,
  missingStrategyMessage,
  isContentEditable,

  initializeConfig,
} = translator;

initializeConfig(props.context, props.props).then(() => {
  if (missingStrategyMessage.value) {
    panel.notification.error(missingStrategyMessage.value);
  }
});

// Lazily fetch required view data (same as `computed` section methods).
const initializationPromise = (async () => {
  const defaultLanguageData = await getModelData();

  fields.value = await panel.api.get(
    MODEL_FIELDS_API_ROUTE,
    { id: defaultLanguageData.id ?? "site" },
    undefined,
    // Avoid showing Panel loading indicator.
    true,
  );

  if (import.meta.env.DEV) {
    // eslint-disable-next-line no-console
    console.log("Model fields:", fields.value);
  }
})();

const { handleImport, handleTranslate, handleBatchTranslate } =
  useTranslationActions(translator, initializationPromise);
</script>

<template>
  <div>
    <template v-if="isImportEnabled && importFrom === 'all'">
      <k-dropdown-item
        v-for="language in panel.languages.filter(
          (language) => language.code !== panel.language.code,
        )"
        :key="language.code"
        :disabled="isTranslating || !isContentEditable"
        icon="import"
        @click="handleImport(language)"
      >
        {{
          panel.t("johannschopplich.content-translator.importFrom", {
            language: language.code.toUpperCase(),
          })
        }}
      </k-dropdown-item>
      <hr v-if="hasAnyStrategy" />
      <k-dropdown-item
        v-if="hasAnyStrategy"
        :disabled="isTranslating || !isContentEditable"
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
        v-if="
          hasAnyStrategy && isBatchTranslationEnabled && panel.language.default
        "
        :disabled="isTranslating || !isContentEditable"
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
          isImportEnabled &&
          (!isBatchTranslationEnabled || !panel.language.default)
        "
      >
        <k-dropdown-item
          :disabled="
            panel.language.default || isTranslating || !isContentEditable
          "
          icon="import"
          @click="handleImport()"
        >
          {{ panel.t("johannschopplich.content-translator.import") }}
        </k-dropdown-item>
        <hr v-if="hasAnyStrategy" />
      </template>
      <k-dropdown-item
        v-if="
          hasAnyStrategy &&
          (!isBatchTranslationEnabled || !panel.language.default)
        "
        :disabled="
          panel.language.default || isTranslating || !isContentEditable
        "
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
        v-if="
          hasAnyStrategy && isBatchTranslationEnabled && panel.language.default
        "
        :disabled="isTranslating || !isContentEditable"
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
