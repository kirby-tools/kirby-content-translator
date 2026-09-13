<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation;

use JohannSchopplich\ContentTranslator\KirbyText;

/**
 * Text a translation provider would only corrupt: it either holds no language
 * at all, or holds a value – a price, a URL – that a translator will happily
 * localize into something broken.
 *
 * Structural emptiness ("this field has no content") is a separate question,
 * answered by the callers that walk the content.
 *
 * @internal
 */
final class UntranslatableText
{
    public static function matches(string $text): bool
    {
        $trimmedText = self::trim($text);

        if ($trimmedText === '') {
            return true;
        }

        if (is_numeric($trimmedText)) {
            return true;
        }

        if (preg_match('/^https?:\/\/\S+$/i', $trimmedText)) {
            return true;
        }

        // A textarea holding nothing but KirbyTags splits into prose that is
        // only KirbyTag placeholders – there is no language in it to translate.
        if (self::trim(preg_replace(KirbyText::PLACEHOLDER_PATTERN, '', $trimmedText) ?? $trimmedText) === '') {
            return true;
        }

        return false;
    }

    /**
     * Reports blankness by the Unicode-aware `trim()`, not PHP's.
     */
    public static function isBlank(string $text): bool
    {
        return self::trim($text) === '';
    }

    /**
     * Mirrors JavaScript's `String.prototype.trim` so both pipelines agree on
     * what is blank: PHP's `trim` leaves Unicode whitespace and the BOM in
     * place – both of which editors paste in – and strips NUL, which
     * JavaScript keeps. The class is spelled out because `\s` under the `u`
     * modifier also matches U+0085 and U+180E, which JavaScript preserves.
     *
     * Invalid UTF-8 makes `preg_replace` bail; the untrimmed text then falls
     * through to the provider rather than being silently treated as blank.
     */
    private static function trim(string $text): string
    {
        $whitespace = '\x{0009}-\x{000D}\x{0020}\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}';

        return preg_replace('/^[' . $whitespace . ']+|[' . $whitespace . ']+$/u', '', $text) ?? $text;
    }
}
