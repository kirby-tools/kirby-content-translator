<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation;

/**
 * Translated texts alongside the positions no translation was applied to.
 *
 * @internal The Panel reads this through the batch API route; it is not part
 *           of the published `Translator` surface.
 */
final readonly class BatchTranslationResult
{
    /**
     * @param list<string> $texts Final text per input position, the source text wherever no translation was applied
     * @param list<int> $rejectedIndexes Positions whose translation came back unusable, in ascending order. A text `UntranslatableText` skipped is absent: it was never handed to a strategy and so cannot have failed.
     */
    public function __construct(
        public array $texts,
        public array $rejectedIndexes,
    ) {
    }
}
