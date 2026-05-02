<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator;

use Kirby\Cms\App;

final readonly class TranslatorConfig
{
    /**
     * @param list<string> $fieldTypes
     * @param list<string> $includeFields
     * @param list<string> $excludeFields
     * @param array<string, list<string>> $kirbyTags
     */
    public function __construct(
        public array $fieldTypes,
        public array $includeFields,
        public array $excludeFields,
        public array $kirbyTags = [],
    ) {
    }

    public static function fromOptions(array $options = []): static
    {
        $kirby = App::instance();

        return new static(
            fieldTypes: array_map('strtolower', $options['fieldTypes'] ?? $kirby->option('johannschopplich.content-translator.fieldTypes', [
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
            ])),
            includeFields: array_map('strtolower', $options['includeFields'] ?? $kirby->option('johannschopplich.content-translator.includeFields', [])),
            excludeFields: array_map('strtolower', $options['excludeFields'] ?? $kirby->option('johannschopplich.content-translator.excludeFields', [])),
            kirbyTags: $options['kirbyTags'] ?? $kirby->option('johannschopplich.content-translator.kirbyTags', []),
        );
    }

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
