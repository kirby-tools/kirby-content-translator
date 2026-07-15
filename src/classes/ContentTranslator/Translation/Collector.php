<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation;

use Closure;
use JohannSchopplich\ContentTranslator\KirbyText;
use JohannSchopplich\ContentTranslator\TranslatorConfig;
use Kirby\Data\Data;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;
use Throwable;

/**
 * Walks a content array and emits translatable units plus
 * post-translation finalisers.
 *
 * Closures capture `&$node` – callers must keep the same array reference
 * live between `collect()` and `writeBack` invocations.
 *
 * @internal
 */
final class Collector
{
    /**
     * @var list<CollectedTranslation>
     */
    private array $translations = [];

    /**
     * @var list<Closure(): void>
     */
    private array $finalizers = [];

    /**
     * @param array<string, array<string, mixed>> $fields
     */
    public function __construct(
        private readonly array $fields,
        private readonly TranslatorConfig $config,
    ) {
    }

    /**
     * @param array<string, mixed> $node
     */
    public function collect(array &$node): CollectorResult
    {
        $this->translations = [];
        $this->finalizers = [];

        $fields = $this->fields;

        if ($this->config->includeFields !== [] || $this->config->excludeFields !== []) {
            $fields = array_filter(
                $fields,
                function (string $fieldName): bool {
                    if ($this->config->includeFields !== [] && !in_array($fieldName, $this->config->includeFields, true)) {
                        return false;
                    }
                    if (in_array($fieldName, $this->config->excludeFields, true)) {
                        return false;
                    }
                    return true;
                },
                ARRAY_FILTER_USE_KEY,
            );
        }

        $this->collectFromObject($node, $fields);

        return new CollectorResult(
            translations: $this->translations,
            finalizers: $this->finalizers,
        );
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, array<string, mixed>> $fields
     */
    private function collectFromObject(array &$node, array $fields): void
    {
        foreach ($node as $fieldName => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            if (!isset($fields[$fieldName])) {
                continue;
            }
            if (($fields[$fieldName]['translate'] ?? true) === false) {
                continue;
            }
            if (!in_array($fields[$fieldName]['type'], $this->config->fieldTypes, true)) {
                continue;
            }

            $this->collectFromField($node, $fieldName, $value, $fields[$fieldName]);
        }
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $field
     */
    private function collectFromField(array &$node, string $fieldName, mixed $value, array $field): void
    {
        $fieldType = $field['type'];

        if (in_array($fieldType, ['list', 'text', 'writer'], true)) {
            $text = (string)$value;
            if ($text === '' || TextFilter::shouldSkip($text)) {
                return;
            }

            $this->translations[] = new CollectedTranslation(
                unit: new TranslationUnit(
                    text: $text,
                    fieldKey: $fieldName,
                ),
                writeBack: function (string $translation) use (&$node, $fieldName): void {
                    $node[$fieldName] = $translation;
                },
            );

            return;
        }

        if (in_array($fieldType, ['textarea', 'markdown'], true)) {
            $text = (string)$value;
            if (TextFilter::shouldSkip($text)) {
                return;
            }

            ['fragments' => $fragments, 'restore' => $restore] = KirbyText::split($text, $this->config->kirbyTags);
            $translatedFragments = array_fill(0, count($fragments), '');

            foreach ($fragments as $fragmentIndex => $fragment) {
                $this->translations[] = new CollectedTranslation(
                    unit: new TranslationUnit(
                        text: $fragment,
                        fieldKey: $fieldName,
                    ),
                    writeBack: function (string $translation) use (&$translatedFragments, $fragmentIndex): void {
                        $translatedFragments[$fragmentIndex] = $translation;
                    },
                );
            }

            $this->finalizers[] = function () use (&$node, $fieldName, $restore, &$translatedFragments): void {
                $node[$fieldName] = $restore($translatedFragments);
            };

            return;
        }

        if ($fieldType === 'tags') {
            if (!is_string($value) || TextFilter::shouldSkip($value)) {
                return;
            }

            $items = Str::split($value, ',');
            if ($items === []) {
                return;
            }

            $this->translations[] = new CollectedTranslation(
                unit: new TranslationUnit(
                    text: implode(' | ', $items),
                    fieldKey: $fieldName,
                ),
                writeBack: function (string $translation) use (&$node, $fieldName): void {
                    $node[$fieldName] = implode(', ', array_map('trim', explode('|', $translation)));
                },
            );

            return;
        }

        if ($fieldType === 'table') {
            $shouldReencode = is_string($value);
            if ($shouldReencode) {
                try {
                    $node[$fieldName] = Data::decode($value, 'yaml');
                } catch (Throwable) {
                    // Tolerate malformed third-party YAML
                    return;
                }
            }
            if (!is_array($node[$fieldName])) {
                return;
            }

            foreach ($node[$fieldName] as $rowIndex => $row) {
                if (!is_array($row)) {
                    continue;
                }
                foreach ($row as $colIndex => $cell) {
                    if (!is_string($cell) || trim($cell) === '') {
                        continue;
                    }
                    $this->translations[] = new CollectedTranslation(
                        unit: new TranslationUnit(
                            text: $cell,
                            fieldKey: $fieldName . '[' . $rowIndex . '][' . $colIndex . ']',
                        ),
                        writeBack: function (string $translation) use (&$node, $fieldName, $rowIndex, $colIndex): void {
                            $node[$fieldName][$rowIndex][$colIndex] = $translation;
                        },
                    );
                }
            }

            if ($shouldReencode) {
                $this->queueEncode($node, $fieldName, 'yaml');
            }

            return;
        }

        if ($fieldType === 'structure') {
            $shouldReencode = is_string($value);
            if ($shouldReencode) {
                $node[$fieldName] = Data::decode($value, 'yaml');
            }
            if (!is_array($node[$fieldName])) {
                return;
            }

            foreach ($node[$fieldName] as &$item) {
                if (!is_array($item)) {
                    continue;
                }
                $this->collectFromObject($item, $field['fields'] ?? []);
            }

            unset($item);

            if ($shouldReencode) {
                $this->queueEncode($node, $fieldName, 'yaml');
            }

            return;
        }

        if ($fieldType === 'object') {
            $shouldReencode = is_string($value);
            if ($shouldReencode) {
                $node[$fieldName] = Data::decode($value, 'yaml');
            }
            if (!is_array($node[$fieldName]) || !A::isAssociative($node[$fieldName])) {
                return;
            }

            $this->collectFromObject($node[$fieldName], $field['fields'] ?? []);

            if ($shouldReencode) {
                $this->queueEncode($node, $fieldName, 'yaml');
            }

            return;
        }

        if ($fieldType === 'blocks') {
            $shouldReencode = is_string($value);
            if ($shouldReencode) {
                $node[$fieldName] = Data::decode($value, 'json');
            }
            if (!is_array($node[$fieldName])) {
                return;
            }

            $fieldsets = $field['fieldsets'] ?? [];

            foreach ($node[$fieldName] as &$block) {
                if (!self::isBlockTranslatable($block)) {
                    continue;
                }
                if (!isset($fieldsets[$block['type']])) {
                    continue;
                }
                $blockFields = self::flattenTabFields($fieldsets, $block);
                $this->collectFromObject($block['content'], $blockFields);
            }

            unset($block);

            if ($shouldReencode) {
                $this->queueEncode($node, $fieldName, 'json');
            }

            return;
        }

        if ($fieldType === 'layout') {
            $shouldReencode = is_string($value);
            if ($shouldReencode) {
                $node[$fieldName] = Data::decode($value, 'json');
            }
            if (!is_array($node[$fieldName])) {
                return;
            }

            $fieldsets = $field['fieldsets'] ?? [];
            foreach (array_keys($node[$fieldName]) as $layoutIndex) {
                foreach (array_keys($node[$fieldName][$layoutIndex]['columns'] ?? []) as $columnIndex) {
                    foreach (array_keys($node[$fieldName][$layoutIndex]['columns'][$columnIndex]['blocks'] ?? []) as $blockIndex) {
                        $block = $node[$fieldName][$layoutIndex]['columns'][$columnIndex]['blocks'][$blockIndex];
                        if (!self::isBlockTranslatable($block)) {
                            continue;
                        }
                        if (!isset($fieldsets[$block['type']])) {
                            continue;
                        }
                        $blockFields = self::flattenTabFields($fieldsets, $block);
                        $this->collectFromObject(
                            $node[$fieldName][$layoutIndex]['columns'][$columnIndex]['blocks'][$blockIndex]['content'],
                            $blockFields,
                        );
                    }
                }
            }

            if ($shouldReencode) {
                $this->queueEncode($node, $fieldName, 'json');
            }
        }
    }

    /**
     * @param array<string, mixed> $node
     */
    private function queueEncode(array &$node, string $fieldName, string $format): void
    {
        $this->finalizers[] = function () use (&$node, $fieldName, $format): void {
            $node[$fieldName] = Data::encode($node[$fieldName], $format);
        };
    }

    private static function isBlockTranslatable(mixed $block): bool
    {
        return is_array($block) &&
            isset($block['content']) &&
            is_array($block['content']) &&
            A::isAssociative($block['content']) &&
            isset($block['id']) &&
            ($block['isHidden'] ?? false) !== true;
    }

    /**
     * @param array<string, array<string, mixed>> $fieldsets
     * @param array<string, mixed> $block
     * @return array<string, array<string, mixed>>
     */
    private static function flattenTabFields(array $fieldsets, array $block): array
    {
        $blockFields = [];
        $tabs = $fieldsets[$block['type']]['tabs'] ?? [];

        foreach ($tabs as $tab) {
            $blockFields = array_merge($blockFields, $tab['fields'] ?? []);
        }

        return $blockFields;
    }

}
