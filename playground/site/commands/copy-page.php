<?php

use Kirby\CLI\CLI;

return [
    'description' => 'Copies the content from the default language.',
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

        $siteChildren = $kirby->site()->children();
        $input = $cli->radio(
            'Which page\'s content should be duplicated?',
            $siteChildren->pluck('title')
        );
        $response = $input->prompt();
        $cli->success('Selected page: ' . $response);

        $page = $siteChildren->findBy('title', $response);
        $translator = $page->translator();
        $translator->copyContent($targetLanguage, $defaultLanguage);

        $cli->success('Successfully copied ' . $page->id() . ' content');
    }
];
