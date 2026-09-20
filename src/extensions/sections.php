<?php

use JohannSchopplich\KirbyTools\FieldResolver;
use Kirby\Toolkit\I18n;

return [
    'content-translator' => [
        'props' => [
            'label' => fn ($label = null) => I18n::translate($label, $label),
            'import' => fn ($import = null) => is_bool($import) ? $import : null,
            'importFrom' => function ($importFrom = null) {
                if ($importFrom === 'all') {
                    return 'all';
                }

                return $importFrom;
            },
            'batch' => fn ($batch = null) => is_bool($batch) ? $batch : null,
            'title' => fn ($title = null) => is_bool($title) ? $title : null,
            'slug' => fn ($slug = null) => is_bool($slug) ? $slug : null,
            'confirm' => fn ($confirm = null) => is_bool($confirm) ? $confirm : null,
            'fieldTypes' => fn ($fieldTypes = null) => is_array($fieldTypes) ? $fieldTypes : null,
            'includeFields' => fn ($includeFields = null) => is_array($includeFields) ? $includeFields : null,
            'excludeFields' => fn ($excludeFields = null) => is_array($excludeFields) ? $excludeFields : null,
            'kirbyTags' => function ($kirbyTags = null) {
                if (!is_array($kirbyTags)) {
                    return null;
                }

                return $kirbyTags;
            },
            'cascade' => fn ($cascade = null) => is_string($cascade) || is_array($cascade) ? $cascade : null,
            'systemPrompt' => fn ($systemPrompt = null) => is_string($systemPrompt) ? trim($systemPrompt) : null
        ],
        'computed' => [
            'fields' => function () {
                return FieldResolver::resolveModelFields($this->model());
            }
        ]
    ]
];
