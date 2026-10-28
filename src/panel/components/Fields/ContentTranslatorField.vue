<script setup lang="ts">
import type { LicenseStatus } from "@kirby-tools/licensing";
import type { PropType } from "vue";
import { LicensingButtonGroup } from "@kirby-tools/licensing/components";
import { usePanel } from "kirbyuse";
import {
  disabled as disabledProp,
  help as helpProp,
  id as idProp,
  name as nameProp,
} from "kirbyuse/props";
import { usePluginContext } from "../../composables/plugin";
import { useTranslationState } from "../../composables/translation";
import { useContentTranslatorActions } from "../../composables/translator-actions";

const props = defineProps({
  // Declared to keep `disabled` off `k-field`, so the field never looks disabled.
  ...disabledProp,
  ...helpProp,
  ...idProp,
  ...nameProp,
  label: {
    type: String,
    required: true,
  },
  import: {
    type: Boolean,
    default: undefined,
  },
  importFrom: String,
  batch: {
    type: Boolean,
    default: undefined,
  },
  title: {
    type: Boolean,
    default: undefined,
  },
  slug: {
    type: Boolean,
    default: undefined,
  },
  confirm: {
    type: Boolean,
    default: undefined,
  },
  fieldTypes: Array as PropType<string[]>,
  includeFields: Array as PropType<string[]>,
  excludeFields: Array as PropType<string[]>,
  kirbyTags: Object as PropType<Record<string, string[]>>,
  cascade: [String, Array] as PropType<string | string[]>,
  systemPrompt: String,
  licenseStatus: {
    type: String as PropType<LicenseStatus>,
    required: true,
  },
  licenseDialog: {
    type: String,
    required: true,
  },
});

const panel = usePanel();
const hasLicenseButtons = !__PLAYGROUND__;
const { isTranslating } = useTranslationState();
const defaultLanguage = panel.languages.find((language) => language.default)!;
const {
  initialize,
  isInitialized,
  isImportEnabled,
  importFrom,
  isBatchTranslationEnabled,
  hasAnyStrategy,
  missingStrategyMessage,
  isContentEditable,
  handleImport,
  handleTranslate,
  handleBatchTranslate,
} = useContentTranslatorActions(props);

if (panel.multilang) {
  (async () => {
    await initialize(await usePluginContext());
  })();
}
</script>

<template>
  <k-field
    :id="id"
    :help="help"
    :input="false"
    :label="label"
    :name="name"
    type="content-translator"
    class="k-content-translator-field"
  >
    <template v-if="hasLicenseButtons" #options>
      <LicensingButtonGroup
        :dialog="licenseDialog"
        :license-status="licenseStatus"
        pricing-url="https://kirby.tools/content-translator/buy"
      />
    </template>

    <k-box v-if="!panel.multilang" theme="empty">
      <k-text>This field requires multi-language support to be enabled.</k-text>
    </k-box>
    <div v-else-if="isInitialized" class="[&>*+*]:kct-mt-[var(--spacing-1)]">
      <k-box v-if="!hasAnyStrategy" theme="empty">
        <k-text>{{ missingStrategyMessage }}</k-text>
      </k-box>

      <k-box v-if="isImportEnabled && importFrom === 'all'" theme="none">
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
        <k-box theme="none">
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
          v-if="
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
        />
      </template>
    </div>
  </k-field>
</template>
