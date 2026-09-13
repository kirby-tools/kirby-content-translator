<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation;

/**
 * Translated texts alongside the positions that kept their source text because
 * the strategy's answer was unusable. Untranslatable text is absent from
 * `$rejections`: it never reached a strategy.
 *
 * @internal
 */
final readonly class TranslatedUnits
{
    /**
     * @param list<string> $texts
     * @param list<TranslationRejection> $rejections
     */
    public function __construct(
        public array $texts,
        public array $rejections,
        public int $translatableCount,
        public int $translatedCount,
    ) {
    }
}
