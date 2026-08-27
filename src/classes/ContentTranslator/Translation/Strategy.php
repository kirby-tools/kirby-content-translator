<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation;

use JohannSchopplich\ContentTranslator\Translation\Exception\TranslationException;

/**
 * Contract for translation backends.
 */
interface Strategy
{
    /**
     * Translates units and returns results in the same order as the input.
     *
     * @param list<TranslationUnit> $units
     * @return list<string|null> `null` marks a unit the strategy could not translate; the caller keeps its source text and records a `missing translation` rejection, but leaves the `content-translator.translate:warning` hook to the strategy, which is the only layer that knows why
     *
     * @throws TranslationException When zero units could be translated
     */
    public function execute(array $units, ExecutionOptions $options): array;
}
