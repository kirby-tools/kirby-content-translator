<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation;

/**
 * Translated texts alongside the positions that kept their source text because
 * the strategy's answer was unusable. A text `UntranslatableText` skipped is
 * absent from `$rejectedIndexes`: it never reached a strategy.
 *
 * @internal
 */
final readonly class BatchTranslationResult
{
    /**
     * @param list<string> $texts
     * @param list<int> $rejectedIndexes
     */
    public function __construct(
        public array $texts,
        public array $rejectedIndexes,
    ) {
    }
}
