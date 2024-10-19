<?php

use JohannSchopplich\ContentTranslator\Translator;
use JohannSchopplich\Licensing\Licenses;
use Kirby\Cms\App;
use Kirby\Cms\Language;
use Kirby\Exception\BadMethodCallException;

return [
    'routes' => fn (App $kirby) => [
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
            'pattern' => '__content-translator__/bulk-translate-content',
            'method' => 'POST',
            'action' => function () use ($kirby) {
                $request = $kirby->request();
                $context = $request->get('context');
                $id = $request->get('id');
                $selectedLanguage = $request->get('selectedLanguage');
                $translateTitle = $request->get('title', false);
                $translateSlug = $request->get('slug', false);

                if (!$context || !$id) {
                    throw new BadMethodCallException('Missing "context" or "id" parameter');
                }

                if (!$selectedLanguage) {
                    throw new BadMethodCallException('Missing "language" parameter');
                }

                $defaultLanguage = $kirby->defaultLanguage();
                $language = $kirby->languages()->findByKey($selectedLanguage);

                if ($context === 'site') {
                    /** @var \JohannSchopplich\ContentTranslator\Translator */
                    $translator = $kirby->site()->translator();

                    $translator->copyContent($language->code(), $defaultLanguage->code());
                    $translator->translateContent($language->code(), $language->code(), $defaultLanguage->code());
                    if ($translateTitle) $translator->translateTitle($language->code(), $language->code(), $defaultLanguage->code());
                } else if ($context === 'page') {
                    /** @var \Kirby\Cms\Page */
                    $page = $kirby->page($id);
                    /** @var \JohannSchopplich\ContentTranslator\Translator */
                    $translator = $page->translator();

                    /** @var \Kirby\Cms\Language $language */
                    $translator->copyContent($language->code(), $defaultLanguage->code());
                    $translator->translateContent($language->code(), $language->code(), $defaultLanguage->code());
                    if ($translateTitle) $translator->translateTitle($language->code(), $language->code(), $defaultLanguage->code());
                    if ($translateSlug) $translator->translateSlug($language->code(), $language->code(), $defaultLanguage->code());
                } else {
                    $id = dirname($id);
                    $filename = basename($id);
                    /** @var \Kirby\Cms\Page */
                    $page = $kirby->page($id);
                    $file = $page->file($filename) ?? $kirby->site()->file($filename);

                    /** @var \JohannSchopplich\ContentTranslator\Translator */
                    $translator = $file->translator();

                    /** @var \Kirby\Cms\Language $language */
                    $translator->copyContent($language->code(), $defaultLanguage->code());
                    $translator->translateContent($language->code(), $language->code(), $defaultLanguage->code());
                    if ($translateTitle) $translator->translateTitle($language->code(), $language->code(), $defaultLanguage->code());
                }

                return [
                    'ok' => true,
                ];
            }
        ],
        [
            'pattern' => '__content-translator__/register',
            'method' => 'POST',
            'action' => function () {
                $licenses = Licenses::read('johannschopplich/kirby-content-translator', ['migrate' => false]);
                return $licenses->registerFromRequest();
            }
        ]
    ]
];
