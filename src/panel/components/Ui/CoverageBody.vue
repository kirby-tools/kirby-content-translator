<script setup lang="ts">
import type { PropType } from "vue";
import type {
  CoverageLabels,
  TranslationLanguageCoverage,
  TranslationTreeEntry,
} from "../../types";
import CoverageRing from "./CoverageRing.vue";
import TranslationTree from "./TranslationTree.vue";

defineProps({
  languages: {
    type: Array as PropType<TranslationLanguageCoverage[]>,
    required: true,
  },
  tree: {
    type: Array as PropType<TranslationTreeEntry[]>,
    default: () => [],
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
  <div class="k-content-translator-coverage">
    <k-section :headline="labels.title">
      <dl class="k-stats">
        <div
          v-for="language in languages"
          :key="language.code"
          class="k-stat kct-items-center"
          :data-theme="language.percentage >= 100 ? 'positive' : 'info'"
        >
          <dt class="k-stat-label">{{ language.name }}</dt>
          <dd
            class="k-stat-value kct-relative kct-mb-[var(--spacing-2)] kct-flex kct-items-center kct-justify-center"
          >
            <CoverageRing :value="language.percentage" />
            <span
              class="kct-[font-weight:var(--font-bold)] kct-absolute kct-text-[length:var(--text-lg)]"
            >
              {{ language.percentage }}%
            </span>
          </dd>
          <dd class="k-stat-info">
            {{
              language.incompletePageCount > 0
                ? labels.pagesIncomplete(language.incompletePageCount)
                : labels.allTranslated
            }}
          </dd>
        </div>
      </dl>
    </k-section>

    <k-section v-if="tree.length > 0" :headline="labels.pagesToTranslate">
      <TranslationTree
        :items="tree"
        :labels="labels"
        @toggle="emit('toggle', $event)"
        @navigate="emit('navigate', $event)"
      />
    </k-section>
  </div>
</template>
