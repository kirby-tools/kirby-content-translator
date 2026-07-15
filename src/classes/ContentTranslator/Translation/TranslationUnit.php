<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation;

/**
 * A single piece of text to translate.
 */
final readonly class TranslationUnit
{
    public function __construct(
        public string $text,
        public string|null $fieldKey = null,
    ) {
    }
}
