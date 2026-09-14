<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation;

/**
 * A unit that reached a strategy and came back unusable, with the check that
 * turned it down.
 */
final readonly class TranslationRejection
{
    /**
     * @param int $index Position of the unit in the run
     * @param string|null $fieldKey Field the unit came from (e.g. `text`, `blocks.text`), where the caller collected one
     * @param list<int>|null $expectedIndexes KirbyTag placeholder indexes the source text carries, set for a `placeholder mismatch`
     * @param list<int>|null $actualIndexes KirbyTag placeholder indexes the answer carried instead
     */
    public function __construct(
        public int $index,
        public string $reason,
        public string|null $fieldKey = null,
        public array|null $expectedIndexes = null,
        public array|null $actualIndexes = null,
    ) {
    }
}
