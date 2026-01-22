import type {
  CollectorOptions,
  TranslationLanguage,
  TranslationStrategy,
} from "./types";
import { collectTranslations } from "./collector";

export * from "./strategies";

export async function translateContent(
  obj: Record<string, unknown>,
  options: CollectorOptions & {
    strategy: TranslationStrategy;
    sourceLanguage?: TranslationLanguage;
    targetLanguage: TranslationLanguage;
    kirbyTags: Record<string, unknown>;
    signal?: AbortSignal;
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
    signal,
  } = options;

  if (signal?.aborted) return obj;

  // Phase 1: Collect translation units and finalizers
  const { translations, finalizers } = collectTranslations(obj, {
    fields,
    fieldTypes,
    includeFields,
    excludeFields,
  });

  if (translations.length === 0) return obj;

  // Phase 2: Execute translations via strategy
  const results = await strategy.execute(
    translations.map((item) => item.unit),
    {
      sourceLanguage,
      targetLanguage,
      kirbyTags,
      signal,
    },
  );

  // Skip applying results if aborted during execution
  if (signal?.aborted) return obj;

  // Phase 3: Apply results back to content
  for (const [index, { apply }] of translations.entries()) {
    apply(results[index]!);
  }

  // Phase 4: Run finalizers (e.g., YAML serialization)
  for (const finalizer of finalizers) {
    finalizer();
  }

  return obj;
}
