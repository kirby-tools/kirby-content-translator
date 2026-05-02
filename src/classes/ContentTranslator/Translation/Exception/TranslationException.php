<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation\Exception;

use Kirby\Exception\Exception;

/**
 * Thrown when a translation strategy fails for every unit it was given.
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
        );
    }
}
