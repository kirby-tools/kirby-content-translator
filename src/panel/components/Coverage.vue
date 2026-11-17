<script setup lang="ts">
import type { TranslationLanguageCoverage } from "../types";
import { usePanel } from "kirbyuse";
import { onMounted, ref } from "vue";
import { useTranslationTree } from "../composables/tree";
import { COVERAGE_API_ROUTE } from "../constants";
import { createCoverageLabels } from "../utils/coverage-labels";
import CoverageBody from "./Ui/CoverageBody.vue";

const panel = usePanel();

const languages = ref<TranslationLanguageCoverage[]>([]);
const { tree, setTree, toggle } = useTranslationTree();

const labels = createCoverageLabels(panel.t);

onMounted(async () => {
  try {
    const response = await panel.api.get(COVERAGE_API_ROUTE);
    languages.value = response.languages;
    setTree(response.tree);
  } catch {
    // Kirby answers 404 when the `coverage` option is off.
  }
});
</script>

<template>
  <CoverageBody
    v-if="languages.length > 0"
    class="kct-mt-[var(--columns-block-gap)]"
    :languages="languages"
    :tree="tree"
    :labels="labels"
    @toggle="toggle"
    @navigate="panel.view.open($event)"
  />
</template>
