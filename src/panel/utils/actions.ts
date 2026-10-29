import type { Translate } from "../types";

export type TranslatorActionType = "import" | "translate" | "batch";

export interface TranslatorAction {
  type: TranslatorActionType;
  text: string;
  icon: string;
  isDisabled: boolean;
  /** Language the action reads from; without one, an import or a batch reads the default language and a translation lets the strategy detect it. */
  sourceLanguageCode?: string;
}

export interface TranslatorActionOptions {
  languageCodes: string[];
  viewLanguageCode: string;
  defaultLanguageCode: string;
  isImportEnabled: boolean;
  importFrom?: string;
  isBatchTranslationEnabled: boolean;
  hasAnyStrategy: boolean;
  isDisabled: boolean;
  t: Translate;
}

export function resolveTranslatorActions({
  languageCodes,
  viewLanguageCode,
  defaultLanguageCode,
  isImportEnabled,
  importFrom,
  isBatchTranslationEnabled,
  hasAnyStrategy,
  isDisabled,
  t,
}: TranslatorActionOptions): {
  groups: [imports: TranslatorAction[], translations: TranslatorAction[]];
  hasDefaultLanguageInfo: boolean;
} {
  const isDefaultLanguage = viewLanguageCode === defaultLanguageCode;
  const translateText = t("johannschopplich.content-translator.translate", {
    language: viewLanguageCode.toUpperCase(),
  });
  const batchActions: TranslatorAction[] =
    hasAnyStrategy && isBatchTranslationEnabled && isDefaultLanguage
      ? [
          {
            type: "batch",
            text: t("johannschopplich.content-translator.batchTranslate", {
              language: defaultLanguageCode.toUpperCase(),
            }),
            icon: "content-translator-global",
            isDisabled,
          },
        ]
      : [];

  if (isImportEnabled && importFrom === "all") {
    return {
      groups: [
        languageCodes
          .filter((code) => code !== viewLanguageCode)
          .map((code) => ({
            type: "import",
            text: t("johannschopplich.content-translator.importFrom", {
              language: code.toUpperCase(),
            }),
            icon: "import",
            isDisabled,
            sourceLanguageCode: code,
          })),
        [
          ...(hasAnyStrategy
            ? [
                {
                  type: "translate" as const,
                  text: translateText,
                  icon: "translate",
                  isDisabled,
                },
              ]
            : []),
          ...batchActions,
        ],
      ],
      hasDefaultLanguageInfo: false,
    };
  }

  const hasSingleLanguageActions =
    !isBatchTranslationEnabled || !isDefaultLanguage;

  return {
    groups: [
      isImportEnabled && hasSingleLanguageActions
        ? [
            {
              type: "import",
              text: t("johannschopplich.content-translator.import"),
              icon: "import",
              isDisabled: isDefaultLanguage || isDisabled,
            },
          ]
        : [],
      [
        ...(hasAnyStrategy && hasSingleLanguageActions
          ? [
              {
                type: "translate" as const,
                text: translateText,
                icon: "translate",
                isDisabled: isDefaultLanguage || isDisabled,
                sourceLanguageCode: defaultLanguageCode,
              },
            ]
          : []),
        ...batchActions,
      ],
    ],
    hasDefaultLanguageInfo:
      hasAnyStrategy && !isBatchTranslationEnabled && isDefaultLanguage,
  };
}
