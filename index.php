<?php

use Composer\Semver\Semver;
use Kirby\Cms\App as Kirby;
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
    'translations' => require __DIR__ . '/src/extensions/translations.php'
]);
