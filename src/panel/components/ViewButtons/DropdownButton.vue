<script setup>
import { isKirby5, ref, usePanel } from "kirbyuse";
import { usePluginContext } from "../../composables/plugin";
import DropdownContent from "./DropdownContent.vue";

const _isKirby5 = isKirby5();
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
      <DropdownContent :context="context" />
    </k-dropdown-content>
  </div>
</template>
