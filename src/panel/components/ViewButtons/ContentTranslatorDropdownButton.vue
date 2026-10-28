<script setup lang="ts">
import type { PropType } from "vue";
import type { PluginContextResponse } from "../../types";
import { LicensingDropdownItems } from "@kirby-tools/licensing/components";
import { usePanel } from "kirbyuse";
import { computed, reactive, ref } from "vue";
import { usePluginContext } from "../../composables/plugin";
import { useTranslationState } from "../../composables/translation";
import {
  useContentTranslatorActions,
  useVariableActions,
} from "../../composables/translator-actions";

// Kirby binds every view button prop (`responsive`, `size`, …), and the ones
// not declared here would otherwise land as attributes on the wrapper `<div>`.
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
    default: "notice-icon",
  },
});

const panel = usePanel();
const { isTranslating } = useTranslationState();

const dropdown = ref();
const defaultLanguage = panel.languages.find((language) => language.default)!;
// Kirby renders the buttons anew for every view, so the view stays the same
// for this instance.
const isLanguageView = panel.view.component === "k-language-view";
const variableActions = isLanguageView
  ? reactive(useVariableActions(props))
  : undefined;
const contentActions = isLanguageView
  ? undefined
  : reactive(useContentTranslatorActions(props));
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

function toggle() {
  dropdown.value.toggle();
  // Kirby mounts the buttons for every view, so `initialize()` at mount would
  // request the model's fields on each navigation.
  actionsInitialization ??= initializeActions();
}

async function initializeActions() {
  await contextInitialization;
  if (!context.value || !panel.multilang) return;

  const translatorActions = variableActions ?? contentActions!;

  try {
    await translatorActions.initialize(context.value);
  } catch (error) {
    panel.error(error as Error);
    return;
  }

  if (translatorActions.missingStrategyMessage) {
    panel.notification.error(translatorActions.missingStrategyMessage);
  }
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
    />
    <k-dropdown ref="dropdown">
      <template v-if="variableActions?.isInitialized">
        <k-dropdown-item
          v-if="
            variableActions.hasAnyStrategy &&
            (!variableActions.isBatchTranslationEnabled ||
              !variableActions.viewLanguage.default)
          "
          :disabled="
            variableActions.viewLanguage.default ||
            isTranslating ||
            !panel.permissions.languages.update
          "
          icon="translate"
          @click="
            variableActions.translateLanguageVariables(
              variableActions.viewLanguage,
            )
          "
        >
          {{
            panel.t("johannschopplich.content-translator.translate", {
              language: variableActions.viewLanguage.code.toUpperCase(),
            })
          }}
        </k-dropdown-item>
        <k-dropdown-item
          v-if="
            variableActions.hasAnyStrategy &&
            variableActions.isBatchTranslationEnabled &&
            variableActions.viewLanguage.default
          "
          :disabled="isTranslating || !panel.permissions.languages.update"
          icon="content-translator-global"
          @click="variableActions.batchTranslateLanguageVariables()"
        >
          {{
            panel.t("johannschopplich.content-translator.batchTranslate", {
              language: defaultLanguage.code.toUpperCase(),
            })
          }}
        </k-dropdown-item>
      </template>

      <template
        v-else-if="
          contentActions?.isInitialized &&
          contentActions.isImportEnabled &&
          contentActions.importFrom === 'all'
        "
      >
        <k-dropdown-item
          v-for="language in panel.languages.filter(
            (language) => language.code !== panel.language.code,
          )"
          :key="language.code"
          :disabled="isTranslating || !contentActions.isContentEditable"
          icon="import"
          @click="contentActions.handleImport(language)"
        >
          {{
            panel.t("johannschopplich.content-translator.importFrom", {
              language: language.code.toUpperCase(),
            })
          }}
        </k-dropdown-item>
        <hr v-if="contentActions.hasAnyStrategy" />
        <k-dropdown-item
          v-if="contentActions.hasAnyStrategy"
          :disabled="isTranslating || !contentActions.isContentEditable"
          icon="translate"
          @click="contentActions.handleTranslate()"
        >
          {{
            panel.t("johannschopplich.content-translator.translate", {
              language: panel.language.code?.toUpperCase(),
            })
          }}
        </k-dropdown-item>
        <k-dropdown-item
          v-if="
            contentActions.hasAnyStrategy &&
            contentActions.isBatchTranslationEnabled &&
            panel.language.default
          "
          :disabled="isTranslating || !contentActions.isContentEditable"
          icon="content-translator-global"
          @click="contentActions.handleBatchTranslate()"
        >
          {{
            panel.t("johannschopplich.content-translator.batchTranslate", {
              language: defaultLanguage.code.toUpperCase(),
            })
          }}
        </k-dropdown-item>
      </template>

      <template v-else-if="contentActions?.isInitialized">
        <template
          v-if="
            contentActions.isImportEnabled &&
            (!contentActions.isBatchTranslationEnabled ||
              !panel.language.default)
          "
        >
          <k-dropdown-item
            :disabled="
              panel.language.default ||
              isTranslating ||
              !contentActions.isContentEditable
            "
            icon="import"
            @click="contentActions.handleImport()"
          >
            {{ panel.t("johannschopplich.content-translator.import") }}
          </k-dropdown-item>
          <hr v-if="contentActions.hasAnyStrategy" />
        </template>
        <k-dropdown-item
          v-if="
            contentActions.hasAnyStrategy &&
            (!contentActions.isBatchTranslationEnabled ||
              !panel.language.default)
          "
          :disabled="
            panel.language.default ||
            isTranslating ||
            !contentActions.isContentEditable
          "
          icon="translate"
          @click="contentActions.handleTranslate(defaultLanguage)"
        >
          {{
            panel.t("johannschopplich.content-translator.translate", {
              language: panel.language.code?.toUpperCase(),
            })
          }}
        </k-dropdown-item>
        <k-dropdown-item
          v-if="
            contentActions.hasAnyStrategy &&
            contentActions.isBatchTranslationEnabled &&
            panel.language.default
          "
          :disabled="isTranslating || !contentActions.isContentEditable"
          icon="content-translator-global"
          @click="contentActions.handleBatchTranslate()"
        >
          {{
            panel.t("johannschopplich.content-translator.batchTranslate", {
              language: defaultLanguage.code.toUpperCase(),
            })
          }}
        </k-dropdown-item>
      </template>

      <template v-if="hasLicenseItems">
        <hr />
        <LicensingDropdownItems
          :dialog="context!.licenseDialog"
          :license-status="context!.licenseStatus"
          pricing-url="https://kirby.tools/content-translator/buy"
        />
      </template>
    </k-dropdown>
  </div>
</template>
