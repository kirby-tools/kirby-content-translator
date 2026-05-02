<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation;

/**
 * Dispatch hint for a {@see TranslationUnit}.
 */
enum TranslationMode: string
{
    case Batch = 'batch';
    case Single = 'single';
}
