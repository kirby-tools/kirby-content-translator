<?php

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
                $config['viewButton'] = $config['viewButton'] ?? true;

                return [
                    'config' => $config,
                    'homePageId' => $kirby->site()->homePageId(),
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

                return Translator::resolveModelFields($model);
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

                return compact('text');
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

                if ($model::CLASS_ALIAS === 'site') {
                    /** @var \JohannSchopplich\ContentTranslator\Translator */
                    $translator = $kirby->site()->translator();
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
                    $translator = $page->translator();
                    $translator->copyContent($toLanguageCode, $fromLanguageCode);
                    $translator->translateContent($toLanguageCode, $toLanguageCode, $fromLanguageCode);
                    if ($translateTitle) {
                        $translator->translateTitle($toLanguageCode, $toLanguageCode, $fromLanguageCode);
                    }
                    if ($translateSlug) {
                        $translator->translateSlug($toLanguageCode, $toLanguageCode, $fromLanguageCode);
                    }
                } else {
                    $id = dirname($id);
                    $filename = basename($id);
                    /** @var \Kirby\Cms\Page */
                    $page = $kirby->page($id);
                    $file = $page->file($filename) ?? $kirby->site()->file($filename);

                    if (!$file) {
                        throw new NotFoundException('Cannot find file with id "' . $id . '"');
                    }

                    /** @var \JohannSchopplich\ContentTranslator\Translator */
                    $translator = $file->translator();
                    $translator->copyContent($toLanguageCode, $fromLanguageCode);
                    $translator->translateContent($toLanguageCode, $toLanguageCode, $fromLanguageCode);
                }

                return [
                    'ok' => true,
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
