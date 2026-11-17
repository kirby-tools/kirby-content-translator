<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation;

use JohannSchopplich\ContentTranslator\Translation\Exception\TranslationException;

interface Strategy
{
    /**
     * Translates units and returns results in the same order as the input.
     *
     * A `null` result marks a unit the strategy could not translate. The caller
     * keeps its source text and records a `MissingTranslation` rejection, but
     * leaves the `content-translator.translate:warning` hook to the strategy,
     * which fires it with a `TranslationRejectionReason` (`ProviderError` for a
     * caught exception).
     *
     * @param list<TranslationUnit> $units
     * @return list<string|null>
     *
     * @throws TranslationException When zero units could be translated
     */
    public function execute(array $units, ExecutionOptions $options): array;
}
