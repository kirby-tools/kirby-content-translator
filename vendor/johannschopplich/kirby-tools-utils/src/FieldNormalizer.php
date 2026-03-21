<?php

declare(strict_types = 1);

namespace JohannSchopplich\KirbyTools;

use Kirby\Form\Field;

/**
 * Normalizes Kirby field definitions: resolves custom types to their
 * standard base types and recurses into nested fields and fieldsets.
 */
final class FieldNormalizer
{
    private const SUPPORTED_TYPES = [
        'blocks', 'checkboxes', 'color', 'date', 'email', 'entries', 'files',
        'gap', 'headline', 'hidden', 'info', 'layout', 'line', 'link', 'list',
        'markdown', 'multiselect', 'number', 'object', 'pages', 'password',
        'radio', 'range', 'select', 'slug', 'stats', 'structure', 'tags', 'tel', 'text',
        'textarea', 'time', 'toggle', 'toggles', 'url', 'users', 'writer',
    ];

    private const MAX_DEPTH = 10;

    private static array|null $supportedTypesMap = null;

    /**
     * Resolves a custom field type to its standard base type
     * by following the `extends` chain.
     */
    public static function resolveBaseType(string $type, int $depth = 0): string
    {
        static::$supportedTypesMap ??= array_flip(static::SUPPORTED_TYPES);

        if (isset(static::$supportedTypesMap[$type]) || $depth >= static::MAX_DEPTH) {
            return $type;
        }

        try {
            $definition = Field::load($type);
        } catch (\Throwable) {
            return $type;
        }

        $extends = $definition['extends'] ?? null;

        if (!is_string($extends) || $extends === '' || $extends === $type) {
            return $type;
        }

        return static::resolveBaseType($extends, $depth + 1);
    }

    /**
     * Normalizes a fields array by resolving custom types and
     * recursing into nested `fields` and `fieldsets[*].tabs[*].fields`.
     */
    public static function normalizeFields(array $fields): array
    {
        foreach ($fields as &$field) {
            if (isset($field['type'])) {
                $field['type'] = static::resolveBaseType($field['type']);
            }

            // Recurse into nested fields (structure, object)
            if (isset($field['fields']) && is_array($field['fields'])) {
                $field['fields'] = static::normalizeFields($field['fields']);
            }

            // Recurse into block/layout fieldsets
            if (isset($field['fieldsets']) && is_array($field['fieldsets'])) {
                foreach ($field['fieldsets'] as &$fieldset) {
                    if (isset($fieldset['tabs']) && is_array($fieldset['tabs'])) {
                        foreach ($fieldset['tabs'] as &$tab) {
                            if (isset($tab['fields']) && is_array($tab['fields'])) {
                                $tab['fields'] = static::normalizeFields($tab['fields']);
                            }
                        }
                    }
                }
            }
        }

        return $fields;
    }
}
