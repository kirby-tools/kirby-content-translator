<?php

use Kirby\CLI\CLI;

return [
    'playground:copy' => [
        'description' => 'Copies the content from the default language to a secondary language.',
        'args' => [
            'language' => [
                'description' => 'The target language to copy the content to.',
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
                'Which page should be copied?',
                $siteChildren->pluck('title')
            );
            $response = $input->prompt();
            $cli->success('Selected page: ' . $response);

            $page = $siteChildren->findBy('title', $response);
            $translator = $page->translator();
            $translator->copyContent($targetLanguage, $defaultLanguage);

            $cli->success('Successfully copied ' . $page->id());
        }
    ],
    'playground:translate' => [
        'description' => 'Translates the content of a specific page.',
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
                'Which page should be copied and translated?',
                $siteChildren->pluck('title')
            );
            $response = $input->prompt();
            $cli->success('Selected page: ' . $response);

            $page = $siteChildren->findBy('title', $response);
            $translator = $page->translator();

            $translator->copyContent($targetLanguage, $defaultLanguage);
            $translator->translateContent($targetLanguage, $targetLanguage, $defaultLanguage);

            $cli->success('Successfully translated ' . $page->id());
        }
    ]
];
