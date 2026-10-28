import type { KirbyFieldProps } from "kirby-types";
import type { PluginContextResponse, TranslatorOptions } from "../types";
import { usePanel } from "kirbyuse";
import { computed, ref } from "vue";
import { MODEL_FIELDS_API_ROUTE } from "../constants";
import { resolveStrategyReadiness } from "../utils/strategy";
import { resolveTranslatorConfig } from "../utils/translator-config";
import { useTranslationActions } from "./actions";
import { useModel } from "./model";
import { useContentTranslator } from "./translation";
import { useVariableTranslation } from "./variables";

/** Offers the actions for a model view, off until `initialize()` resolves. */
export function useContentTranslatorActions(options: TranslatorOptions) {
  const panel = usePanel();
  const { getModelData } = useModel();
  const translator = useContentTranslator();
  const {
    isImportEnabled,
    importFrom,
    isBatchTranslationEnabled,
    fields,
    hasAnyStrategy,
    missingStrategyMessage,
    isContentEditable,
    initializeConfig,
  } = translator;

  const isInitialized = ref(false);

  const { handleImport, handleTranslate, handleBatchTranslate } =
    useTranslationActions(translator);

  async function initialize(context: PluginContextResponse) {
    await Promise.all([initializeConfig(context, options), initializeFields()]);
    isInitialized.value = true;
  }

  // Not resolved by the field class: a getter that resolves the form recurses.
  async function initializeFields() {
    const { id } = await getModelData(panel.view.path);
    fields.value = await panel.api.get<Record<string, KirbyFieldProps>>(
      MODEL_FIELDS_API_ROUTE,
      { id: id ?? "site" },
      undefined,
      // Avoid showing Panel loading indicator.
      true,
    );
  }

  return {
    initialize,
    isInitialized,
    isImportEnabled,
    importFrom,
    isBatchTranslationEnabled,
    hasAnyStrategy,
    missingStrategyMessage,
    isContentEditable,
    handleImport,
    handleTranslate,
    handleBatchTranslate,
  };
}

/** Offers the variable translations for a language view, off until `initialize()` resolves. */
export function useVariableActions(options: TranslatorOptions) {
  const panel = usePanel();
  const { translateLanguageVariables, batchTranslateLanguageVariables } =
    useVariableTranslation(options);

  const isBatchTranslationEnabled = ref(false);
  const hasAnyStrategy = ref(false);
  const missingStrategyMessage = ref<string>();
  const isInitialized = ref(false);

  // The language the view shows; `panel.language` is the content language of
  // the header's switch.
  const viewLanguage = computed(() =>
    panel.languages.find((language) => language.code === panel.view.props.id)!,
  );

  async function initialize(context: PluginContextResponse) {
    isBatchTranslationEnabled.value = resolveTranslatorConfig(
      context.config,
      options,
    ).isBatchTranslationEnabled;
    const strategyReadiness = await resolveStrategyReadiness(context.config);
    hasAnyStrategy.value = strategyReadiness.hasAnyStrategy;
    missingStrategyMessage.value = strategyReadiness.missingStrategyMessage;
    isInitialized.value = true;
  }

  return {
    initialize,
    isInitialized,
    viewLanguage,
    isBatchTranslationEnabled,
    hasAnyStrategy,
    missingStrategyMessage,
    translateLanguageVariables,
    batchTranslateLanguageVariables,
  };
}
