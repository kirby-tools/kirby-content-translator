import type {
  CollectorOptions,
  TranslationLanguage,
  TranslationStrategy,
} from "./types";
import { collectTranslations } from "./collector";
import { translateUnits } from "./dispatch";

export * from "./strategies";
export * from "./text";

/**
 * Translates every collected unit and writes the results back into `obj`.
 *
 * Finalizers run only after all units have been applied, so a finalizer may
 * re-serialize a field that several units wrote into.
 */
export async function translateContent(
  obj: Record<string, unknown>,
  options: CollectorOptions & {
    strategy: TranslationStrategy;
    sourceLanguage?: TranslationLanguage;
    targetLanguage: TranslationLanguage;
    kirbyTags?: Record<string, string[]>;
  },
): Promise<Record<string, unknown>> {
  const {
    strategy,
    sourceLanguage,
    targetLanguage,
    kirbyTags,
    fields,
    fieldTypes,
    includeFields = [],
    excludeFields = [],
  } = options;

  const { translations, finalizers } = collectTranslations(obj, {
    fields,
    fieldTypes,
    includeFields,
    excludeFields,
    kirbyTags,
  });

  if (translations.length === 0) return obj;

  const results = await translateUnits(
    translations.map((item) => item.unit),
    strategy,
    {
      sourceLanguage,
      targetLanguage,
    },
  );

  for (const [index, { apply }] of translations.entries()) {
    apply(results[index]!);
  }

  for (const finalizer of finalizers) {
    finalizer();
  }

  return obj;
}
