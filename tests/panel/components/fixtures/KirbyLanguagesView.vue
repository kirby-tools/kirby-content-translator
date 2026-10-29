<!-- Kirby's `k-languages-view`, reduced to the branches its template switches between. -->
<script lang="ts">
import type { PropType } from "vue";

export default {
  props: {
    languages: {
      type: Array as PropType<string[]>,
      default: () => [],
    },
  },
};
</script>

<template>
  <k-panel-inside class="k-languages-view">
    <template v-if="languages.length > 0">
      <ul class="languages">
        <li v-for="language in languages" :key="language">{{ language }}</li>
      </ul>
    </template>

    <template v-else-if="languages.length === 0">
      <p>No languages</p>
    </template>
  </k-panel-inside>
</template>
