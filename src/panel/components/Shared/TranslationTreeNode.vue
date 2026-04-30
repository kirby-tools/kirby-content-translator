<script setup lang="ts">
import type { PropType } from "vue";
import type { TranslationTreeEntry } from "../../types";
import { computed, inject, usePanel } from "kirbyuse";
import { TOGGLE_INJECTION_KEY } from "../../composables/tree";
import { formatPlural } from "../../utils/i18n";

const props = defineProps({
  item: {
    type: Object as PropType<TranslationTreeEntry>,
    required: true,
  },
  level: {
    type: Number,
    required: true,
  },
});

const panel = usePanel();
const toggle = inject(TOGGLE_INJECTION_KEY)!;

const arrowIcon = computed(() => {
  if (props.item.loading) return "loader";
  return props.item.open ? "angle-down" : "angle-right";
});

function formatDescendantCount(count: number) {
  return formatPlural(
    panel.t("johannschopplich.content-translator.coverage.pagesIncomplete", {
      count,
    }),
    count,
  );
}

function navigate() {
  panel.view.open(props.item.link);
}

function navigateToLanguage(code: string) {
  panel.view.open(`${props.item.link}?language=${code}`);
}
</script>

<template>
  <li :aria-expanded="item.open">
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
        @click="toggle(item)"
      >
        <k-icon :type="arrowIcon" />
      </button>

      <button class="k-tree-folder" type="button" @click="navigate">
        <k-icon-frame :icon="item.icon ?? 'page'" />
        <span class="k-tree-folder-label">{{ item.label }}</span>
      </button>

      <span
        class="kct-flex kct-shrink-0 kct-items-center kct-gap-[var(--tags-gap)]"
      >
        <k-tag
          v-if="item.incompleteDescendants > 0 && !item.open"
          :text="formatDescendantCount(item.incompleteDescendants)"
          theme="light"
        />
        <template v-if="item.missing">
          <k-tag
            v-if="item.isFullyUntranslated"
            :text="
              panel.t('johannschopplich.content-translator.coverage.untranslated')
            "
            theme="light"
          />
          <template v-else>
            <k-tag
              v-for="lang in item.missing"
              :key="lang.code"
              :text="lang.code.toUpperCase()"
              theme="light"
              @click.native.stop="navigateToLanguage(lang.code)"
            />
          </template>
        </template>
      </span>
    </p>

    <ul v-if="item.hasChildren && item.open && item.children" class="k-tree">
      <TranslationTreeNode
        v-for="child in item.children"
        :key="child.id"
        :item="child"
        :level="level + 1"
      />
    </ul>
  </li>
</template>
