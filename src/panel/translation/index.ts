import type {
  CollectorOptions,
  ContentTranslationResult,
  TranslationLanguage,
  TranslationStrategy,
} from "./types";
import { collectTranslations } from "./collector";
import { translateUnits } from "./dispatch";

export * from "./strategies";
export * from "./text";

/**
 * Translates every collected unit, writes the results back into `obj` and
 * reports how much of it was translated.
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
): Promise<ContentTranslationResult> {
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

  if (translations.length === 0) {
    return { translatableCount: 0, translatedCount: 0, rejections: [] };
  }

  const { texts, translatableCount, translatedCount, rejections } = await translateUnits(
    translations.map((item) => item.unit),
    strategy,
    {
      sourceLanguage,
      targetLanguage,
    },
  );

  for (const [index, { apply }] of translations.entries()) {
    apply(texts[index]!);
  }

  for (const finalizer of finalizers) {
    finalizer();
  }

  return { translatableCount, translatedCount, rejections };
}
