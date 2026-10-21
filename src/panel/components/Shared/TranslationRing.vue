<script setup lang="ts">
import { computed } from "kirbyuse";

const props = defineProps({
  value: {
    type: Number,
    default: 0,
  },
});

const SIZE = 64;
const STROKE_WIDTH = 4;
const RADIUS = (SIZE - STROKE_WIDTH) / 2;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

const offset = computed(() => CIRCUMFERENCE * (1 - props.value / 100));
</script>

<template>
  <svg
    :width="SIZE"
    :height="SIZE"
    role="progressbar"
    :aria-valuenow="value"
    aria-valuemin="0"
    aria-valuemax="100"
    class="translation-ring"
  >
    <circle
      class="translation-ring-track"
      :cx="SIZE / 2"
      :cy="SIZE / 2"
      :r="RADIUS"
      fill="none"
      :stroke-width="STROKE_WIDTH"
    />
    <circle
      class="translation-ring-fill"
      :cx="SIZE / 2"
      :cy="SIZE / 2"
      :r="RADIUS"
      fill="none"
      :stroke-width="STROKE_WIDTH"
      stroke-linecap="round"
      :stroke-dasharray="CIRCUMFERENCE"
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
