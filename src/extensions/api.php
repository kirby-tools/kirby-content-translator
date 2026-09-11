<?php

use JohannSchopplich\ContentTranslator\BatchWriter;
use JohannSchopplich\ContentTranslator\PanelContext;
use JohannSchopplich\ContentTranslator\Translation\TranslationRejection;
use JohannSchopplich\ContentTranslator\TranslationCoverage;
use JohannSchopplich\ContentTranslator\Translator;
use JohannSchopplich\KirbyTools\FieldResolver;
use JohannSchopplich\KirbyTools\ModelResolver;
use JohannSchopplich\Licensing\LicensePanel;
use JohannSchopplich\Licensing\Licenses;
use Kirby\Cms\App;
use Kirby\Cms\Find;
use Kirby\Exception\BadMethodCallException;

return [
    'routes' => fn (App $kirby) => [
        ...LicensePanel::api('johannschopplich/kirby-content-translator'),
        [
            'pattern' => '__content-translator__/context',
            'method' => 'GET',
            'action' => function () use ($kirby) {
                $licenses = Licenses::read('johannschopplich/kirby-content-translator');

                return [
                    'config' => PanelContext::config(),
                    'homePageId' => $kirby->site()->homePageId(),
                    'errorPageId' => $kirby->site()->errorPageId(),
                    'licenseStatus' => $licenses->getStatus()
                ];
            }
        ],
        [
            'pattern' => '__content-translator__/model-fields',
            'method' => 'GET',
            'action' => function () use ($kirby) {
                $id = $kirby->request()->query()->get('id');
                $model = ModelResolver::resolveFromId($id);

                return FieldResolver::resolveModelFields($model);
            }
        ],
        [
            'pattern' => '__content-translator__/translate-batch',
            'method' => 'POST',
            'action' => function () use ($kirby) {
                $request = $kirby->request();
                $texts = $request->get('texts');
                $sourceLanguage = $request->get('sourceLanguage');
                $targetLanguage = $request->get('targetLanguage');

                if (!$texts || !is_array($texts)) {
                    throw new BadMethodCallException('Missing or invalid "texts" parameter');
                }

                if (!$targetLanguage) {
                    throw new BadMethodCallException('Missing "targetLanguage" parameter');
                }

                $result = Translator::translateBatch($texts, $targetLanguage, $sourceLanguage);

                return [
                    'texts' => $result->texts,
                    'rejections' => array_map(
                        static function (TranslationRejection $rejection): array {
                            $payload = [
                                'index' => $rejection->index,
                                'reason' => $rejection->reason
                            ];

                            // The Panel formats the detail from these, so the wire
                            // carries indexes rather than prose.
                            if ($rejection->expectedIndexes !== null) {
                                $payload['expectedIndexes'] = $rejection->expectedIndexes;
                                $payload['actualIndexes'] = $rejection->actualIndexes;
                            }

                            return $payload;
                        },
                        $result->rejections
                    )
                ];
            }
        ],
        [
            'pattern' => '__content-translator__/batch-status',
            'method' => 'GET',
            'action' => function () use ($kirby) {
                $path = (string)$kirby->request()->query()->get('path');

                return BatchWriter::status(Find::parent($path));
            }
        ],
        [
            'pattern' => '__content-translator__/batch-write',
            'method' => 'POST',
            'action' => function () use ($kirby) {
                $request = $kirby->request();
                $path = (string)$request->get('path');
                $languageCode = $request->get('language');
                $content = $request->get('content', []);

                if (!is_string($languageCode) || $languageCode === '') {
                    throw new BadMethodCallException('Missing "language" parameter');
                }

                if (!is_array($content)) {
                    throw new BadMethodCallException('Invalid "content" parameter');
                }

                return BatchWriter::write(
                    model: Find::parent($path),
                    languageCode: $languageCode,
                    content: $content,
                    title: $request->get('title'),
                    slug: $request->get('slug'),
                );
            }
        ],
        [
            'pattern' => '__content-translator__/coverage',
            'method' => 'GET',
            'action' => function () use ($kirby) {
                $coverageConfig = $kirby->option('johannschopplich.content-translator.coverage', true);

                if ($coverageConfig === false || $coverageConfig === []) {
                    return null;
                }

                $pagesQuery = is_array($coverageConfig) ? ($coverageConfig['pages'] ?? null) : null;
                $pages = ($pagesQuery && is_callable($pagesQuery))
                    ? $pagesQuery()
                    : $kirby->site()->index();

                $coverage = new TranslationCoverage($pages);
                $parent = $kirby->request()->get('parent');

                if ($parent !== null) {
                    return ['children' => $coverage->treeChildren($parent)];
                }

                return $coverage->treeCoverage();
            }
        ]
    ]
];
