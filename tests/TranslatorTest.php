<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\Translator;
use Kirby\Cms\App;
use Kirby\Data\Json;
use Kirby\Data\Yaml;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TranslatorTest extends TestCase
{
    protected function tearDown(): void
    {
        App::destroy();
    }

    private static function threeLanguages(): array
    {
        return [
            ['code' => 'en', 'name' => 'English', 'default' => true],
            ['code' => 'de', 'name' => 'Deutsch'],
            ['code' => 'fr', 'name' => 'Français'],
        ];
    }

    private static function fakeTranslateFn(): \Closure
    {
        return function (string $text, string $toLanguageCode, string|null $fromLanguageCode = null): string {
            $prefix = $fromLanguageCode !== null ? "[$toLanguageCode:$fromLanguageCode]" : "[$toLanguageCode]";
            return "$prefix$text";
        };
    }

    private static function pluginOptions(): array
    {
        return [
            'debug' => true,
            'johannschopplich.content-translator' => [
                'translateFn' => self::fakeTranslateFn(),
            ],
        ];
    }

    private function appWithTranslateFn(): App
    {
        return new App([
            'languages' => self::threeLanguages(),
            'options' => self::pluginOptions(),
        ]);
    }

    private function appWithScalarFieldPage(): App
    {
        return new App([
            'languages' => self::threeLanguages(),
            'blueprints' => [
                'pages/default' => [
                    'fields' => [
                        'title' => ['type' => 'text', 'translate' => true],
                        'text' => ['type' => 'textarea', 'translate' => true],
                        'untranslatableText' => ['type' => 'text', 'translate' => false],
                        'tags' => ['type' => 'tags', 'translate' => true],
                        'list' => ['type' => 'list', 'translate' => true],
                        'writer' => ['type' => 'writer', 'translate' => true],
                    ],
                ],
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'home',
                        'template' => 'default',
                        'translations' => [
                            [
                                'code' => 'en',
                                'content' => [
                                    'title' => 'Home',
                                    'text' => 'Welcome to our website',
                                    'untranslatableText' => 'Do not translate',
                                    'tags' => 'tag1, tag2',
                                    'list' => 'item1, item2',
                                    'writer' => 'Writer content',
                                ],
                            ],
                            ['code' => 'de', 'content' => []],
                        ],
                    ],
                    [
                        'slug' => 'about',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => ['title' => 'About']],
                        ],
                    ],
                ],
            ],
            'options' => self::pluginOptions(),
        ]);
    }

    private function appWithBlocksPage(): App
    {
        $blocks = Json::encode([
            ['type' => 'text', 'id' => '1234', 'content' => ['text' => 'Block content']],
            ['type' => 'heading', 'id' => '5678', 'content' => ['title' => 'Block heading', 'level' => 'h2']],
            ['type' => 'text', 'id' => '9999', 'isHidden' => true, 'content' => ['text' => 'Hidden block content']],
            ['type' => 'container', 'id' => 'cont1', 'content' => [
                'heading' => 'Container heading',
                'blocks' => Json::encode([
                    ['type' => 'text', 'id' => 'nested1', 'content' => ['text' => 'Nested block text']],
                ]),
            ]],
        ]);

        return new App([
            'languages' => self::threeLanguages(),
            'blueprints' => [
                'pages/default' => [
                    'fields' => [
                        'blocks' => [
                            'type' => 'blocks',
                            'translate' => true,
                            'fieldsets' => [
                                'text' => ['tabs' => ['content' => ['fields' => [
                                    'text' => ['type' => 'textarea', 'translate' => true],
                                ]]]],
                                'heading' => ['tabs' => ['content' => ['fields' => [
                                    'title' => ['type' => 'text', 'translate' => true],
                                    'level' => [
                                        'type' => 'select',
                                        'translate' => false,
                                        'options' => ['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3'],
                                    ],
                                ]]]],
                                'container' => ['tabs' => ['content' => ['fields' => [
                                    'heading' => ['type' => 'text', 'translate' => true],
                                    'blocks' => [
                                        'type' => 'blocks',
                                        'translate' => true,
                                        'fieldsets' => [
                                            'text' => ['tabs' => ['content' => ['fields' => [
                                                'text' => ['type' => 'text', 'translate' => true],
                                            ]]]],
                                        ],
                                    ],
                                ]]]],
                            ],
                        ],
                    ],
                ],
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'home',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => ['blocks' => $blocks]],
                        ],
                    ],
                ],
            ],
            'options' => self::pluginOptions(),
        ]);
    }

    private function appWithNestedFieldsPage(): App
    {
        $structure = Yaml::encode([
            ['heading' => 'Section 1', 'description' => 'Description 1'],
            ['heading' => 'Section 2', 'description' => 'Description 2'],
        ]);
        $object = Yaml::encode([
            'title' => 'Object title',
            'description' => 'Object description',
        ]);
        $layout = Json::encode([
            [
                'id' => 'layout1',
                'attrs' => [],
                'columns' => [
                    [
                        'id' => 'col1',
                        'blocks' => [
                            ['type' => 'text', 'id' => 'block1', 'content' => ['text' => 'Layout block content']],
                        ],
                    ],
                ],
            ],
        ]);

        return new App([
            'languages' => self::threeLanguages(),
            'blueprints' => [
                'pages/default' => [
                    'fields' => [
                        'structure' => [
                            'type' => 'structure',
                            'translate' => true,
                            'fields' => [
                                'heading' => ['type' => 'text', 'translate' => true],
                                'description' => ['type' => 'textarea', 'translate' => true],
                            ],
                        ],
                        'object' => [
                            'type' => 'object',
                            'translate' => true,
                            'fields' => [
                                'title' => ['type' => 'text', 'translate' => true],
                                'description' => ['type' => 'textarea', 'translate' => true],
                            ],
                        ],
                        'layout' => [
                            'type' => 'layout',
                            'translate' => true,
                            'fieldsets' => [
                                'text' => ['tabs' => ['content' => ['fields' => [
                                    'text' => ['type' => 'textarea', 'translate' => true],
                                ]]]],
                            ],
                        ],
                    ],
                ],
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'home',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => [
                                'structure' => $structure,
                                'object' => $object,
                                'layout' => $layout,
                            ]],
                        ],
                    ],
                ],
            ],
            'options' => self::pluginOptions(),
        ]);
    }

    private function appWithFilterableFieldsPage(): App
    {
        return new App([
            'languages' => self::threeLanguages(),
            'blueprints' => [
                'pages/default' => [
                    'fields' => [
                        'title' => ['type' => 'text', 'translate' => true],
                        'text' => ['type' => 'textarea', 'translate' => true],
                        'tags' => ['type' => 'tags', 'translate' => true],
                        'structure' => [
                            'type' => 'structure',
                            'translate' => true,
                            'fields' => [
                                'heading' => ['type' => 'text', 'translate' => true],
                                'description' => ['type' => 'textarea', 'translate' => true],
                            ],
                        ],
                    ],
                ],
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'home',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => [
                                'title' => 'Home',
                                'text' => 'Welcome to our website',
                                'tags' => 'tag1, tag2',
                                'structure' => Yaml::encode([
                                    ['heading' => 'Section 1', 'description' => 'Description 1'],
                                ]),
                            ]],
                        ],
                    ],
                ],
            ],
            'options' => self::pluginOptions(),
        ]);
    }

    private function appWithKirbyTagsPage(): App
    {
        return new App([
            'languages' => self::threeLanguages(),
            'blueprints' => [
                'pages/default' => [
                    'fields' => [
                        'title' => ['type' => 'text', 'translate' => true],
                        'text' => ['type' => 'textarea', 'translate' => true],
                    ],
                ],
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'kirbytags',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => [
                                'title' => 'KirbyTags Test',
                                'text' => 'Visit (link: https://example.com text: our website title: Click here)!',
                            ]],
                        ],
                    ],
                ],
            ],
            'options' => self::pluginOptions(),
            'tags' => [
                'link' => [
                    'attr' => ['text', 'title', 'class', 'rel', 'target', 'lang', 'role'],
                    'html' => fn ($tag) => '<a href="' . $tag->link . '">' . ($tag->text ?? $tag->link) . '</a>',
                ],
            ],
        ]);
    }

    /** @return array<string, array{0: string}> */
    public static function whitespaceTexts(): array
    {
        return [
            'empty string' => [''],
            'spaces only' => ['   '],
            'newline and tab only' => ["\n\t"],
        ];
    }

    #[Test]
    #[DataProvider('whitespaceTexts')]
    public function returns_whitespace_only_text_unchanged(string $text): void
    {
        $this->appWithTranslateFn();
        $this->assertSame($text, Translator::translateText($text, 'de'));
    }

    /** @return array<string, array{0: string|null, 1: string}> */
    public static function sourceLanguageVariants(): array
    {
        return [
            'without source language' => [null, '[de]'],
            'with source language' => ['en', '[de:en]'],
        ];
    }

    #[Test]
    #[DataProvider('sourceLanguageVariants')]
    public function translate_text_passes_source_language_to_translate_fn(string|null $sourceLanguage, string $prefix): void
    {
        $this->appWithTranslateFn();
        $this->assertSame("{$prefix}hello", Translator::translateText('hello', 'de', $sourceLanguage));
    }

    /** @return array<string, array{0: string}> */
    public static function pureNumericValues(): array
    {
        return [
            'integer' => ['123'],
            'decimal' => ['45.67'],
            'negative' => ['-99'],
            'scientific notation' => ['1.5e10'],
        ];
    }

    #[Test]
    #[DataProvider('pureNumericValues')]
    public function skips_pure_numeric_values(string $value): void
    {
        $this->appWithTranslateFn();
        $this->assertSame($value, Translator::translateText($value, 'de'));
    }

    #[Test]
    public function translates_text_with_embedded_numbers(): void
    {
        $this->appWithTranslateFn();
        $this->assertSame('[de]Product 123', Translator::translateText('Product 123', 'de'));
    }

    /** @return array<string, array{0: string}> */
    public static function pureUrlValues(): array
    {
        return [
            'https url' => ['https://example.com'],
            'http url with query' => ['http://localhost:3000/path?query=1'],
        ];
    }

    #[Test]
    #[DataProvider('pureUrlValues')]
    public function skips_pure_url_values(string $value): void
    {
        $this->appWithTranslateFn();
        $this->assertSame($value, Translator::translateText($value, 'de'));
    }

    #[Test]
    public function translates_text_with_embedded_url(): void
    {
        $this->appWithTranslateFn();
        $this->assertSame('[de]Visit https://example.com today', Translator::translateText('Visit https://example.com today', 'de'));
    }

    #[Test]
    public function copy_content_from_default_language_deletes_target_translation(): void
    {
        $app = $this->appWithScalarFieldPage();
        $page = $app->page('home');
        $translator = new Translator($page);
        $translator->copyContent('de', 'en');

        $this->assertFalse($translator->model()->version()->exists('de'));
        $this->assertSame(
            'Welcome to our website',
            $translator->model()->content('de')->get('text')->value()
        );
        $this->assertSame(
            'Do not translate',
            $translator->model()->content('de')->get('untranslatableText')->value()
        );
    }

    #[Test]
    public function copy_content_from_non_default_language_copies_content(): void
    {
        $app = $this->appWithScalarFieldPage();
        $page = $app->page('home');

        $page = $app->impersonate('kirby', fn () => $page->update([
            'text' => 'Bienvenue sur notre site',
        ], 'fr'));

        $translator = new Translator($page);
        $translator->copyContent('de', 'fr');

        $this->assertSame(
            'Bienvenue sur notre site',
            $translator->model()->content('de')->get('text')->value()
        );
    }

    #[Test]
    public function translate_content_recreates_target_translation_when_prior_copy_cleared_it(): void
    {
        $app = $this->appWithScalarFieldPage();
        $page = $app->page('home');
        $translator = new Translator($page);

        $translator->copyContent('de', 'en');
        $this->assertFalse($translator->model()->version()->exists('de'));

        $translator->translateContent('de', 'de', 'en');
        $this->assertSame(
            '[de:en]Welcome to our website',
            $translator->model()->content('de')->get('text')->value()
        );
    }

    #[Test]
    #[DataProvider('sourceLanguageVariants')]
    public function translate_content_passes_source_language_to_translate_fn(string|null $sourceLanguage, string $prefix): void
    {
        $app = $this->appWithScalarFieldPage();
        $page = $app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de', $sourceLanguage);

        $this->assertSame(
            "{$prefix}Welcome to our website",
            $translator->model()->content('en')->get('text')->value()
        );
    }

    #[Test]
    #[DataProvider('sourceLanguageVariants')]
    public function translate_title_passes_source_language_to_translate_fn(string|null $sourceLanguage, string $prefix): void
    {
        $app = $this->appWithScalarFieldPage();
        $page = $app->page('home');
        $translator = new Translator($page);
        $translator->translateTitle('en', 'de', $sourceLanguage);

        $this->assertSame("{$prefix}Home", $translator->model()->title()->value());
    }

    #[Test]
    public function does_not_translate_home_page_slug(): void
    {
        $app = $this->appWithScalarFieldPage();
        $page = $app->page('home');
        $originalSlug = $page->slug('en');
        $translator = new Translator($page);
        $translator->translateSlug('en', 'de');

        $this->assertSame($originalSlug, $translator->model()->slug('en'));
    }

    #[Test]
    public function translates_regular_page_slug(): void
    {
        $app = $this->appWithScalarFieldPage();
        $page = $app->page('about');
        $translator = new Translator($page);
        $translator->translateSlug('en', 'de');

        $this->assertSame('de-about', $translator->model()->slug('en'));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function scalarTextFieldCases(): array
    {
        return [
            'tags' => ['tags', 'tag1, tag2'],
            'list' => ['list', 'item1, item2'],
            'writer' => ['writer', 'Writer content'],
        ];
    }

    #[Test]
    #[DataProvider('scalarTextFieldCases')]
    public function translates_scalar_text_field_types(string $field, string $original): void
    {
        $app = $this->appWithScalarFieldPage();
        $page = $app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $this->assertSame("[de]$original", $translator->model()->content('en')->get($field)->value());
    }

    #[Test]
    public function translates_nested_block_content(): void
    {
        $app = $this->appWithBlocksPage();
        $page = $app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $blocks = Json::decode($translator->model()->content('en')->get('blocks')->value());

        $this->assertSame('[de]Block content', $blocks[0]['content']['text']);
        $this->assertSame('[de]Block heading', $blocks[1]['content']['title']);
        $this->assertSame('h2', $blocks[1]['content']['level']);
    }

    #[Test]
    public function translates_blocks_inside_container_blocks(): void
    {
        $app = $this->appWithBlocksPage();
        $page = $app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $blocks = Json::decode($translator->model()->content('en')->get('blocks')->value());
        $nestedBlocks = Json::decode($blocks[3]['content']['blocks']);

        $this->assertSame('[de]Container heading', $blocks[3]['content']['heading']);
        $this->assertSame('[de]Nested block text', $nestedBlocks[0]['content']['text']);
    }

    #[Test]
    public function translates_structure_field_entries(): void
    {
        $app = $this->appWithNestedFieldsPage();
        $translator = new Translator($app->page('home'));
        $translator->translateContent('en', 'de');

        $structure = Yaml::decode($translator->model()->content('en')->get('structure')->value());
        $this->assertSame('[de]Section 1', $structure[0]['heading']);
        $this->assertSame('[de]Description 1', $structure[0]['description']);
        $this->assertSame('[de]Section 2', $structure[1]['heading']);
        $this->assertSame('[de]Description 2', $structure[1]['description']);
    }

    #[Test]
    public function translates_object_field_properties(): void
    {
        $app = $this->appWithNestedFieldsPage();
        $translator = new Translator($app->page('home'));
        $translator->translateContent('en', 'de');

        $object = Yaml::decode($translator->model()->content('en')->get('object')->value());
        $this->assertSame('[de]Object title', $object['title']);
        $this->assertSame('[de]Object description', $object['description']);
    }

    #[Test]
    public function translates_blocks_inside_layout_columns(): void
    {
        $app = $this->appWithNestedFieldsPage();
        $translator = new Translator($app->page('home'));
        $translator->translateContent('en', 'de');

        $layout = Json::decode($translator->model()->content('en')->get('layout')->value());
        $this->assertSame('[de]Layout block content', $layout[0]['columns'][0]['blocks'][0]['content']['text']);
    }

    #[Test]
    public function respects_include_fields_filter(): void
    {
        $app = $this->appWithFilterableFieldsPage();
        $page = $app->page('home');
        $translator = new Translator($page, [
            'includeFields' => ['text'],
        ]);
        $translator->translateContent('en', 'de');

        $this->assertSame('[de]Welcome to our website', $translator->model()->content('en')->get('text')->value());
        $this->assertSame('tag1, tag2', $translator->model()->content('en')->get('tags')->value());
    }

    #[Test]
    public function does_not_check_nested_field_names_against_include_filter(): void
    {
        $app = $this->appWithFilterableFieldsPage();
        $page = $app->page('home');
        $translator = new Translator($page, [
            'includeFields' => ['structure'],
        ]);
        $translator->translateContent('en', 'de');

        $structure = Yaml::decode($translator->model()->content('en')->get('structure')->value());
        $this->assertSame('[de]Section 1', $structure[0]['heading']);
        $this->assertSame('[de]Description 1', $structure[0]['description']);

        $this->assertSame('Welcome to our website', $translator->model()->content('en')->get('text')->value());
    }

    #[Test]
    public function does_not_translate_kirby_tag_attributes_when_config_is_empty(): void
    {
        $app = $this->appWithKirbyTagsPage();
        $translator = new Translator($app->page('kirbytags'));
        $translator->translateContent('en', 'de');

        $this->assertSame(
            '[de]Visit (link: https://example.com text: our website title: Click here)!',
            $translator->model()->content('en')->get('text')->value()
        );
    }

    #[Test]
    public function translates_configured_kirby_tag_attributes(): void
    {
        $app = $this->appWithKirbyTagsPage();
        $translator = new Translator($app->page('kirbytags'), [
            'kirbyTags' => [
                'link' => ['text', 'title'],
            ],
        ]);
        $translator->translateContent('en', 'de');

        $this->assertSame(
            '[de]Visit (link: https://example.com text: [de]our website title: [de]Click here)!',
            $translator->model()->content('en')->get('text')->value()
        );
    }
}
