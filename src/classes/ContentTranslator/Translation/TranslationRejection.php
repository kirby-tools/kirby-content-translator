<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation;

/**
 * A unit that reached a strategy and came back unusable, with the check that
 * turned it down.
 *
 * @internal
 */
final readonly class TranslationRejection
{
    /**
     * @param int $index Position in the texts handed to `translateBatch()`
     */
    public function __construct(
        public int $index,
        public string $reason,
    ) {
    }
}
