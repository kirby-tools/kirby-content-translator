<script setup lang="ts">
import type { PropType } from "vue";
import type { CoverageLabels, TranslationTreeEntry } from "../../types";
import { computed } from "vue";

const props = defineProps({
  item: {
    type: Object as PropType<TranslationTreeEntry>,
    required: true,
  },
  level: {
    type: Number,
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

const arrowIcon = computed(() => {
  if (props.item.isLoading) return "loader";
  return props.item.isOpen ? "angle-down" : "angle-right";
});

const descendantLabel = computed(() =>
  props.labels.pagesIncomplete(props.item.incompleteDescendantCount),
);

const hasCounter = computed(
  () => props.item.incompleteDescendantCount > 0 && !props.item.isOpen,
);
</script>

<template>
  <li :aria-expanded="item.isOpen">
    <p
      class="k-tree-branch kct-mb-0 kct-bg-transparent kct-pe-[2px] hover:kct-bg-transparent"
      :class="
        level > 0 &&
        'kct-border-x-0 kct-border-b-0 kct-border-t kct-border-solid kct-border-[color:var(--color-border-dimmed)]'
      "
      :style="{ '--tree-level': level }"
    >
      <button
        class="k-tree-toggle"
        :disabled="!item.hasChildren"
        type="button"
        @click="emit('toggle', item)"
      >
        <k-icon :type="arrowIcon" />
      </button>

      <button
        class="k-tree-folder"
        type="button"
        @click="emit('navigate', item.link)"
      >
        <k-icon-frame :icon="item.icon ?? 'page'" />
        <span class="k-tree-folder-label">{{ item.label }}</span>
        <span
          v-if="hasCounter"
          class="k-button-badge kct-static kct-transform-none kct-shadow-none"
          data-theme="passive"
          :title="descendantLabel"
          :aria-label="descendantLabel"
        >
          {{ item.incompleteDescendantCount }}
        </span>
      </button>

      <span
        v-if="item.missingLanguages.length > 0"
        class="kct-flex kct-shrink-0 kct-items-center kct-gap-[2px]"
      >
        <k-tag
          v-for="lang in item.missingLanguages"
          :key="lang.code"
          :text="lang.code.toUpperCase()"
          theme="light"
          @click.stop="emit('navigate', `${item.link}?language=${lang.code}`)"
        />
      </span>
    </p>

    <ul v-if="item.hasChildren && item.isOpen && item.children" class="k-tree">
      <TranslationTreeNode
        v-for="child in item.children"
        :key="child.id"
        :item="child"
        :level="level + 1"
        :labels="labels"
        @toggle="emit('toggle', $event)"
        @navigate="emit('navigate', $event)"
      />
    </ul>
  </li>
</template>
