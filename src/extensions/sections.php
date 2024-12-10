<?php

use JohannSchopplich\ContentTranslator\Translator;
use JohannSchopplich\Licensing\Licenses;
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
            'bulk' => fn ($bulk = null) => is_bool($bulk) ? $bulk : null,
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
            }
        ],
        'computed' => [
            'slug' => function () {
                /** @var \Kirby\Cms\Page */
                $model = $this->model();

                if ($model::CLASS_ALIAS !== 'page') {
                    return $this->slug;
                }

                if ($model->isHomePage()) {
                    return false;
                }

                return $this->slug;
            },
            'modelMeta' => function () {
                /** @var \Kirby\Cms\Site|\Kirby\Cms\Page|\Kirby\Cms\File */
                $model = $this->model();
                return [
                    'context' => $model::CLASS_ALIAS,
                    'id' => $model->id()
                ];
            },
            'fields' => function () {
                return Translator::resolveModelFields($this->model);
            },
            'license' => function () {
                $licenses = Licenses::read('johannschopplich/kirby-content-translator');
                return $licenses->getStatus();
            }
        ]
    ]
];
