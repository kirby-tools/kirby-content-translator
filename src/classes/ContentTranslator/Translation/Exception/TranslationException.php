<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation\Exception;

use Kirby\Exception\Exception;

/**
 * Thrown when a translation strategy fails for every unit it was given.
 */
final class TranslationException extends Exception
{
    protected static string $defaultKey = 'content-translator.translation';
    protected static string $defaultFallback = 'Translation request failed';
    protected static int $defaultHttpCode = 502;

    public function __construct(
        string $strategy,
        string $reason,
        int $unitsAttempted,
        int $unitsTranslated = 0,
    ) {
        // TODO: Drop K4 compat in v4 – use named args (message:, details:) once Kirby 5 is the floor
        parent::__construct([
            'fallback' => sprintf(
                '%s strategy failed: %s (%d/%d units translated)',
                $strategy,
                $reason,
                $unitsTranslated,
                $unitsAttempted,
            ),
            'details' => [
                'strategy' => $strategy,
                'unitsAttempted' => $unitsAttempted,
                'unitsTranslated' => $unitsTranslated,
            ],
        ]);
    }
}
