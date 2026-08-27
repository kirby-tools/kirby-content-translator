<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation;

/**
 * Translated texts alongside the positions that kept their source text because
 * the strategy's answer was unusable. A text `UntranslatableText` skipped is
 * absent from `$rejections`: it never reached a strategy.
 *
 * @internal
 */
final readonly class BatchTranslationResult
{
    /**
     * @param list<string> $texts
     * @param list<TranslationRejection> $rejections
     * @param int $translatableCount Units handed to the strategy, i.e. everything `UntranslatableText` did not skip
     */
    public function __construct(
        public array $texts,
        public array $rejections,
        public int $translatableCount,
        public int $translatedCount,
    ) {
    }
}
