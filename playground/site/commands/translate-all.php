<?php

use Kirby\CLI\CLI;
use Kirby\Cms\Language;

$defaultAllLanguagesLabel = 'All Languages';

return [
    'description' => 'Translates the content of the whole website',
    'args' => [],
    'command' => static function (CLI $cli) use ($defaultTranslatorOptions, $defaultAllLanguagesLabel): void {
        $kirby = $cli->kirby();
        $defaultLanguage = $kirby->defaultLanguage()->code();
        $nonDefaultLanguages = $kirby->languages()->filter(fn (Language $language) => !$language->isDefault());

        $input = $cli->radio(
            'Content of which language/languages should be translated?',
            [
                $defaultAllLanguagesLabel,
                ...$nonDefaultLanguages->pluck('name')
            ]
        );

        $targetLanguage = $input->prompt();
        $hasAllLanguagesSelected = $targetLanguage === $defaultAllLanguagesLabel;
        $selectedLanguages = $hasAllLanguagesSelected
            ? $nonDefaultLanguages
            : $nonDefaultLanguages->filter(fn (Language $language) => $language->name() === $targetLanguage);

        $cli->success('Translating to: ' . implode(', ', $selectedLanguages->pluck('name')));

        // Translate all site translations
        foreach ($selectedLanguages as $language) {
            /** @var \JohannSchopplich\ContentTranslator\Translator */
            $translator = $kirby->site()->translator($defaultTranslatorOptions);

            $translator->copyContent($language->code(), $defaultLanguage);
            $translator->translateContent($language->code(), $language->code(), $defaultLanguage);
            $cli->{$hasAllLanguagesSelected ? 'out' : 'success'}('Translated site data to ' . $language->name());
        }

        if ($hasAllLanguagesSelected) {
            $cli->success('Successfully translated all ' . $kirby->site()->title() . ' site data');
        }

        // Recursively translate all pages
        foreach ($kirby->site()->index() as $page) {
            /** @var \JohannSchopplich\ContentTranslator\Translator */
            $translator = $page->translator($defaultTranslatorOptions);

            foreach ($selectedLanguages as $language) {
                /** @var \Kirby\Cms\Language $language */
                $translator->copyContent($language->code(), $defaultLanguage);
                $translator->translateContent($language->code(), $language->code(), $defaultLanguage);
                $translator->translateTitle($language->code(), $language->code(), $defaultLanguage);
            }

            $cli->out('Translated ' . $page->id());
        }

        $cli->success('Successfully translated all ' . $kirby->site()->title() . ' pages');
    }
];
