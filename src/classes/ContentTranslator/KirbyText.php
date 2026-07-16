<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator;

use Closure;
use Exception;
use Kirby\Cms\App;
use Kirby\Exception\LogicException;
use Kirby\Text\KirbyTag;

final class KirbyText
{
    /** @see https://github.com/getkirby/kirby/blob/main/src/Text/KirbyTags.php */
    private const KIRBY_TAGS_REGEX = '!
        (?=[^\]])               # positive lookahead that matches a group after the main expression without including ] in the result
        (?=\([a-z0-9_-]+:)      # positive lookahead that requires starts with ( and lowercase ASCII letters, digits, underscores or hyphens followed with : immediately to the right of the current location
        (\(                     # capturing group 1
            (?:[^()]+|(?1))*+   # repetitions of any chars other than ( and ) or the whole group 1 pattern (recursed)
        \))                     # end of capturing group 1
    !isx';

    /**
     * Must match `PLACEHOLDER_PATTERN` in `src/panel/translation/kirby-text.ts`.
     */
    public const PLACEHOLDER_PATTERN = '!<c(\d+)/>!';

    /**
     * Splits KirbyText prose from KirbyTags structurally.
     *
     * The first fragment is always the prose; the remaining fragments are
     * translatable attribute values in source order. Translations must be
     * passed back to `restore` in the same order.
     *
     * @param array<string, list<string>> $kirbyTags
     * @return array{fragments: list<string>, restore: Closure(list<string>): string}
     */
    public static function split(string $text, array $kirbyTags = []): array
    {
        $tagSpans = self::findKirbyTags($text);

        $proseParts = [];
        $attrValues = [];
        $tagSlots = [];

        $cursor = 0;
        foreach ($tagSpans as [$start, $end]) {
            $proseParts[] = substr($text, $cursor, $start - $cursor);
            $proseParts[] = '<c' . count($tagSlots) . '/>';

            $tag = self::parseKirbyTag(substr($text, $start, $end - $start));
            $translatableAttrs = $kirbyTags[$tag['type']] ?? [];
            $attrIndices = [];

            if (in_array('value', $translatableAttrs, true) && $tag['value'] !== null && $tag['value'] !== '') {
                $attrIndices['value'] = count($attrValues);
                $attrValues[] = $tag['value'];
            }

            foreach ($tag['attrs'] as [$name, $val]) {
                if (in_array($name, $translatableAttrs, true) && $val !== '') {
                    $attrIndices[$name] = count($attrValues);
                    $attrValues[] = $val;
                }
            }

            $tagSlots[] = ['tag' => $tag, 'attrIndices' => $attrIndices];
            $cursor = $end;
        }
        $proseParts[] = substr($text, $cursor);

        $fragments = [implode('', $proseParts), ...$attrValues];
        $expectedLength = count($fragments);

        $restore = static function (array $translatedFragments) use ($tagSlots, $expectedLength): string {
            if (count($translatedFragments) !== $expectedLength) {
                // TODO: Drop K4 compat in v4 – use named arg (message:) once Kirby 5 is the floor
                throw new LogicException(
                    'Expected ' . $expectedLength . ' translated fragments, got ' . count($translatedFragments)
                );
            }

            $translatedProse = $translatedFragments[0];
            $translatedAttrs = array_slice($translatedFragments, 1);

            return preg_replace_callback(
                self::PLACEHOLDER_PATTERN,
                static function (array $matches) use ($tagSlots, $translatedAttrs): string {
                    $slot = $tagSlots[(int)$matches[1]] ?? null;
                    if ($slot === null) {
                        return '';
                    }

                    return self::rebuildKirbyTag($slot['tag'], $slot['attrIndices'], $translatedAttrs);
                },
                $translatedProse
            );
        };

        return ['fragments' => $fragments, 'restore' => $restore];
    }

    /**
     * @deprecated v4 Will be removed. Translate via the Strategy pipeline:
     * `Translator::translateText()` already routes through it.
     */
    public static function translateText(string $text, string $targetLanguage, string|null $sourceLanguage = null, array $kirbyTags = []): string
    {
        if (trim($text) === '') {
            return '';
        }

        if ($kirbyTags !== []) {
            $text = preg_replace_callback(
                self::KIRBY_TAGS_REGEX,
                fn (array $matches) => self::translateKirbyTag($matches[0], $targetLanguage, $sourceLanguage, $kirbyTags),
                $text
            );
        }

        return self::translateWithProtectedTags($text, $targetLanguage, $sourceLanguage);
    }

    /**
     * Locates paren-balanced KirbyTag spans in `$text` and returns their byte offsets.
     *
     * @return list<array{int, int}> List of [start, endExclusive] pairs
     */
    private static function findKirbyTags(string $text): array
    {
        preg_match_all('!\([\w-]+:!', $text, $matches, PREG_OFFSET_CAPTURE);

        $spans = [];
        $lastEnd = 0;
        $length = strlen($text);

        foreach (array_column($matches[0], 1) as $start) {
            if ($start < $lastEnd) {
                continue;
            }

            $depth = 0;
            for ($i = $start; $i < $length; $i++) {
                $char = $text[$i];
                if ($char === '(') {
                    $depth++;
                } elseif ($char === ')') {
                    $depth--;
                    if ($depth === 0) {
                        $spans[] = [$start, $i + 1];
                        $lastEnd = $i + 1;
                        break;
                    }
                }
            }
        }

        return $spans;
    }

    /**
     * Parses a single KirbyTag string into type, optional value and ordered attrs.
     */
    private static function parseKirbyTag(string $rawTag): array
    {
        $body = substr($rawTag, 1, -1);
        $colonIdx = strpos($body, ':');
        if ($colonIdx === false) {
            return ['type' => strtolower(trim($body)), 'value' => null, 'attrs' => []];
        }

        $type = strtolower(trim(substr($body, 0, $colonIdx)));
        $rest = substr($body, $colonIdx + 1);

        preg_match_all('!(?:^|\s+)([a-z][\w-]*):(?=\s|$)!i', $rest, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        $boundaries = [];
        foreach ($matches as $match) {
            $boundaries[] = [
                'name' => strtolower($match[1][0]),
                'index' => $match[0][1],
                'matchEnd' => $match[0][1] + strlen($match[0][0]),
            ];
        }

        $restLength = strlen($rest);
        $valueEnd = $boundaries === [] ? $restLength : $boundaries[0]['index'];
        $rawValue = trim(substr($rest, 0, $valueEnd));
        $value = $rawValue === '' ? null : $rawValue;

        $attrs = [];
        $count = count($boundaries);
        for ($i = 0; $i < $count; $i++) {
            $current = $boundaries[$i];
            $next = $boundaries[$i + 1] ?? null;
            $start = $current['matchEnd'];
            $end = $next !== null ? $next['index'] : $restLength;
            $attrs[] = [$current['name'], trim(substr($rest, $start, $end - $start))];
        }

        return ['type' => $type, 'value' => $value, 'attrs' => $attrs];
    }

    /**
     * Reassembles a parsed tag, splicing translated attribute values into the indicated slots.
     */
    private static function rebuildKirbyTag(array $tag, array $attrIndices, array $translatedAttrs): string
    {
        $parts = [];

        $valueIndex = $attrIndices['value'] ?? null;
        $value = $valueIndex !== null ? ($translatedAttrs[$valueIndex] ?? null) : $tag['value'];
        $parts[] = ($value !== null && $value !== '') ? $tag['type'] . ': ' . $value : $tag['type'];

        foreach ($tag['attrs'] as [$name, $originalValue]) {
            $idx = $attrIndices[$name] ?? null;
            $finalValue = $idx !== null ? ($translatedAttrs[$idx] ?? null) : $originalValue;
            if ($finalValue !== null && $finalValue !== '') {
                $parts[] = $name . ': ' . $finalValue;
            }
        }

        return '(' . implode(' ', $parts) . ')';
    }

    private static function translateKirbyTag(string $tagString, string $targetLanguage, string|null $sourceLanguage, array $kirbyTags): string
    {
        $kirby = App::instance();

        try {
            $tag = KirbyTag::parse($tagString, [
                'kirby' => $kirby
            ]);

            $tagType = $tag->type();
            $translatableAttributes = $kirbyTags[$tagType] ?? null;

            if ($translatableAttributes === null) {
                return '<span translate="no">' . $tagString . '</span>';
            }

            $newAttributes = [];
            $hasTranslations = false;

            // Translate the main value if `value` is in the attributes list
            $newValue = $tag->value;
            if (in_array('value', $translatableAttributes, true) && !empty($tag->value)) {
                $newValue = Translator::translateText($tag->value, $targetLanguage, $sourceLanguage);
                $hasTranslations = true;
            }

            // Process each attribute
            foreach ($tag->attrs as $attrName => $attrValue) {
                if (in_array($attrName, $translatableAttributes, true) && !empty($attrValue)) {
                    $newAttributes[$attrName] = Translator::translateText($attrValue, $targetLanguage, $sourceLanguage);
                    $hasTranslations = true;
                } else {
                    $newAttributes[$attrName] = $attrValue;
                }
            }

            // If no translations were made, return the original
            if (!$hasTranslations) {
                return $tagString;
            }

            return self::buildKirbyTag($tagType, $newValue, $newAttributes);

        } catch (Exception $e) {
            if ($kirby->option('debug', false)) {
                throw $e;
            }

            return '<span translate="no">' . $tagString . '</span>';
        }
    }

    private static function buildKirbyTag(string $type, string|null $value, array $attributes): string
    {
        $parts = [];

        // Start with the tag type and main value
        if (!empty($value)) {
            $parts[] = $type . ': ' . $value;
        } else {
            $parts[] = $type;
        }

        // Add attributes
        foreach ($attributes as $name => $attrValue) {
            if (!empty($attrValue)) {
                $parts[] = $name . ': ' . $attrValue;
            }
        }

        return '(' . implode(' ', $parts) . ')';
    }

    private static function translateWithProtectedTags(string $text, string $targetLanguage, string|null $sourceLanguage = null): string
    {
        $protectedText = preg_replace_callback(
            self::KIRBY_TAGS_REGEX,
            fn (array $matches) => '<span translate="no">' . $matches[0] . '</span>',
            $text
        );

        $translatedText = Translator::translateText($protectedText, $targetLanguage, $sourceLanguage);

        return preg_replace(
            '!<span translate="no">(.*?)</span>!s',
            '$1',
            $translatedText
        );
    }
}
