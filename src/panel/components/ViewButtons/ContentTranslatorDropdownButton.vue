<script setup>
import { isKirby5, ref, usePanel } from "kirbyuse";
import { usePluginContext } from "../../composables/plugin";
import ContentTranslatorDropdownContent from "./ContentTranslatorDropdownContent.vue";

const props = defineProps({
  label: {
    type: String,
    required: false,
  },
  import: {
    type: [Boolean, String, Number],
    required: false,
  },
  importFrom: {
    type: String,
    required: false,
  },
  batch: {
    type: [Boolean, String, Number],
    required: false,
  },
  title: {
    type: [Boolean, String, Number],
    required: false,
  },
  slug: {
    type: [Boolean, String, Number],
    required: false,
  },
  confirm: {
    type: [Boolean, String, Number],
    required: false,
  },
  fieldTypes: {
    type: Array,
    required: false,
  },
  includeFields: {
    type: Array,
    required: false,
  },
  excludeFields: {
    type: Array,
    required: false,
  },
  kirbyTags: {
    type: Object,
    required: false,
  },
  theme: {
    type: String,
    default: "notice-icon",
  },
});

const _isKirby5 = isKirby5();
const panel = usePanel();
const dropdownContent = ref();
const context = ref();

function toggle() {
  dropdownContent.value.toggle();
}

(async () => {
  context.value = await usePluginContext();

  if (!panel.multilang) {
    panel.notification.error(
      "The Content Translator plugin requires a multi-language Kirby installation.",
    );
  }
})();
</script>

<template>
  <div v-if="_isKirby5 || context?.config?.viewButton">
    <k-button
      :dropdown="true"
      :text="
        label || panel.t('johannschopplich.content-translator.viewButton.label')
      "
      icon="content-translator-global"
      responsive="text"
      :theme="theme"
      variant="filled"
      size="sm"
      @click="toggle()"
    >
    </k-button>
    <k-dropdown-content ref="dropdownContent">
      <ContentTranslatorDropdownContent :context="context" :props="props" />
    </k-dropdown-content>
  </div>
</template>
