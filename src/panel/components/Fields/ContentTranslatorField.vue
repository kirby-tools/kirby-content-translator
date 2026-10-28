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
import { computed } from "vue";
import { usePluginContext } from "../../composables/plugin";
import { useTranslationState } from "../../composables/translation";
import { useContentTranslatorActions } from "../../composables/translator-actions";
import TranslatorButtons from "../Shared/TranslatorButtons.vue";

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
const {
  initialize,
  isInitialized,
  actions,
  hasDefaultLanguageInfo,
  hasAnyStrategy,
  missingStrategyMessage,
  run,
} = useContentTranslatorActions(props);

const info = computed(() =>
  hasDefaultLanguageInfo.value
    ? panel.t("johannschopplich.content-translator.help.defaultLanguageInfo")
    : undefined,
);

if (panel.multilang) {
  (async () => {
    try {
      await initialize(await usePluginContext());
    } catch (error) {
      panel.error(error as Error);
    }
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
      <TranslatorButtons
        :actions="actions"
        :is-translating="isTranslating"
        :info="info"
        @run="run"
      />
    </div>
  </k-field>
</template>
