export type TranslatorActionType = "import" | "translate" | "batch";

export interface TranslatorAction {
  type: TranslatorActionType;
  text: string;
  icon: string;
  disabled: boolean;
  /** Code of the language the action reads from; without one, imports copy the default language and translations let the strategy detect it. */
  from?: string;
}

export interface TranslatorActionOptions {
  languages: string[];
  /** Code of the language the view shows. */
  language: string;
  defaultLanguage: string;
  isImportEnabled: boolean;
  importFrom?: string;
  isBatchTranslationEnabled: boolean;
  hasAnyStrategy: boolean;
  isDisabled: boolean;
  t: (key: string, data?: Record<string, unknown>) => string;
}

/** Derives the actions, grouped into the imports and the translations. */
export function resolveTranslatorActions({
  languages,
  language,
  defaultLanguage,
  isImportEnabled,
  importFrom,
  isBatchTranslationEnabled,
  hasAnyStrategy,
  isDisabled,
  t,
}: TranslatorActionOptions): TranslatorAction[][] {
  const isDefaultLanguage = language === defaultLanguage;
  const translateText = t("johannschopplich.content-translator.translate", {
    language: language.toUpperCase(),
  });
  const batchActions: TranslatorAction[] =
    hasAnyStrategy && isBatchTranslationEnabled && isDefaultLanguage
      ? [
          {
            type: "batch",
            text: t("johannschopplich.content-translator.batchTranslate", {
              language: defaultLanguage.toUpperCase(),
            }),
            icon: "content-translator-global",
            disabled: isDisabled,
          },
        ]
      : [];

  if (isImportEnabled && importFrom === "all") {
    return [
      languages
        .filter((code) => code !== language)
        .map((code) => ({
          type: "import",
          text: t("johannschopplich.content-translator.importFrom", {
            language: code.toUpperCase(),
          }),
          icon: "import",
          disabled: isDisabled,
          from: code,
        })),
      [
        ...(hasAnyStrategy
          ? [
              {
                type: "translate" as const,
                text: translateText,
                icon: "translate",
                disabled: isDisabled,
              },
            ]
          : []),
        ...batchActions,
      ],
    ];
  }

  const isPerLanguage = !isBatchTranslationEnabled || !isDefaultLanguage;

  return [
    isImportEnabled && isPerLanguage
      ? [
          {
            type: "import",
            text: t("johannschopplich.content-translator.import"),
            icon: "import",
            disabled: isDefaultLanguage || isDisabled,
          },
        ]
      : [],
    [
      ...(hasAnyStrategy && isPerLanguage
        ? [
            {
              type: "translate" as const,
              text: translateText,
              icon: "translate",
              disabled: isDefaultLanguage || isDisabled,
              from: defaultLanguage,
            },
          ]
        : []),
      ...batchActions,
    ],
  ];
}

/**
 * Tells whether the field explains that only secondary languages take
 * imports and translations.
 */
export function hasDefaultLanguageInfo({
  language,
  defaultLanguage,
  isImportEnabled,
  importFrom,
  isBatchTranslationEnabled,
  hasAnyStrategy,
}: Omit<TranslatorActionOptions, "languages" | "isDisabled" | "t">) {
  return (
    !(isImportEnabled && importFrom === "all") &&
    hasAnyStrategy &&
    !isBatchTranslationEnabled &&
    language === defaultLanguage
  );
}
