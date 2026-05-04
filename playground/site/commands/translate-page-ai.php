<?php

use JohannSchopplich\ContentTranslator\Translation\Strategies\CopilotAIStrategy;
use Kirby\CLI\CLI;

return [
    'description' => 'Translates a page using AI, overriding the configured strategy.',
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

        $strategy = new CopilotAIStrategy();

        // `translator()` is a page method registered by the kirby-content-translator plugin.
        $translator = $page->translator();
        $translator->copyContent($targetLanguage, $defaultLanguage);
        $translator->translateContent($targetLanguage, $targetLanguage, $defaultLanguage, $strategy);
        $translator->translateTitle($targetLanguage, $targetLanguage, $defaultLanguage);
        // $translator->translateSlug($targetLanguage, $targetLanguage, $defaultLanguage);

        $cli->success('Successfully translated ' . $page->id() . ' via AI');
    }
];
