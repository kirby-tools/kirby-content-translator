<script lang="ts">
import type { PropType } from "vue";
import type { PluginContextResponse, TranslatorOptions } from "../../types";
import { LicensingDropdownItems } from "@kirby-tools/licensing/components";
import { usePanel } from "kirbyuse";
import { useTranslationDialogs } from "../../composables/dialogs";
import { useModel } from "../../composables/model";
import { useContentTranslator } from "../../composables/translation";
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
const defaultLanguage = panel.languages.find((language) => language.default)!;
const { getModelData } = useModel();

const {
  // Configuration state
  allowImport,
  importFrom,
  allowBatchTranslation,
  shouldConfirm,

  // Runtime state
  fields,
  licenseStatus,

  // Methods
  initializeConfig,
  syncModelContent,
  translateModelContent,
  batchTranslateModelContent,
} = useContentTranslator();

const { openConfirmableTextDialog, openBatchTranslationDialog } =
  useTranslationDialogs({ batchTranslateModelContent });

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
    // Silent
    true,
  );

  if (import.meta.env.DEV) {
    // eslint-disable-next-line no-console
    console.log("Model fields:", fields.value);
  }
})();

function withInitialization(fn: (...args: unknown[]) => void) {
  return async (...args: unknown[]) => {
    await initializationPromise;
    fn(...args);
  };
}

async function invokeWhenInitialized(fn?: () => void) {
  await initializationPromise;
  fn?.();
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
          icon="import"
          @click="
            openConfirmableTextDialog(
              panel.t('johannschopplich.content-translator.dialog.importFrom', {
                language: language.name,
              }),
              shouldConfirm,
              withInitialization(() => syncModelContent(language)),
            )
          "
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
        icon="translate"
        @click="
          openConfirmableTextDialog(
            panel.t('johannschopplich.content-translator.dialog.translate', {
              language: panel.language.name,
            }),
            shouldConfirm,
            withInitialization(() => translateModelContent(panel.language)),
          )
        "
      >
        {{
          panel.t("johannschopplich.content-translator.translate", {
            language: panel.language.code?.toUpperCase(),
          })
        }}
      </k-dropdown-item>
      <k-dropdown-item
        v-if="allowBatchTranslation && panel.language.default"
        icon="content-translator-global"
        @click="invokeWhenInitialized(openBatchTranslationDialog)"
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
          :disabled="panel.language.default"
          icon="import"
          @click="
            openConfirmableTextDialog(
              panel.t('johannschopplich.content-translator.dialog.import', {
                language: defaultLanguage.name,
              }),
              shouldConfirm,
              withInitialization(() => syncModelContent()),
            )
          "
        >
          {{ panel.t("johannschopplich.content-translator.import") }}
        </k-dropdown-item>
        <hr />
      </template>
      <k-dropdown-item
        v-if="!allowBatchTranslation || !panel.language.default"
        :disabled="panel.language.default"
        icon="translate"
        @click="
          openConfirmableTextDialog(
            panel.t('johannschopplich.content-translator.dialog.translate', {
              language: panel.language.name,
            }),
            shouldConfirm,
            withInitialization(() =>
              translateModelContent(panel.language, defaultLanguage),
            ),
          )
        "
      >
        {{
          panel.t("johannschopplich.content-translator.translate", {
            language: panel.language.code?.toUpperCase(),
          })
        }}
      </k-dropdown-item>
      <k-dropdown-item
        v-if="allowBatchTranslation && panel.language.default"
        icon="content-translator-global"
        @click="invokeWhenInitialized(openBatchTranslationDialog)"
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
