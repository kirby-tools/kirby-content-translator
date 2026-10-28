<script setup lang="ts">
import type { PropType } from "vue";
import type { TranslatorAction } from "../../utils/actions";
import { computed } from "vue";

const props = defineProps({
  actions: {
    type: Array as PropType<TranslatorAction[][]>,
    required: true,
  },
  isTranslating: Boolean,
  info: String,
});

const emit = defineEmits<{
  (event: "run", action: TranslatorAction): void;
}>();

const buttons = computed(() => props.actions.flat());
</script>

<template>
  <div class="[&>*+*]:kct-mt-[var(--spacing-1)]">
    <k-box v-if="buttons.length > 0" theme="none">
      <k-button-group layout="collapsed">
        <k-button
          v-for="action in buttons"
          :key="action.text"
          :disabled="action.disabled"
          :icon="
            isTranslating && action.type !== 'import' ? 'loader' : action.icon
          "
          :theme="action.type === 'import' ? undefined : 'notice-icon'"
          variant="filled"
          @click="emit('run', action)"
        >
          {{ action.text }}
        </k-button>
      </k-button-group>
    </k-box>

    <k-box v-if="info" theme="none" :text="info" />
  </div>
</template>
