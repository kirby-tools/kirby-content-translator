<script setup lang="ts">
import type { PropType } from "vue";
import type { PluginContextResponse } from "../../types";
import { LicensingDropdownItems } from "@kirby-tools/licensing/components";
import { usePanel } from "kirbyuse";
import { computed, ref } from "vue";
import { usePluginContext } from "../../composables/plugin";
import { useTranslationState } from "../../composables/translation";
import {
  useContentTranslatorActions,
  useVariableActions,
} from "../../composables/translator-actions";
import TranslatorDropdownButton from "../Shared/TranslatorDropdownButton.vue";

// Kirby binds every view button prop (`responsive`, `size`, …), and the ones
// not declared here would otherwise fall through to the root of
// `TranslatorDropdownButton`.
defineOptions({ inheritAttrs: false });

const props = defineProps({
  label: {
    type: String,
  },
  import: {
    type: [Boolean, String, Number],
    default: null,
  },
  importFrom: {
    type: String,
  },
  batch: {
    type: [Boolean, String, Number],
    default: null,
  },
  // Kirby's button sends `title` as a string, `""` for `false`, which Vue
  // casts to `true` when `Boolean` comes first.
  title: {
    type: [String, Boolean, Number],
    default: null,
  },
  slug: {
    type: [Boolean, String, Number],
    default: null,
  },
  fieldTypes: {
    type: Array as PropType<string[]>,
  },
  includeFields: {
    type: Array as PropType<string[]>,
  },
  excludeFields: {
    type: Array as PropType<string[]>,
  },
  kirbyTags: {
    type: Object as PropType<Record<string, string[]>>,
  },
  cascade: {
    type: [String, Array] as PropType<string | string[]>,
  },
  theme: {
    type: String,
  },
});

const panel = usePanel();
const { isTranslating } = useTranslationState();

// Kirby renders the buttons anew for every view, so the view stays the same
// for this instance.
const { initialize, actions, missingStrategyMessage, run } =
  panel.view.component === "k-language-view"
    ? useVariableActions(props)
    : useContentTranslatorActions(props);
const context = ref<PluginContextResponse>();
const hasInitializationError = ref(false);
const hasLicenseItems = computed(
  () =>
    !__PLAYGROUND__ &&
    context.value !== undefined &&
    context.value.licenseStatus !== "active",
);

let actionsInitialization: Promise<void> | undefined;

const contextInitialization = (async () => {
  try {
    context.value = await usePluginContext();
  } catch (error) {
    hasInitializationError.value = true;
    panel.error(error as Error);
    return;
  }

  if (!panel.multilang) {
    panel.notification.error(
      "The Content Translator plugin requires a multi-language Kirby installation.",
    );
  }
})();

function onToggle() {
  // Kirby mounts the buttons for every view, so `initialize()` at mount would
  // request the model's fields on each navigation.
  actionsInitialization ??= initializeActions();
}

async function initializeActions() {
  await contextInitialization;
  if (!context.value || !panel.multilang) return;

  try {
    await initialize(context.value);
  } catch (error) {
    actionsInitialization = undefined;
    panel.error(error as Error);
    return;
  }

  if (missingStrategyMessage.value) {
    panel.notification.error(missingStrategyMessage.value);
  }
}
</script>

<template>
  <TranslatorDropdownButton
    v-if="!hasInitializationError"
    :actions="actions"
    :label="
      label || panel.t('johannschopplich.content-translator.viewButton.label')
    "
    :theme="theme"
    :is-translating="isTranslating"
    @toggle="onToggle()"
    @run="run"
  >
    <template v-if="hasLicenseItems">
      <hr />
      <LicensingDropdownItems
        :dialog="context!.licenseDialog"
        :license-status="context!.licenseStatus"
        pricing-url="https://kirby.tools/content-translator/buy"
      />
    </template>
  </TranslatorDropdownButton>
</template>
