<script setup lang="ts">
import { computed } from "kirbyuse";

const props = defineProps({
  value: {
    type: Number,
    default: 0,
  },
  size: {
    type: Number,
    default: 64,
  },
  strokeWidth: {
    type: Number,
    default: 4,
  },
});

const radius = computed(() => (props.size - props.strokeWidth) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);
const offset = computed(() => circumference.value * (1 - props.value / 100));
const center = computed(() => props.size / 2);
</script>

<template>
  <svg
    :width="size"
    :height="size"
    role="progressbar"
    :aria-valuenow="value"
    aria-valuemin="0"
    aria-valuemax="100"
    class="translation-ring"
  >
    <circle
      class="translation-ring-track"
      :cx="center"
      :cy="center"
      :r="radius"
      fill="none"
      :stroke-width="strokeWidth"
    />
    <circle
      class="translation-ring-fill"
      :cx="center"
      :cy="center"
      :r="radius"
      fill="none"
      :stroke-width="strokeWidth"
      stroke-linecap="round"
      :stroke-dasharray="circumference"
      :stroke-dashoffset="offset"
    />
  </svg>
</template>

<style>
.translation-ring {
  transform: rotate(-90deg);
}

.translation-ring-track {
  stroke: light-dark(var(--color-gray-300), var(--color-gray-700));
}

.translation-ring-fill {
  stroke: var(--theme-color-icon);
}
</style>
