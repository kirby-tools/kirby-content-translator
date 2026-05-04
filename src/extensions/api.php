<?php

use JohannSchopplich\ContentTranslator\Translation\Strategies\CopilotAIStrategy;
use JohannSchopplich\ContentTranslator\TranslationCoverage;
use JohannSchopplich\ContentTranslator\Translator;
use JohannSchopplich\Copilot\AI\Client as CopilotClient;
use JohannSchopplich\KirbyTools\FieldResolver;
use JohannSchopplich\KirbyTools\ModelResolver;
use JohannSchopplich\Licensing\LicensePanel;
use JohannSchopplich\Licensing\Licenses;
use Kirby\Cms\App;
use Kirby\Exception\BadMethodCallException;

return [
    'routes' => fn (App $kirby) => [
        ...LicensePanel::api('johannschopplich/kirby-content-translator'),
        [
            'pattern' => '__content-translator__/context',
            'method' => 'GET',
            'action' => function () use ($kirby) {
                $licenses = Licenses::read('johannschopplich/kirby-content-translator');
                $config = $kirby->option('johannschopplich.content-translator', []);

                // Don't leak the API key to the Panel frontend
                if (isset($config['DeepL']['apiKey'])) {
                    $config['DeepL'] = [
                        'apiKey' => !empty($config['DeepL']['apiKey'])
                    ];
                }

                $config['translateFn'] = isset($config['translateFn']) && is_callable($config['translateFn']);

                if (class_exists(CopilotClient::class)) {
                    $config['ai'] ??= [];
                    $config['ai']['systemPrompt'] = CopilotAIStrategy::resolveDefaultSystemPrompt();
                }

                // Keep backwards compatibility with Kirby 4
                // TODO: Deprecated, remove in Kirby 6
                $config['viewButton'] ??= true;

                return [
                    'config' => $config,
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
            'pattern' => '__content-translator__/translate',
            'method' => 'POST',
            'action' => function () use ($kirby) {
                $request = $kirby->request();
                $text = $request->get('text');
                $sourceLanguage = $request->get('sourceLanguage');
                $targetLanguage = $request->get('targetLanguage');

                if (!$text) {
                    throw new BadMethodCallException('Missing "text" parameter');
                }

                if (!$targetLanguage) {
                    throw new BadMethodCallException('Missing "targetLanguage" parameter');
                }

                $text = Translator::translateText($text, $targetLanguage, $sourceLanguage);

                return [
                    'text' => $text
                ];
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

                $translatedTexts = Translator::translateTexts($texts, $targetLanguage, $sourceLanguage);

                return [
                    'texts' => $translatedTexts
                ];
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
