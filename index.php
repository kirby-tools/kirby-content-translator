<?php

use Composer\Semver\Semver;
use Kirby\Cms\App as Kirby;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Filesystem\F;

F::loadClasses([
    'JohannSchopplich\\ContentTranslator\\Translator' => 'src/classes/ContentTranslator/Translator.php',
    'JohannSchopplich\\ContentTranslator\\DeepL' => 'src/classes/ContentTranslator/DeepL.php'
], __DIR__);

// Validate Kirby version
if (!Semver::satisfies(Kirby::version() ?? '0.0.0', '~4.0')) {
    throw new Exception('Kirby Content Translator requires Kirby 4');
}

Kirby::plugin('johannschopplich/content-translator', [
    'api' => require __DIR__ . '/src/extensions/api.php',
    'sections' => require __DIR__ . '/src/extensions/sections.php',
    'translations' => require __DIR__ . '/src/extensions/translations.php',
    'siteMethods' => [
        'translator' => function (array $options = []) {
            return new \JohannSchopplich\ContentTranslator\Translator($this, $options);
        }
    ],
    'pageMethods' => [
        'translator' => function (array $options = []) {
            return new \JohannSchopplich\ContentTranslator\Translator($this, $options);
        }
    ],
    'fileMethods' => [
        'translator' => function (array $options = []) {
            return new \JohannSchopplich\ContentTranslator\Translator($this, $options);
        }
    ]
]);

if (!function_exists('translator')) {
    function translator(Site|Page|File $model, array $options = []): \JohannSchopplich\ContentTranslator\Translator
    {
        return new \JohannSchopplich\ContentTranslator\Translator($model, $options);
    }
}
