<?php

use JohannSchopplich\ContentTranslator\Translator;
use Kirby\CLI\CLI;

return [
    'playground:synchronize' => [
        'description' => 'Synchronizes the content from the default language to a secondary language.',
        'args' => [
            'language' => [
                'description' => 'The target language to synchronize the content to.',
                'defaultValue' => 'de'
            ]
        ],
        'command' => static function (CLI $cli): void {
            $kirby = $cli->kirby();
            $defaultLanguage = $kirby->defaultLanguage()->code();
            $targetLanguage = $cli->arg('language');

            if (!$kirby->multilang()) {
                $cli->error('Multi-language support is required');
                return;
            }

            $siteChildren = $kirby->site()->children();
            $input = $cli->radio(
                'Which pages should be synchronized?',
                $siteChildren->pluck('title')
            );
            $response = $input->prompt();
            $cli->success('Selected parent page: ' . $response);

            $page = $siteChildren->findBy('title', $response);

            // foreach ($page->children()->listed() as $item) {
            //     $translator = new Translator($item);
            //     $cli->out('Synchronizing ' . $item->id() . '...');

            //     $translator->synchronizeContent($targetLanguage, $defaultLanguage);
            // }

            $translator = new Translator($page);
            $cli->out('Synchronizing ' . $page->id() . '...');

            $translator->synchronizeContent($targetLanguage, $defaultLanguage);

            $cli->success('Page ' . $page->id() . ' successfully synchronized');
        }
    ],
    'playground:translate' => [
        'description' => 'Synchronizes and translates the content from the default language to a secondary language.',
        'args' => [
            'language' => [
                'description' => 'The target language to translate the content to.',
                'defaultValue' => 'de'
            ]
        ],
        'command' => static function (CLI $cli): void {
            $kirby = $cli->kirby();
            $defaultLanguage = $kirby->defaultLanguage()->code();
            $targetLanguage = $cli->arg('language');

            if (!$kirby->multilang()) {
                $cli->error('Multi-language support is required');
                return;
            }

            $siteChildren = $kirby->site()->children();
            $input = $cli->radio(
                'Which pages should be synchronized and translated?',
                $siteChildren->pluck('title')
            );
            $response = $input->prompt();
            $cli->success('Selected parent page: ' . $response);

            $page = $siteChildren->findBy('title', $response);

            // foreach ($page->children()->listed() as $item) {
            //     $translator = new Translator($item);
            //     $cli->out('Translating ' . $item->id() . '...');

            //     $translator->synchronizeContent($targetLanguage, $defaultLanguage);
            //     $translator->translateContent($targetLanguage, $targetLanguage, $defaultLanguage);
            // }

            $translator = new Translator($page);
            $cli->out('Translating ' . $page->id() . '...');

            $translator->synchronizeContent($targetLanguage, $defaultLanguage);
            $translator->translateContent($targetLanguage, $targetLanguage, $defaultLanguage);

            $cli->success('Page ' . $page->id() . ' successfully translated');
        }
    ]
];
