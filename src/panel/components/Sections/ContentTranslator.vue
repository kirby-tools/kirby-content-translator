<script lang="ts">
import type { PanelLanguageInfo } from "kirby-types";
import { LicensingButtonGroup } from "@kirby-tools/licensing/components";
import { ref, usePanel, useSection } from "kirbyuse";
import { section } from "kirbyuse/props";
import { useTranslationDialogs } from "../../composables/dialogs";
import { usePluginContext } from "../../composables/plugin";
import {
  useContentTranslator,
  useTranslationState,
} from "../../composables/translation";

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
const { isTranslating } = useTranslationState();

const defaultLanguage = panel.languages.find((language) => language.default)!;
const isInitialized = ref(false);

const {
  label,
  isImportEnabled,
  importFrom,
  isBatchTranslationEnabled,
  shouldConfirm,
  strategyName,

  licenseStatus,
  hasAnyStrategy,
  missingStrategyMessage,
  isContentEditable,

  initializeConfig,
  importModelContent,
  translateModelContent,
  batchTranslateModelContent,
} = useContentTranslator();

const {
  openConfirmableTextDialog,
  openTranslationDialog,
  openBatchTranslationDialog,
  showCopilotLicenseToastOnce,
} = useTranslationDialogs();

(async () => {
  const { load } = useSection();

  const [context, sectionProps] = await Promise.all([
    usePluginContext(),
    load({
      parent: props.parent!,
      name: props.name!,
    }),
  ]);

  await initializeConfig(context, sectionProps);

  isInitialized.value = true;
})();

async function handleImport(sourceLanguage?: PanelLanguageInfo) {
  const text = panel.t(
    "johannschopplich.content-translator.dialog.importConfirmation",
    {
      language: sourceLanguage?.name ?? defaultLanguage.name,
    },
  );

  await openConfirmableTextDialog(text, shouldConfirm.value, async () => {
    await importModelContent(sourceLanguage);
  });
}

async function handleTranslate(sourceLanguage?: PanelLanguageInfo) {
  const result = await openTranslationDialog();
  if (result) {
    strategyName.value = result.strategyName;
    await translateModelContent(panel.language, sourceLanguage);
    if (result.strategyName === "ai") {
      showCopilotLicenseToastOnce();
    }
  }
}

async function handleBatchTranslate() {
  const result = await openBatchTranslationDialog();
  if (result) {
    strategyName.value = result.strategyName;
    await batchTranslateModelContent(result.languages);
    if (result.strategyName === "ai") {
      showCopilotLicenseToastOnce();
    }
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
    <template v-else>
      <k-box v-if="!hasAnyStrategy" theme="empty">
        <k-text>{{ missingStrategyMessage }}</k-text>
      </k-box>

      <k-box
        v-if="isImportEnabled && importFrom === 'all'"
        theme="none"
        :class="!hasAnyStrategy && 'kct-mt-[var(--spacing-1)]'"
      >
        <k-button-group layout="collapsed">
          <k-button
            v-for="language in panel.languages.filter(
              (language) => language.code !== panel.language.code,
            )"
            :key="language.code"
            :disabled="isTranslating || !isContentEditable"
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
          <k-button
            v-if="hasAnyStrategy"
            :disabled="isTranslating || !isContentEditable"
            :icon="isTranslating ? 'loader' : 'translate'"
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
            v-if="
              hasAnyStrategy &&
              isBatchTranslationEnabled &&
              panel.language.default
            "
            :disabled="isTranslating || !isContentEditable"
            :icon="isTranslating ? 'loader' : 'content-translator-global'"
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

      <template v-else-if="isImportEnabled || hasAnyStrategy">
        <k-box
          theme="none"
          :class="!hasAnyStrategy && 'kct-mt-[var(--spacing-1)]'"
        >
          <k-button-group layout="collapsed">
            <k-button
              v-if="
                isImportEnabled &&
                (!isBatchTranslationEnabled || !panel.language.default)
              "
              :disabled="
                panel.language.default || isTranslating || !isContentEditable
              "
              icon="import"
              variant="filled"
              @click="handleImport()"
            >
              {{ panel.t("johannschopplich.content-translator.import") }}
            </k-button>
            <k-button
              v-if="
                hasAnyStrategy &&
                (!isBatchTranslationEnabled || !panel.language.default)
              "
              :disabled="
                panel.language.default || isTranslating || !isContentEditable
              "
              :icon="isTranslating ? 'loader' : 'translate'"
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
              v-if="
                hasAnyStrategy &&
                isBatchTranslationEnabled &&
                panel.language.default
              "
              :disabled="isTranslating || !isContentEditable"
              :icon="isTranslating ? 'loader' : 'content-translator-global'"
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
          v-show="
            hasAnyStrategy &&
            !isBatchTranslationEnabled &&
            panel.language.default
          "
          theme="none"
          :text="
            panel.t(
              'johannschopplich.content-translator.help.defaultLanguageInfo',
            )
          "
          class="kct-mt-[var(--spacing-1)]"
        />
      </template>
    </template>
  </k-section>
</template>
