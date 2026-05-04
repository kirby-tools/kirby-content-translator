<?php

use Kirby\CLI\CLI;

return [
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

        $siteChildren = $kirby->site()->children();
        $titles = array_map('strval', $siteChildren->pluck('title'));
        $input = $cli->radio(
            'Which page should be translated?',
            $titles
        );
        $response = $input->prompt();
        $cli->success('Selected page: ' . $response);

        $page = $siteChildren->findBy('title', $response);
        if ($page === null) {
            $cli->error('Page "' . $response . '" not found.');
            return;
        }

        $translator = $page->translator();
        $translator->copyContent($targetLanguage, $defaultLanguage);
        $translator->translateContent($targetLanguage, $targetLanguage, $defaultLanguage);
        $translator->translateTitle($targetLanguage, $targetLanguage, $defaultLanguage);
        // $translator->translateSlug($targetLanguage, $targetLanguage, $defaultLanguage);

        $cli->success('Successfully translated ' . $page->id());
    }
];
