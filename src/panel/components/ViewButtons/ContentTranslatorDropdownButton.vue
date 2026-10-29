<script setup lang="ts">
import type { PropType } from "vue";
import type { PluginContextResponse } from "../../types";
import { LicensingDropdownItems } from "@kirby-tools/licensing/components";
import { computed, ref, usePanel } from "kirbyuse";
import {
  resolveLicenseStatus,
  usePluginContext,
} from "../../composables/plugin";
import {
  useContentTranslator,
  useTranslationState,
} from "../../composables/translation";
import ContentTranslatorDropdownContent from "./ContentTranslatorDropdownContent.vue";
import VariablesDropdownContent from "./VariablesDropdownContent.vue";

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
  confirm: {
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
    default: "notice-icon",
  },
});

const panel = usePanel();
const { isTranslating } = useTranslationState();

const dropdownContent = ref();
const isLanguageView = panel.view.component === "k-language-view";
const translator = isLanguageView ? undefined : useContentTranslator();
let actionsInitialization: Promise<unknown> | undefined;
const context = ref<PluginContextResponse>();
const hasInitializationError = ref(false);
const licenseStatus = computed(
  () => context.value && resolveLicenseStatus(context.value),
);

(async () => {
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

function toggle() {
  dropdownContent.value.toggle();
}

// Kirby renders the dropdown content only while it is open, so the content
// would otherwise set itself up on every open.
function initializeOnce<T>(initialize: () => Promise<T>) {
  actionsInitialization ??= initialize().catch((error) => {
    actionsInitialization = undefined;
    throw error;
  });
  return actionsInitialization as Promise<T>;
}
</script>

<template>
  <div v-if="!hasInitializationError">
    <k-button
      :dropdown="true"
      :text="
        label || panel.t('johannschopplich.content-translator.viewButton.label')
      "
      :icon="isTranslating ? 'loader' : 'content-translator-global'"
      responsive
      :theme="theme"
      variant="filled"
      size="sm"
      @click="toggle()"
    >
    </k-button>
    <k-dropdown-content ref="dropdownContent">
      <VariablesDropdownContent
        v-if="context && isLanguageView"
        :context="context"
        :props="props"
        :initialize-once="initializeOnce"
      />
      <ContentTranslatorDropdownContent
        v-else-if="context && translator"
        :context="context"
        :props="props"
        :translator="translator"
        :initialize-once="initializeOnce"
      />

      <template
        v-if="licenseStatus !== undefined && licenseStatus !== 'active'"
      >
        <hr />
        <LicensingDropdownItems
          label="Kirby Content Translator"
          api-namespace="__content-translator__"
          :license-status="licenseStatus"
          pricing-url="https://kirby.tools/content-translator/buy"
        />
      </template>
    </k-dropdown-content>
  </div>
</template>
