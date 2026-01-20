<script lang="ts">
import type { PanelLanguageInfo } from "kirby-types";
import { LicensingButtonGroup } from "@kirby-tools/licensing/components";
import { ref, usePanel, useSection } from "kirbyuse";
import { section } from "kirbyuse/props";
import { useTranslationDialogs } from "../../composables/dialogs";
import { usePluginContext } from "../../composables/plugin";
import { useContentTranslator } from "../../composables/translation";

const propsDefinition = {
  ...section,
};

export default {
  inheritAttrs: false,
};
</script>

<script setup lang="ts">
const props = defineProps(propsDefinition);

const panel = usePanel();
const defaultLanguage = panel.languages.find((language) => language.default)!;
const isInitialized = ref(false);

const {
  // Configuration state
  label,
  allowImport,
  importFrom,
  allowBatchTranslation,
  shouldConfirm,
  provider,

  // Runtime state
  config,
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
} = useTranslationDialogs({
  defaultProvider: provider.value,
});

(async () => {
  const { load } = useSection();
  const [context, sectionProps] = await Promise.all([
    usePluginContext(),
    load({
      parent: props.parent!,
      name: props.name!,
    }),
  ]);

  initializeConfig(context, sectionProps);

  isInitialized.value = true;
})();

async function handleImport(sourceLanguage?: PanelLanguageInfo) {
  const text = sourceLanguage
    ? panel.t("johannschopplich.content-translator.dialog.importFrom", {
        language: sourceLanguage.name,
      })
    : panel.t("johannschopplich.content-translator.dialog.import", {
        language: defaultLanguage.name,
      });

  await openConfirmableTextDialog(text, shouldConfirm.value, async () => {
    await syncModelContent(sourceLanguage);
  });
}

async function handleTranslate(sourceLanguage?: PanelLanguageInfo) {
  const result = await openTranslationDialog();
  if (result) {
    provider.value = result.provider;
    await translateModelContent(panel.language, sourceLanguage);
  }
}

async function handleBatchTranslate() {
  const result = await openBatchTranslationDialog();
  if (result) {
    provider.value = result.provider;
    await batchTranslateModelContent(result.languages);
  }
}
</script>

<template>
  <k-section v-if="isInitialized" :label="label">
    <template v-if="licenseStatus !== undefined" slot="options">
      <LicensingButtonGroup
        label="Kirby Content Translator"
        api-namespace="__content-translator__"
        :license-status="licenseStatus"
        pricing-url="https://kirby.tools/content-translator/buy"
      />
    </template>

    <k-box v-if="!panel.multilang" theme="empty">
      <k-text>
        This section requires multi-language support to be enabled.
      </k-text>
    </k-box>
    <k-box
      v-else-if="!config?.translateFn && !config?.DeepL?.apiKey"
      theme="empty"
    >
      <k-text>
        Either a custom <code>translateFn</code> or <code>DeepL.apiKey</code> in
        the <code>johannschopplich.content-translator</code> plugin
        configuration is required.
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
            @click="handleImport(language)"
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
          @click="handleTranslate()"
        >
          {{
            panel.t("johannschopplich.content-translator.translate", {
              language: panel.language.code?.toUpperCase(),
            })
          }}
        </k-button>
        <k-button
          v-if="allowBatchTranslation && panel.language.default"
          icon="content-translator-global"
          variant="filled"
          theme="notice-icon"
          @click="handleBatchTranslate()"
        >
          {{
            panel.t("johannschopplich.content-translator.batchTranslate", {
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
              allowImport && (!allowBatchTranslation || !panel.language.default)
            "
            :disabled="panel.language.default"
            icon="import"
            variant="filled"
            @click="handleImport()"
          >
            {{ panel.t("johannschopplich.content-translator.import") }}
          </k-button>
          <k-button
            v-if="!allowBatchTranslation || !panel.language.default"
            :disabled="panel.language.default"
            icon="translate"
            variant="filled"
            theme="notice-icon"
            @click="handleTranslate(defaultLanguage)"
          >
            {{
              panel.t("johannschopplich.content-translator.translate", {
                language: panel.language.code?.toUpperCase(),
              })
            }}
          </k-button>
          <k-button
            v-if="allowBatchTranslation && panel.language.default"
            icon="content-translator-global"
            variant="filled"
            theme="notice-icon"
            @click="handleBatchTranslate()"
          >
            {{
              panel.t("johannschopplich.content-translator.batchTranslate", {
                language: defaultLanguage.code.toUpperCase(),
              })
            }}
          </k-button>
        </k-button-group>
      </k-box>

      <k-box
        v-show="!allowBatchTranslation && panel.language.default"
        theme="none"
        :text="
          panel.t(
            'johannschopplich.content-translator.help.disabledDefaultLanguage',
          )
        "
        :style="{
          marginTop: 'var(--spacing-1)',
        }"
      />
    </template>
  </k-section>
</template>
