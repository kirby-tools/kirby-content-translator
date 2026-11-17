<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation;

enum TranslationRejectionReason: string
{
    case MissingTranslation = 'missing translation';
    case NonStringTranslation = 'non-string translation';
    case EmptyTranslation = 'empty translation';
    case PlaceholderMismatch = 'placeholder mismatch';
    case ResponseLengthMismatch = 'response length mismatch';
    /** The hook carries the provider's exception in `previous`. */
    case ProviderError = 'provider error';
}
