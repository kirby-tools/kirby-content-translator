import type { KirbyFieldProps } from "kirby-types";
import type { PluginContextResponse, TranslatorOptions } from "../types";
import type { TranslatorAction } from "../utils/actions";
import { usePanel } from "kirbyuse";
import { computed, ref } from "vue";
import { MODEL_FIELDS_API_ROUTE } from "../constants";
import {
  hasDefaultLanguageInfo,
  resolveTranslatorActions,
} from "../utils/actions";
import { resolveStrategyReadiness } from "../utils/strategy";
import { resolveTranslatorConfig } from "../utils/translator-config";
import { useTranslationActions } from "./actions";
import { useModel } from "./model";
import { useContentTranslator, useTranslationState } from "./translation";
import { useVariableTranslation } from "./variables";

/** Offers the actions for a model view, empty until `initialize()` resolves. */
export function useContentTranslatorActions(options: TranslatorOptions) {
  const panel = usePanel();
  const { getModelData } = useModel();
  const { isTranslating } = useTranslationState();
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

  const actionOptions = computed(() => {
    const defaultLanguage = panel.languages.find(
      (language) => language.default,
    );

    return {
      languages: panel.languages.map((language) => language.code),
      language: panel.language.code ?? "",
      defaultLanguage: defaultLanguage?.code ?? "",
      isImportEnabled: isImportEnabled.value === true,
      importFrom: importFrom.value,
      isBatchTranslationEnabled: isBatchTranslationEnabled.value === true,
      hasAnyStrategy: hasAnyStrategy.value,
      isDisabled: isTranslating.value || !isContentEditable.value,
      t: panel.t,
    };
  });

  const actions = computed(() =>
    isInitialized.value && panel.multilang
      ? resolveTranslatorActions(actionOptions.value)
      : [],
  );
  const hasInfo = computed(
    () => isInitialized.value && hasDefaultLanguageInfo(actionOptions.value),
  );

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

  function run(action: TranslatorAction) {
    const sourceLanguage = panel.languages.find(
      (language) => language.code === action.from,
    );

    if (action.type === "import") return handleImport(sourceLanguage);
    if (action.type === "translate") return handleTranslate(sourceLanguage);
    return handleBatchTranslate();
  }

  return {
    initialize,
    isInitialized,
    actions,
    hasDefaultLanguageInfo: hasInfo,
    hasAnyStrategy,
    missingStrategyMessage,
    run,
  };
}

/** Offers the variable translations for a language view, empty until `initialize()` resolves. */
export function useVariableActions(options: TranslatorOptions) {
  const panel = usePanel();
  const { isTranslating } = useTranslationState();
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

  const actions = computed(() => {
    if (!isInitialized.value) return [];

    const defaultLanguage = panel.languages.find(
      (language) => language.default,
    )!;

    return resolveTranslatorActions({
      languages: panel.languages.map((language) => language.code),
      language: viewLanguage.value.code,
      defaultLanguage: defaultLanguage.code,
      isImportEnabled: false,
      isBatchTranslationEnabled: isBatchTranslationEnabled.value,
      hasAnyStrategy: hasAnyStrategy.value,
      isDisabled: isTranslating.value || !panel.permissions.languages.update,
      t: panel.t,
    });
  });

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

  function run(action: TranslatorAction) {
    if (action.type === "batch") return batchTranslateLanguageVariables();
    return translateLanguageVariables(viewLanguage.value);
  }

  return {
    initialize,
    isInitialized,
    actions,
    missingStrategyMessage,
    run,
  };
}
