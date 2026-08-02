<script setup lang="ts">
import type { PropType } from "vue";
import type { PluginContextResponse } from "../../types";
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
    type: Object as PropType<Record<string, string[]>>,
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
const initializationError = ref<Error>();

(async () => {
  try {
    context.value = await usePluginContext();
  } catch (error) {
    // A misconfigured plugin option makes the context endpoint fail. Without the notification the failure would go
    // unnoticed on Kirby 4, where the button is gated by the `viewButton` option of the very context that just failed
    initializationError.value = error as Error;
    panel.notification.error((error as Error).message);
    return;
  }

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
      responsive
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
      <k-dropdown-item
        v-else-if="initializationError"
        disabled
        icon="alert"
        theme="negative"
      >
        {{ initializationError.message }}
      </k-dropdown-item>
    </k-dropdown-content>
  </div>
</template>
