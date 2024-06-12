<?php

use Kirby\CLI\CLI;

return [
    'copy:page' => [
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
                'Which page content should be duplicated?',
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
    'translate:page' => [
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
                'Which page should be translated?',
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
    ],
    'translate:children' => [
        'description' => 'Translates the content of all children of a specific page.',
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
                'Which page\'s children should be translated?',
                $siteChildren->pluck('title')
            );
            $response = $input->prompt();
            $cli->success('Selected parent page: ' . $response);

            $page = $siteChildren->findBy('title', $response);

            foreach ($page->children()->listed() as $child) {
                $translator = $child->translator();
                $translator->copyContent($targetLanguage, $defaultLanguage);
                $translator->translateContent($targetLanguage, $targetLanguage, $defaultLanguage);
                $cli->out('Translated ' . $child->id());
            }

            $cli->success('Successfully translated all ' . $page->id() . ' children');
        }
    ]
];
