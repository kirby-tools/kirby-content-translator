<script setup>
import { ref, usePanel } from "kirbyuse";
import { usePluginContext } from "../../composables/plugin";
import ContentTranslator from "./ContentTranslator.vue";

const panel = usePanel();
const dropdownContent = ref();
const context = ref();

function toggle() {
  dropdownContent.value.toggle();
}

(async () => {
  context.value = await usePluginContext();
})();
</script>

<template>
  <div v-if="context?.config?.viewButton">
    <k-button
      :dropdown="true"
      :text="panel.t('johannschopplich.content-translator.viewButton.label')"
      icon="content-translator-global"
      responsive="text"
      variant="filled"
      size="sm"
      @click="toggle()"
    >
    </k-button>
    <k-dropdown-content ref="dropdownContent">
      <ContentTranslator :context="context" />
    </k-dropdown-content>
  </div>
</template>
