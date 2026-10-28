type Translate = (key: string, data?: Record<string, unknown>) => string;

const AI_PROVIDER_CONFIG: Record<string, { labelKey: string; icon: string }> = {
  openai: {
    labelKey: "johannschopplich.content-translator.provider.openai",
    icon: "content-translator-openai",
  },
  anthropic: {
    labelKey: "johannschopplich.content-translator.provider.anthropic",
    icon: "content-translator-anthropic",
  },
  google: {
    labelKey: "johannschopplich.content-translator.provider.google",
    icon: "content-translator-google",
  },
  mistral: {
    labelKey: "johannschopplich.content-translator.provider.mistral",
    icon: "content-translator-mistral",
  },
};

export function createStrategyField({
  isCustomStrategy,
  aiProvider,
  t,
}: {
  /** The `strategy` option names a custom strategy in place of DeepL. */
  isCustomStrategy: boolean;
  aiProvider?: string;
  t: Translate;
}) {
  const aiProviderConfig = aiProvider
    ? AI_PROVIDER_CONFIG[aiProvider]
    : undefined;

  return {
    type: "toggles",
    label: t("johannschopplich.content-translator.dialog.strategyLabel"),
    labels: true,
    grow: true,
    options: [
      {
        value: "deepl",
        text: t(
          isCustomStrategy
            ? "johannschopplich.content-translator.strategy.custom"
            : "johannschopplich.content-translator.strategy.deepl",
        ),
        icon: "translate",
      },
      {
        value: "ai",
        text: aiProviderConfig
          ? t(aiProviderConfig.labelKey)
          : t("johannschopplich.content-translator.strategy.copilot"),
        // Fallback: https://getkirby.com/docs/reference/panel/icons/ai
        icon: aiProviderConfig?.icon ?? "ai",
      },
    ],
  };
}

/** Builds the fields of the translation dialog, or with `translationLanguages` those of the batch dialog. */
export function createTranslationDialogFields({
  help,
  strategyField,
  translationLanguages,
  t,
}: {
  help?: string;
  strategyField?: ReturnType<typeof createStrategyField>;
  translationLanguages?: { code: string; name: string }[];
  t: Translate;
}) {
  return {
    ...(translationLanguages
      ? {
          languages: {
            type: "checkboxes",
            label: t("johannschopplich.content-translator.translateTo"),
            options: translationLanguages.map((language) => ({
              value: language.code,
              text: language.name,
            })),
            help,
          },
        }
      : help && { help: { type: "info", text: help } }),
    ...(strategyField && { strategyName: strategyField }),
  };
}
