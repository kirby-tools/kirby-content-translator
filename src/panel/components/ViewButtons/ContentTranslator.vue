<script>
import { LicensingDropdownItems } from "@kirby-tools/licensing/components";
import { usePanel } from "kirbyuse";
import {
  openConditionalTextDialog,
  openTextDialog,
} from "../../composables/dialog";
import { useContentTranslator } from "../../composables/translation";

export default {
  inheritAttrs: false,
};
</script>

<script setup>
const props = defineProps({
  context: {
    type: Object,
    required: true,
  },
});

const panel = usePanel();

const {
  // Section props
  allowImport,
  importFrom,
  allowBulkTranslation,
  confirm,

  // Local data
  licenseStatus,

  // Static data
  defaultLanguage,

  // Methods
  initializeConfig,
  syncModelContent,
  translateModelContent,
  bulkTranslateModelContent,
  updateModelDefaultLanguageData,
} = useContentTranslator();

if (!props.context.config.translateFn && !props.context.config.DeepL?.apiKey) {
  panel.notification.error(
    `You need to set the either a custom "translateFn" or the "DeepL.apiKey" option for the "johannschopplich.content-translator" namespace in your Kirby configuration.`,
  );
}

initializeConfig(props.context);
updateModelDefaultLanguageData();
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
            openConditionalTextDialog(
              confirm,
              panel.t('johannschopplich.content-translator.dialog.importFrom', {
                language: language.name,
              }),
              () => syncModelContent(language),
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
          openConditionalTextDialog(
            confirm,
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
      </k-dropdown-item>
      <k-dropdown-item
        v-if="allowBulkTranslation && panel.language.default"
        icon="content-translator-global"
        @click="
          openTextDialog(
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
      </k-dropdown-item>
    </template>

    <template v-else>
      <template
        v-if="allowImport && (!allowBulkTranslation || !panel.language.default)"
      >
        <k-dropdown-item
          :disabled="panel.language.default"
          icon="import"
          @click="
            openConditionalTextDialog(
              confirm,
              panel.t('johannschopplich.content-translator.dialog.import', {
                language: defaultLanguage.name,
              }),
              () => syncModelContent(),
            )
          "
        >
          {{ panel.t("johannschopplich.content-translator.import") }}
        </k-dropdown-item>
        <hr />
      </template>
      <k-dropdown-item
        v-if="!allowBulkTranslation || !panel.language.default"
        :disabled="panel.language.default"
        icon="translate"
        @click="
          openConditionalTextDialog(
            confirm,
            panel.t('johannschopplich.content-translator.dialog.translate', {
              language: panel.language.name,
            }),
            () => translateModelContent(panel.language, defaultLanguage),
          )
        "
      >
        {{
          panel.t("johannschopplich.content-translator.translate", {
            language: panel.language.code.toUpperCase(),
          })
        }}
      </k-dropdown-item>
      <k-dropdown-item
        v-if="allowBulkTranslation && panel.language.default"
        icon="content-translator-global"
        @click="
          openTextDialog(
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
      </k-dropdown-item>
    </template>

    <template v-if="licenseStatus !== 'active'">
      <hr />
      <LicensingDropdownItems
        label="Kirby Content Translator"
        api-namespace="__content-translator__"
        :license-status="licenseStatus"
        pricing-url="https://kirby.tools/content-translator#pricing"
      />
    </template>
  </div>
</template>
