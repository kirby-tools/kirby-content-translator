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
     * Translate units and return results in the same order as the input.
     *
     * @param list<TranslationUnit> $units
     * @return list<string>
     *
     * @throws TranslationException When zero units could be translated.
     *                              Per-unit failures keep the source text and only log a warning.
     */
    public function execute(array $units, ExecutionOptions $options): array;
}
