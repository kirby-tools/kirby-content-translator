<?php

declare(strict_types = 1);

use Kirby\Cms\App;
use PHPUnit\Framework\TestCase;

abstract class TranslationCoverageTestCase extends TestCase
{
    protected function tearDown(): void
    {
        App::destroy();
    }

    protected static function threeLanguages(): array
    {
        return [
            ['code' => 'en', 'name' => 'English', 'default' => true],
            ['code' => 'de', 'name' => 'Deutsch'],
            ['code' => 'fr', 'name' => 'Français'],
        ];
    }

    protected static function twoLanguages(): array
    {
        return [
            ['code' => 'en', 'name' => 'English', 'default' => true],
            ['code' => 'de', 'name' => 'Deutsch'],
        ];
    }

    protected static function pluginOptions(): array
    {
        return [
            'johannschopplich.content-translator' => [
                'cache' => ['type' => 'memory'],
            ],
        ];
    }

    protected function appWithLanguagesOnly(): App
    {
        return new App([
            'languages' => self::threeLanguages(),
            'options' => self::pluginOptions(),
        ]);
    }

    protected function appWithMixedCoverageFixture(): App
    {
        return new App([
            'languages' => self::threeLanguages(),
            'options' => self::pluginOptions(),
            'blueprints' => [
                'pages/default' => [
                    'fields' => [
                        'text' => ['type' => 'textarea', 'translate' => true],
                        'tags' => ['type' => 'tags', 'translate' => true],
                        'untranslatable' => ['type' => 'text', 'translate' => false],
                    ],
                ],
                'pages/no-translatable' => [
                    'fields' => [
                        'slug' => ['type' => 'slug'],
                        'toggle' => ['type' => 'toggle'],
                    ],
                ],
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'fully-translated',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => [
                                'title' => 'Fully Translated',
                                'text' => 'English text',
                                'tags' => 'tag1, tag2',
                                'untranslatable' => 'Do not translate',
                            ]],
                            ['code' => 'de', 'content' => [
                                'title' => 'Voll übersetzt',
                                'text' => 'Deutscher Text',
                                'tags' => 'tag1, tag2',
                            ]],
                            ['code' => 'fr', 'content' => [
                                'title' => 'Entièrement traduit',
                                'text' => 'Texte français',
                                'tags' => 'tag1, tag2',
                            ]],
                        ],
                    ],
                    [
                        'slug' => 'partially-translated',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => [
                                'title' => 'Partial',
                                'text' => 'English text',
                                'tags' => 'tag1',
                            ]],
                            ['code' => 'de', 'content' => [
                                'title' => 'Teilweise',
                                'text' => 'Deutscher Text',
                                'tags' => '',
                            ]],
                            ['code' => 'fr', 'content' => [
                                'title' => 'Partiel',
                                'text' => '',
                                'tags' => '',
                            ]],
                        ],
                    ],
                    [
                        'slug' => 'untranslated',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => [
                                'title' => 'Untranslated',
                                'text' => 'English only',
                                'tags' => 'tag1',
                            ]],
                        ],
                    ],
                    [
                        'slug' => 'no-fields',
                        'template' => 'no-translatable',
                        'translations' => [
                            ['code' => 'en', 'content' => ['title' => 'No translatable fields']],
                        ],
                    ],
                ],
            ],
        ]);
    }

    protected function appWithMissingTranslationsFixture(): App
    {
        return new App([
            'languages' => self::threeLanguages(),
            'options' => self::pluginOptions(),
            'blueprints' => [
                'pages/default' => [
                    'fields' => ['text' => ['type' => 'text', 'translate' => true]],
                ],
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'all-missing',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => ['text' => 'hello']],
                        ],
                    ],
                    [
                        'slug' => 'one-missing',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => ['text' => 'hello']],
                            ['code' => 'de', 'content' => ['text' => 'hallo']],
                        ],
                    ],
                ],
            ],
        ]);
    }

    protected function appWithEmptyBlocksTranslationFixture(): App
    {
        return new App([
            'languages' => self::twoLanguages(),
            'options' => self::pluginOptions(),
            'blueprints' => [
                'pages/default' => [
                    'fields' => ['blocks' => ['type' => 'blocks', 'translate' => true]],
                ],
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'page',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => [
                                'blocks' => '[{"id":"x","type":"text","content":{"text":"hi"}}]',
                            ]],
                            ['code' => 'de', 'content' => ['blocks' => '[]']],
                        ],
                    ],
                ],
            ],
        ]);
    }

    protected function appWithEmptyDefaultContentFixture(): App
    {
        return new App([
            'languages' => self::twoLanguages(),
            'options' => self::pluginOptions(),
            'blueprints' => [
                'pages/default' => [
                    'fields' => ['text' => ['type' => 'text', 'translate' => true]],
                ],
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'stub',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => ['text' => '']],
                            ['code' => 'de', 'content' => ['text' => 'irrelevant']],
                        ],
                    ],
                ],
            ],
        ]);
    }

    protected function appWithPartiallyFilledSourceFixture(): App
    {
        return new App([
            'languages' => self::twoLanguages(),
            'options' => self::pluginOptions(),
            'blueprints' => [
                'pages/default' => [
                    'fields' => [
                        'text' => ['type' => 'text', 'translate' => true],
                        'tags' => ['type' => 'tags', 'translate' => true],
                    ],
                ],
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'partial-source',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => ['text' => 'hello', 'tags' => '']],
                            ['code' => 'de', 'content' => ['text' => 'hallo', 'tags' => 'tag1']],
                        ],
                    ],
                ],
            ],
        ]);
    }

    protected function appWithUuidPageFixture(): App
    {
        return new App([
            'languages' => self::twoLanguages(),
            'options' => self::pluginOptions(),
            'blueprints' => [
                'pages/default' => [
                    'fields' => ['text' => ['type' => 'text', 'translate' => true]],
                ],
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'home',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => [
                                'uuid' => 'abc123',
                                'text' => 'Hello',
                            ]],
                            ['code' => 'de', 'content' => ['text' => 'Hallo']],
                        ],
                    ],
                ],
            ],
        ]);
    }

    protected function appWithNestedPagesFixture(): App
    {
        return new App([
            'languages' => self::twoLanguages(),
            'options' => self::pluginOptions(),
            'blueprints' => [
                'pages/default' => [
                    'fields' => ['text' => ['type' => 'text', 'translate' => true]],
                ],
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'parent',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => ['text' => 'hi']],
                            ['code' => 'de', 'content' => ['text' => 'hallo']],
                        ],
                        'children' => [
                            [
                                'slug' => 'child',
                                'template' => 'default',
                                'translations' => [
                                    ['code' => 'en', 'content' => ['text' => 'hi']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
