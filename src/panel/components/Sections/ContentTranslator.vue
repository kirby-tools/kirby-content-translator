<script>
import { LicensingButtonGroup } from "@kirby-tools/licensing/components";
import { onBeforeUnmount, ref, usePanel, useSection } from "kirbyuse";
import { section } from "kirbyuse/props";
import {
  openConditionalTextDialog,
  openTextDialog,
} from "../../composables/dialog";
import { usePluginContext } from "../../composables/plugin";
import { useContentTranslator } from "../../composables/translation";

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
const isInitialized = ref(false);

const {
  // Configuration state
  label,
  allowImport,
  importFrom,
  allowBulkTranslation,
  confirm,

  // Runtime state
  config,
  licenseStatus,

  // Panel constants
  defaultLanguage,

  // Methods
  initializeConfig,
  syncModelContent,
  translateModelContent,
  bulkTranslateModelContent,
  updateModelDefaultLanguageData,
} = useContentTranslator();

(async () => {
  const { load } = useSection();
  const [context, sectionProps] = await Promise.all([
    usePluginContext(),
    load({
      parent: props.parent,
      name: props.name,
    }),
  ]);

  initializeConfig(context, sectionProps);

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
        You need to set either a custom <code>translateFn</code> or the
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
            variant="filled"
            @click="
              openConditionalTextDialog(
                confirm,
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
          variant="filled"
          theme="notice-icon"
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
        </k-button>
        <k-button
          v-if="allowBulkTranslation && panel.language.default"
          icon="content-translator-global"
          variant="filled"
          theme="notice-icon"
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
            variant="filled"
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
          </k-button>
          <k-button
            v-if="!allowBulkTranslation || !panel.language.default"
            :disabled="panel.language.default"
            icon="translate"
            variant="filled"
            theme="notice-icon"
            @click="
              openConditionalTextDialog(
                confirm,
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
            icon="content-translator-global"
            variant="filled"
            theme="notice-icon"
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
