<?php

use JohannSchopplich\ContentTranslator\FieldResolver;
use JohannSchopplich\ContentTranslator\KirbyText;
use JohannSchopplich\ContentTranslator\Translator;
use JohannSchopplich\Licensing\Licenses;
use Kirby\Cms\App;
use Kirby\Exception\BadMethodCallException;
use Kirby\Exception\NotFoundException;

return [
    'routes' => fn (App $kirby) => [
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
        ],
        [
            'pattern' => '__content-translator__/translate-content',
            'method' => 'POST',
            'action' => function () use ($kirby) {
                $request = $kirby->request();
                $id = $request->get('id');
                $toLanguageCode = $request->get('selectedLanguage');
                $translateTitle = $request->get('title', false);
                $translateSlug = $request->get('slug', false);

                // Section-specific options
                $fieldTypes = $request->get('fieldTypes');
                $includeFields = $request->get('includeFields');
                $excludeFields = $request->get('excludeFields');
                $kirbyTags = $request->get('kirbyTags');

                if (!$id) {
                    throw new BadMethodCallException('Missing "id" parameter');
                }

                if (!$toLanguageCode) {
                    throw new BadMethodCallException('Missing "selectedLanguage" parameter');
                }

                $model = $id === 'site'
                    ? $kirby->site()
                    : $kirby->page($id, drafts: true) ?? $kirby->file($id, drafts: true);

                $fromLanguageCode = $kirby->defaultLanguage()->code();

                // Build translator options from section configuration
                $translatorOptions = array_filter([
                    'fieldTypes' => $fieldTypes,
                    'includeFields' => $includeFields,
                    'excludeFields' => $excludeFields,
                    'kirbyTags' => $kirbyTags,
                ]);

                if ($model::CLASS_ALIAS === 'site') {
                    /** @var \JohannSchopplich\ContentTranslator\Translator */
                    $translator = $kirby->site()->translator($translatorOptions);
                    $translator->copyContent($toLanguageCode, $fromLanguageCode);
                    $translator->translateContent($toLanguageCode, $toLanguageCode, $fromLanguageCode);
                    if ($translateTitle) {
                        $translator->translateTitle($toLanguageCode, $toLanguageCode, $fromLanguageCode);
                    }
                } elseif ($model::CLASS_ALIAS === 'page') {
                    /** @var \Kirby\Cms\Page */
                    $page = $kirby->page($id);

                    if (!$page) {
                        throw new NotFoundException('Cannot find page with id "' . $id . '"');
                    }

                    /** @var \JohannSchopplich\ContentTranslator\Translator */
                    $translator = $page->translator($translatorOptions);
                    $translator->copyContent($toLanguageCode, $fromLanguageCode);
                    $translator->translateContent($toLanguageCode, $toLanguageCode, $fromLanguageCode);
                    if ($translateTitle) {
                        $translator->translateTitle($toLanguageCode, $toLanguageCode, $fromLanguageCode);
                    }
                    if ($translateSlug) {
                        $translator->translateSlug($toLanguageCode, $toLanguageCode, $fromLanguageCode);
                    }
                } else {
                    $pageId = dirname($id);
                    $filename = basename($id);
                    /** @var \Kirby\Cms\Page */
                    $page = $kirby->page($pageId);
                    $file = $page->file($filename) ?? $kirby->site()->file($filename);

                    if (!$file) {
                        throw new NotFoundException('Cannot find file with id "' . $id . '"');
                    }

                    /** @var \JohannSchopplich\ContentTranslator\Translator */
                    $translator = $file->translator($translatorOptions);
                    $translator->copyContent($toLanguageCode, $fromLanguageCode);
                    $translator->translateContent($toLanguageCode, $toLanguageCode, $fromLanguageCode);
                    if ($translateTitle) {
                        $translator->translateTitle($toLanguageCode, $toLanguageCode, $fromLanguageCode);
                    }
                }

                return [
                    'status' => 'ok'
                ];
            }
        ],
        [
            'pattern' => '__content-translator__/activate',
            'method' => 'POST',
            'action' => function () {
                $licenses = Licenses::read('johannschopplich/kirby-content-translator');
                return $licenses->activateFromRequest();
            }
        ]
    ]
];
