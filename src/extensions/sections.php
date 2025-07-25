<?php

use JohannSchopplich\ContentTranslator\FieldResolver;
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
            // TODO: Deprecated, remove in v4
            'bulk' => fn ($bulk = null) => is_bool($bulk) ? $bulk : null,
            'batch' => fn ($batch = null) => is_bool($batch) ? $batch : null,
            'title' => fn ($title = null) => is_bool($title) ? $title : null,
            'slug' => fn ($slug = null) => is_bool($slug) ? $slug : null,
            'confirm' => fn ($confirm = null) => is_bool($confirm) ? $confirm : null,
            'fieldTypes' => function ($fieldTypes = null) {
                if (!is_array($fieldTypes)) {
                    return null;
                }

                return array_map('strtolower', $fieldTypes);
            },
            'includeFields' => function ($includeFields = null) {
                if (!is_array($includeFields)) {
                    return null;
                }

                return array_map('strtolower', $includeFields);
            },
            'excludeFields' => function ($excludeFields = null) {
                if (!is_array($excludeFields)) {
                    return null;
                }

                return array_map('strtolower', $excludeFields);
            },
            'kirbyTags' => function ($kirbyTags = null) {
                if (!is_array($kirbyTags)) {
                    return null;
                }

                return $kirbyTags;
            }
        ],
        'computed' => [
            'batch' => function () {
                return $this->batch ?? $this->bulk;
            },
            'fields' => function () {
                return FieldResolver::resolveModelFields($this->model());
            }
        ]
    ]
];
