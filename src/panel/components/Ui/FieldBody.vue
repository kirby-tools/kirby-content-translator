<script setup lang="ts">
import {
  disabled as disabledProp,
  help as helpProp,
  id as idProp,
  name as nameProp,
} from "kirbyuse/props";

defineProps({
  ...disabledProp,
  ...helpProp,
  ...idProp,
  ...nameProp,
  label: {
    type: String,
    required: true,
  },
});
</script>

<template>
  <k-field
    :id="id"
    :disabled="disabled"
    :help="help"
    :input="false"
    :label="label"
    :name="name"
    type="content-translator"
    class="k-content-translator-field"
  >
    <template v-if="$slots.options" #options>
      <slot name="options" />
    </template>

    <slot />
  </k-field>
</template>
