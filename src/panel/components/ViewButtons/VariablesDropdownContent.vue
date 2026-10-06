<script lang="ts">
import type { PropType } from "vue";
import type { PluginContextResponse, TranslatorOptions } from "../../types";
import { computed, ref, usePanel } from "kirbyuse";
import { useTranslationState } from "../../composables/translation";
import { useVariableTranslation } from "../../composables/variables";
import { resolveStrategyReadiness } from "../../utils/strategy";
import { resolveTranslatorConfig } from "../../utils/translator-config";

export default {
  inheritAttrs: false,
};
</script>

<script setup lang="ts">
const props = defineProps({
  context: {
    type: Object as PropType<PluginContextResponse>,
    required: true,
  },
  props: {
    type: Object as PropType<TranslatorOptions>,
    required: true,
  },
});

const panel = usePanel();
const { isTranslating } = useTranslationState();
const { translateLanguageVariables, batchTranslateLanguageVariables } =
  useVariableTranslation(props.props);

const defaultLanguage = panel.languages.find((language) => language.default)!;
// The language the view shows; `panel.language` is the content language of
// the header's switch.
const viewLanguage = computed(() =>
  panel.languages.find((language) => language.code === panel.view.props.id)!,
);
const { isBatchTranslationEnabled } = resolveTranslatorConfig(
  props.context.config,
  props.props,
);
const hasAnyStrategy = ref(false);

resolveStrategyReadiness(props.context.config).then((strategyReadiness) => {
  hasAnyStrategy.value = strategyReadiness.hasAnyStrategy;

  if (strategyReadiness.missingStrategyMessage) {
    panel.notification.error(strategyReadiness.missingStrategyMessage);
  }
});
</script>

<template>
  <div>
    <k-dropdown-item
      v-if="
        hasAnyStrategy && (!isBatchTranslationEnabled || !viewLanguage.default)
      "
      :disabled="
        viewLanguage.default ||
        isTranslating ||
        !panel.permissions.languages.update
      "
      icon="translate"
      @click="translateLanguageVariables(viewLanguage)"
    >
      {{
        panel.t("johannschopplich.content-translator.translate", {
          language: viewLanguage.code.toUpperCase(),
        })
      }}
    </k-dropdown-item>
    <k-dropdown-item
      v-if="hasAnyStrategy && isBatchTranslationEnabled && viewLanguage.default"
      :disabled="isTranslating || !panel.permissions.languages.update"
      icon="content-translator-global"
      @click="batchTranslateLanguageVariables()"
    >
      {{
        panel.t("johannschopplich.content-translator.batchTranslate", {
          language: defaultLanguage.code.toUpperCase(),
        })
      }}
    </k-dropdown-item>
  </div>
</template>
