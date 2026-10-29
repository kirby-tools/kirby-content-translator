import type { KirbyFieldProps } from "kirby-types";
import type { PluginContextResponse, TranslatorOptions } from "../types";
import type { TranslatorAction } from "../utils/actions";
import { usePanel } from "kirbyuse";
import { computed, ref } from "vue";
import { MODEL_FIELDS_API_ROUTE } from "../constants";
import { resolveTranslatorActions } from "../utils/actions";
import { resolveStrategyReadiness } from "../utils/strategy";
import { resolveTranslatorConfig } from "../utils/translator-config";
import { useTranslationHandlers } from "./handlers";
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
    useTranslationHandlers(translator);

  const actionOptions = computed(() => {
    const defaultLanguage = panel.languages.find(
      (language) => language.default,
    );

    return {
      languageCodes: panel.languages.map((language) => language.code),
      viewLanguageCode: panel.language.code ?? "",
      defaultLanguageCode: defaultLanguage?.code ?? "",
      isImportEnabled: isImportEnabled.value === true,
      importFrom: importFrom.value,
      isBatchTranslationEnabled: isBatchTranslationEnabled.value === true,
      hasAnyStrategy: hasAnyStrategy.value,
      isDisabled: isTranslating.value || !isContentEditable.value,
      t: panel.t,
    };
  });

  const resolution = computed(() =>
    isInitialized.value && panel.multilang
      ? resolveTranslatorActions(actionOptions.value)
      : undefined,
  );
  const actions = computed(() => resolution.value?.groups ?? []);
  const hasDefaultLanguageInfo = computed(
    () => resolution.value?.hasDefaultLanguageInfo ?? false,
  );

  async function initialize(context: PluginContextResponse) {
    await Promise.all([initializeConfig(context, options), initializeFields()]);
    isInitialized.value = true;
  }

  // Fetched rather than computed by the PHP `ContentTranslatorField` class: its
  // getter would build the form that holds the field and recurse.
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
      (language) => language.code === action.sourceLanguageCode,
    );

    if (action.type === "import") return handleImport(sourceLanguage);
    if (action.type === "translate") return handleTranslate(sourceLanguage);
    return handleBatchTranslate();
  }

  return {
    initialize,
    isInitialized,
    actions,
    hasDefaultLanguageInfo,
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
      languageCodes: panel.languages.map((language) => language.code),
      viewLanguageCode: viewLanguage.value.code,
      defaultLanguageCode: defaultLanguage.code,
      isImportEnabled: false,
      isBatchTranslationEnabled: isBatchTranslationEnabled.value,
      hasAnyStrategy: hasAnyStrategy.value,
      isDisabled: isTranslating.value || !panel.permissions.languages.update,
      t: panel.t,
    }).groups;
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
    actions,
    missingStrategyMessage,
    run,
  };
}
