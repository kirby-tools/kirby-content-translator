<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation;

/**
 * Per-execution options passed to a {@see Strategy}.
 */
final readonly class ExecutionOptions
{
    public function __construct(
        public TranslationLanguage $targetLanguage,
        public TranslationLanguage|null $sourceLanguage = null,
    ) {
    }
}
