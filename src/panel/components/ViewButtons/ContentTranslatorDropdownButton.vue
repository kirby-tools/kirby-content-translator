<script setup lang="ts">
import type { PropType } from "vue";
import type {
  KirbyTagConfig,
  PluginContextResponse,
  TranslationProvider,
} from "../../types";
import { isKirby5, ref, usePanel } from "kirbyuse";
import { usePluginContext } from "../../composables/plugin";
import { useTranslationState } from "../../composables/translation";
import ContentTranslatorDropdownContent from "./ContentTranslatorDropdownContent.vue";

const props = defineProps({
  label: {
    type: String,
  },
  import: {
    type: [Boolean, String, Number],
    default: null,
  },
  importFrom: {
    type: String,
  },
  batch: {
    type: [Boolean, String, Number],
    default: null,
  },
  title: {
    type: [Boolean, String, Number],
    default: null,
  },
  slug: {
    type: [Boolean, String, Number],
    default: null,
  },
  confirm: {
    type: [Boolean, String, Number],
    default: null,
  },
  fieldTypes: {
    type: Array as PropType<string[]>,
  },
  includeFields: {
    type: Array as PropType<string[]>,
  },
  excludeFields: {
    type: Array as PropType<string[]>,
  },
  kirbyTags: {
    type: Object as PropType<Record<string, KirbyTagConfig>>,
  },
  provider: {
    type: String as PropType<TranslationProvider>,
    default: null,
  },
  theme: {
    type: String,
    default: "notice-icon",
  },
});

const _isKirby5 = isKirby5();
const panel = usePanel();
const { isTranslating } = useTranslationState();

const dropdownContent = ref();
const context = ref<PluginContextResponse>();

(async () => {
  context.value = await usePluginContext();

  if (!panel.multilang) {
    panel.notification.error(
      "The Content Translator plugin requires a multi-language Kirby installation.",
    );
  }
})();

function toggle() {
  dropdownContent.value.toggle();
}
</script>

<template>
  <div v-if="_isKirby5 || context?.config?.viewButton">
    <k-button
      :dropdown="true"
      :text="
        label || panel.t('johannschopplich.content-translator.viewButton.label')
      "
      :icon="isTranslating ? 'loader' : 'content-translator-global'"
      responsive="text"
      :theme="theme"
      variant="filled"
      size="sm"
      @click="toggle()"
    >
    </k-button>
    <k-dropdown-content ref="dropdownContent">
      <ContentTranslatorDropdownContent
        v-if="context"
        :context="context"
        :props="props"
      />
    </k-dropdown-content>
  </div>
</template>
