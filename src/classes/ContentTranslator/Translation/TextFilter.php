<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation;

/**
 * Predicates for filtering text values before translation.
 *
 * @internal
 */
final class TextFilter
{
    /**
     * Returns `true` for empty, numeric, or bare-URL strings.
     */
    public static function shouldSkip(string $text): bool
    {
        $trimmedText = trim($text);

        if ($trimmedText === '') {
            return true;
        }

        if (is_numeric($trimmedText)) {
            return true;
        }

        if (preg_match('/^https?:\/\/\S+$/i', $trimmedText)) {
            return true;
        }

        return false;
    }
}
