<script setup lang="ts">
import type { PropType } from "vue";
import type { CoverageLabels, TranslationTreeEntry } from "../../types";
import TranslationTreeNode from "./TranslationTreeNode.vue";

defineProps({
  items: {
    type: Array as PropType<TranslationTreeEntry[]>,
    required: true,
  },
  labels: {
    type: Object as PropType<CoverageLabels>,
    required: true,
  },
});

const emit = defineEmits<{
  (event: "toggle", item: TranslationTreeEntry): void;
  (event: "navigate", link: string): void;
}>();
</script>

<template>
  <ul class="k-tree k-translator-tree kct-flex kct-flex-col kct-gap-[2px]">
    <TranslationTreeNode
      v-for="item in items"
      :key="item.id"
      :item="item"
      :level="0"
      :labels="labels"
      @toggle="emit('toggle', $event)"
      @navigate="emit('navigate', $event)"
    />
  </ul>
</template>

<style>
.k-translator-tree > li {
  background: var(--item-color-back);
  box-shadow: var(--item-shadow);
  border-radius: var(--rounded);
  overflow: hidden;
}
</style>
