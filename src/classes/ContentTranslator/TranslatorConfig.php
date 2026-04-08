<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator;

use Kirby\Cms\App;

final readonly class TranslatorConfig
{
    public function __construct(
        public array $fieldTypes,
        public array $includeFields,
        public array $excludeFields,
    ) {
    }

    public static function fromOptions(array $options = []): static
    {
        $config = App::instance()->option('johannschopplich.content-translator', []);

        return new static(
            fieldTypes: array_map('strtolower', $options['fieldTypes'] ?? $config['fieldTypes'] ?? [
                'blocks',
                'layout',
                'list',
                'object',
                'structure',
                'tags',
                'text',
                'textarea',
                'writer',
                // Community plugins
                'markdown',
                'table'
            ]),
            includeFields: array_map('strtolower', $options['includeFields'] ?? $config['includeFields'] ?? []),
            excludeFields: array_map('strtolower', $options['excludeFields'] ?? $config['excludeFields'] ?? []),
        );
    }

    /**
     * Check if a top-level field is translatable based on its type,
     * include/exclude lists, and the field's `translate` property.
     */
    public function isTranslatable(string $key, array $props): bool
    {
        if (!in_array($props['type'], $this->fieldTypes, true)) {
            return false;
        }

        if ($this->includeFields !== [] && !in_array($key, $this->includeFields, true)) {
            return false;
        }

        if ($this->excludeFields !== [] && in_array($key, $this->excludeFields, true)) {
            return false;
        }

        if (($props['translate'] ?? true) === false) {
            return false;
        }

        return true;
    }
}
