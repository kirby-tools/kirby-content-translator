<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation;

/**
 * What a content translation run did: how many units reached the strategy, how
 * many came back usable, and a rejection for each of the rest.
 */
final readonly class ContentTranslationResult
{
    /**
     * @param int $translatableCount Units handed to the strategy, i.e. everything that is not untranslatable text
     * @param list<TranslationRejection> $rejections
     */
    public function __construct(
        public int $translatableCount,
        public int $translatedCount,
        public array $rejections,
    ) {
    }
}
