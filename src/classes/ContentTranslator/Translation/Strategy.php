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
     * @return list<string>
     *
     * @throws TranslationException When zero units could be translated. A single unusable unit keeps its source text and travels back as a rejection, which also fires the `content-translator.translate:warning` hook
     */
    public function execute(array $units, ExecutionOptions $options): array;
}
