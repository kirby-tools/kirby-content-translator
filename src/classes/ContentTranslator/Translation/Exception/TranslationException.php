<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation\Exception;

use Kirby\Exception\Exception;
use Throwable;

/**
 * Thrown when a translation strategy fails for every unit it was given.
 *
 * Per-unit failures are signalled via the `content-translator.translate:warning`
 * hook and do not raise. Strategies only throw this when zero units survive.
 */
class TranslationException extends Exception
{
    protected static string $defaultKey = 'content-translator.translation';
    protected static string $defaultFallback = 'Translation request failed';
    protected static int $defaultHttpCode = 502;

    public function __construct(
        string $strategy,
        string $reason,
        int $unitsAttempted,
        int $unitsTranslated = 0,
        Throwable|null $previous = null,
    ) {
        parent::__construct(
            message: sprintf(
                '%s strategy failed: %s (%d/%d units translated)',
                $strategy,
                $reason,
                $unitsTranslated,
                $unitsAttempted,
            ),
            details: [
                'strategy' => $strategy,
                'unitsAttempted' => $unitsAttempted,
                'unitsTranslated' => $unitsTranslated,
            ],
            previous: $previous,
        );
    }
}
