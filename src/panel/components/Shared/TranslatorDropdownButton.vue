<script setup lang="ts">
import type { PropType } from "vue";
import type { TranslatorAction } from "../../utils/actions";
import { ref } from "vue";
import TranslatorDropdownItems from "./TranslatorDropdownItems.vue";

defineProps({
  actions: {
    type: Array as PropType<TranslatorAction[][]>,
    required: true,
  },
  label: {
    type: String,
    required: true,
  },
  theme: {
    type: String,
    default: "notice-icon",
  },
  isTranslating: Boolean,
});

const emit = defineEmits<{
  (event: "toggle"): void;
  (event: "run", action: TranslatorAction): void;
}>();

const dropdown = ref();

function toggle() {
  dropdown.value.toggle();
  emit("toggle");
}
</script>

<template>
  <div>
    <k-button
      :dropdown="true"
      :text="label"
      :icon="isTranslating ? 'loader' : 'content-translator-global'"
      responsive
      :theme="theme"
      variant="filled"
      size="sm"
      @click="toggle()"
    />
    <k-dropdown ref="dropdown">
      <TranslatorDropdownItems :actions="actions" @run="emit('run', $event)" />
      <slot />
    </k-dropdown>
  </div>
</template>
