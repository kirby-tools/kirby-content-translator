<script setup lang="ts">
import type { TranslationLanguageCoverage } from "../../types";
import { computed, onMounted, provide, ref, useApi, usePanel } from "kirbyuse";
import {
  TOGGLE_INJECTION_KEY,
  useTranslationTree,
} from "../../composables/tree";
import { formatPlural } from "../../utils/i18n";
import TranslationRing from "../Shared/TranslationRing.vue";
import TranslationTree from "../Shared/TranslationTree.vue";

const panel = usePanel();
const api = useApi();

const isLoading = ref(true);
const languages = ref<TranslationLanguageCoverage[]>([]);
const { tree, setTree, toggle } = useTranslationTree();
provide(TOGGLE_INJECTION_KEY, toggle);

const stats = computed(() =>
  languages.value.map((lang) => ({
    code: lang.code,
    value: `${lang.percentage}%`,
    label: lang.name,
    info: formatPlural(
      panel.t("johannschopplich.content-translator.coverage.pagesIncomplete", {
        count: lang.incompletePageCount,
      }),
      lang.incompletePageCount,
    ),
    theme: lang.percentage >= 100 ? "positive" : "info",
    percentage: lang.percentage,
  })),
);

async function fetchCoverage() {
  const response = await api.get("__content-translator__/coverage");
  if (!response) return;
  languages.value = response.languages;
  setTree(response.tree);
}

onMounted(async () => {
  try {
    await fetchCoverage();
  } catch {
  } finally {
    isLoading.value = false;
  }
});
</script>

<template>
  <div
    v-if="!isLoading && stats.length > 0"
    class="kct-mt-[var(--columns-block-gap)]"
  >
    <k-section
      :headline="panel.t('johannschopplich.content-translator.coverage.title')"
    >
      <dl class="k-stats">
        <div
          v-for="stat in stats"
          :key="stat.code"
          class="k-stat kct-items-center"
          :data-theme="stat.theme"
        >
          <dt class="k-stat-label">{{ stat.label }}</dt>
          <dd
            class="k-stat-value kct-relative kct-mb-[var(--spacing-2)] kct-flex kct-items-center kct-justify-center"
          >
            <TranslationRing :value="stat.percentage" />
            <span
              class="kct-[font-weight:var(--font-bold)] kct-absolute kct-text-[length:var(--text-lg)]"
            >
              {{ stat.value }}
            </span>
          </dd>
          <dd class="k-stat-info">{{ stat.info }}</dd>
        </div>
      </dl>
    </k-section>

    <k-section
      :headline="
        panel.t('johannschopplich.content-translator.coverage.pagesToTranslate')
      "
    >
      <TranslationTree v-if="tree.length > 0" :items="tree" />
      <k-empty v-else icon="check">
        {{
          panel.t("johannschopplich.content-translator.coverage.allTranslated")
        }}
      </k-empty>
    </k-section>
  </div>
</template>
