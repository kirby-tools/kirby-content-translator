<?php

use JohannSchopplich\ContentTranslator\ContentTranslatorField;
use JohannSchopplich\ContentTranslator\Licensing;
use JohannSchopplich\ContentTranslator\Translator;
use Kirby\Cms\App as Kirby;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Exception\LogicException;
use KirbyTools\Licensing\LicensePanel;
use KirbyTools\Licensing\PluginLicense;

if (version_compare(Kirby::version() ?? '0', '6.0.0-beta', '<')) {
    throw new LogicException('Kirby Content Translator requires Kirby 6');
}

@include_once __DIR__ . '/vendor/autoload.php';

Kirby::plugin(
    name: 'johannschopplich/content-translator',
    extends: [
        'options' => [
            'cache' => true
        ],
        'api' => require __DIR__ . '/src/extensions/api.php',
        'areas' => [
            'system' => fn () => [
                'dialogs' => LicensePanel::dialogs(Licensing::PACKAGE_NAME, 'Kirby Content Translator')
            ]
        ],
        'fields' => [
            'content-translator' => ContentTranslatorField::class
        ],
        'hooks' => require __DIR__ . '/src/extensions/hooks.php',
        'translations' => require __DIR__ . '/src/extensions/translations.php',
        'siteMethods' => [
            'translator' => function (array $options = []) {
                return new Translator($this, $options);
            }
        ],
        'pageMethods' => [
            'translator' => function (array $options = []) {
                return new Translator($this, $options);
            }
        ],
        'fileMethods' => [
            'translator' => function (array $options = []) {
                return new Translator($this, $options);
            }
        ]
    ],
    license: fn ($plugin) => new PluginLicense($plugin, Licensing::PACKAGE_NAME)
);

if (!function_exists('translator')) {
    function translator(Site|Page|File $model, array $options = []): Translator
    {
        return new Translator($model, $options);
    }
}

if (!function_exists('translate')) {
    function translate(string $text, string $targetLanguage, string|null $sourceLanguage = null): string
    {
        return Translator::translateText($text, $targetLanguage, $sourceLanguage);
    }
}
