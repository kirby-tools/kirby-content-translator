<script setup lang="ts">
import type { PropType } from "vue";
import type { TranslatorAction } from "../../utils/actions";
import { computed } from "vue";

const props = defineProps({
  actions: {
    type: Array as PropType<TranslatorAction[][]>,
    required: true,
  },
});

const emit = defineEmits<{
  (event: "run", action: TranslatorAction): void;
}>();

const groups = computed(() => props.actions.filter((group) => group.length));
</script>

<template>
  <template v-for="(group, index) in groups" :key="index">
    <hr v-if="index > 0" />
    <k-dropdown-item
      v-for="action in group"
      :key="action.text"
      :disabled="action.disabled"
      :icon="action.icon"
      @click="emit('run', action)"
    >
      {{ action.text }}
    </k-dropdown-item>
  </template>
</template>
