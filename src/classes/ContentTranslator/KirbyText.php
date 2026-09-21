<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator;

use Closure;
use Kirby\Exception\LogicException;

final class KirbyText
{
    /**
     * Must match `PLACEHOLDER_PATTERN` in `src/panel/translation/kirby-text.ts`
     * for ASCII whitespace. Matching the wider `\s` JavaScript accepts needs the
     * `u` modifier, under which PCRE returns `false` on invalid UTF-8.
     */
    public const PLACEHOLDER_PATTERN = '!<c(\d+)\s*/>!';

    /**
     * Splits KirbyText prose from KirbyTags structurally.
     *
     * The first unit text is always the prose; the remaining ones are
     * translatable attribute values in source order. Translations must be
     * passed back to `restore` in the same order.
     *
     * @param array<string, list<string>> $kirbyTags
     * @return array{unitTexts: list<string>, restore: Closure(list<string>): string}
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

        $unitTexts = [implode('', $proseParts), ...$attrValues];
        $expectedLength = count($unitTexts);

        $restore = static function (array $translatedUnitTexts) use ($tagSlots, $expectedLength): string {
            if (count($translatedUnitTexts) !== $expectedLength) {
                // TODO: Drop K4 compat in v4 – use the named argument `message:` once Kirby 5 is the floor.
                throw new LogicException(
                    'Expected ' . $expectedLength . ' translations, got ' . count($translatedUnitTexts)
                );
            }

            $translatedProse = $translatedUnitTexts[0];
            $translatedAttrs = array_slice($translatedUnitTexts, 1);

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

        return ['unitTexts' => $unitTexts, 'restore' => $restore];
    }

    /**
     * Locates paren-balanced KirbyTag spans in `$text`.
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
}
