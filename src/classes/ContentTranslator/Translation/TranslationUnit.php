<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation;

/**
 * A single piece of text to translate, paired with a dispatch hint.
 */
final readonly class TranslationUnit
{
    public function __construct(
        public string $text,
        public TranslationMode $mode = TranslationMode::Batch,
        public string|null $fieldKey = null,
    ) {
    }
}
