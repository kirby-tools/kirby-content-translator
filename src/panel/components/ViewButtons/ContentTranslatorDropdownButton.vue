<script setup>
import { isKirby5, ref, usePanel } from "kirbyuse";
import { usePluginContext } from "../../composables/plugin";
import ContentTranslatorDropdownContent from "./ContentTranslatorDropdownContent.vue";

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
      :text="panel.t('johannschopplich.content-translator.viewButton.label')"
      icon="content-translator-global"
      responsive="text"
      theme="notice-icon"
      variant="filled"
      size="sm"
      @click="toggle()"
    >
    </k-button>
    <k-dropdown-content ref="dropdownContent">
      <ContentTranslatorDropdownContent :context="context" />
    </k-dropdown-content>
  </div>
</template>
