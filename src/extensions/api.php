<?php

use JohannSchopplich\ContentTranslator\KirbyText;
use JohannSchopplich\ContentTranslator\Translator;
use JohannSchopplich\KirbyPlugins\FieldResolver;
use JohannSchopplich\Licensing\Licenses;
use JohannSchopplich\Licensing\PluginLicenseExtensions;
use Kirby\Cms\App;
use Kirby\Exception\BadMethodCallException;

return [
    'routes' => fn (App $kirby) => [
        ...PluginLicenseExtensions::api('johannschopplich/kirby-content-translator'),
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

                // For backwards compatibility with Kirby 4
                // TODO: Deprecated, remove for Kirby 6 release
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
                $model = $id === 'site'
                    ? $kirby->site()
                    : $kirby->page($id, drafts: true) ?? $kirby->file($id, drafts: true);

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
            'pattern' => '__content-translator__/translate-kirbytext',
            'method' => 'POST',
            'action' => function () use ($kirby) {
                $request = $kirby->request();
                $text = $request->get('text');
                $sourceLanguage = $request->get('sourceLanguage');
                $targetLanguage = $request->get('targetLanguage');
                $kirbyTags = $request->get(
                    'kirbyTags',
                    $kirby->option('johannschopplich.content-translator.kirbyTags', [])
                );

                if (!$text) {
                    throw new BadMethodCallException('Missing "text" parameter');
                }

                if (!$targetLanguage) {
                    throw new BadMethodCallException('Missing "targetLanguage" parameter');
                }

                $translatedText = KirbyText::translateText($text, $targetLanguage, $sourceLanguage, $kirbyTags);

                return [
                    'text' => $translatedText
                ];
            }
        ]
    ]
];
