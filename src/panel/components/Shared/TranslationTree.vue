<script setup lang="ts">
import type { PropType } from "vue";
import type { TranslationTreeEntry } from "../../types";
import TranslationTreeNode from "./TranslationTreeNode.vue";

defineProps({
  items: {
    type: Array as PropType<TranslationTreeEntry[]>,
    required: true,
  },
});
</script>

<template>
  <ul class="k-tree k-translator-tree kct-flex kct-flex-col kct-gap-[2px]">
    <TranslationTreeNode
      v-for="item in items"
      :key="item.id"
      :item="item"
      :level="0"
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
