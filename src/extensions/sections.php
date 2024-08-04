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
            'fields' => function () {
                return Translator::resolveModelFields($this->model);
            },
            'config' => function () {
                /** @var \Kirby\Cms\App */
                $kirby = $this->kirby();
                $config = $kirby->option('johannschopplich.content-translator', []);

                // Don't leak the API key to the Panel frontend
                if (isset($config['DeepL']['apiKey'])) {
                    $config['DeepL'] = [
                        'apiKey' => !empty($config['DeepL']['apiKey'])
                    ];
                }

                $config['translateFn'] = isset($config['translateFn']) && is_callable($config['translateFn']);

                return $config;
            },
            'license' => function () {
                $licenses = Licenses::read('johannschopplich/kirby-content-translator');
                return $licenses->getStatus();
            }
        ]
    ]
];
