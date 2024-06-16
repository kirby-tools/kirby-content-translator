<?php

use Kirby\CLI\CLI;

return [
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
];
