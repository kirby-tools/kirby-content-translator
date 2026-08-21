<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\Translation\ExecutionOptions;
use JohannSchopplich\ContentTranslator\Translation\Strategy;
use JohannSchopplich\ContentTranslator\Translation\TranslationUnit;
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

    private static function pluginOptions(\Closure|null $translateFn = null): array
    {
        return [
            'debug' => true,
            'johannschopplich.content-translator' => [
                'translateFn' => $translateFn ?? self::fakeTranslateFn(),
            ],
        ];
    }

    private static function recordingStrategy(): Strategy
    {
        return new class () implements Strategy {
            /** @var list<list<string>> */
            public array $calls = [];

            public function execute(array $units, ExecutionOptions $options): array
            {
                $texts = array_map(static fn (TranslationUnit $unit): string => $unit->text, $units);
                $this->calls[] = $texts;

                return array_map(static fn (string $text): string => '[de]' . $text, $texts);
            }
        };
    }

    private static function mangledPlaceholderStrategy(): Strategy
    {
        return new class () implements Strategy {
            public function execute(array $units, ExecutionOptions $options): array
            {
                return array_map(
                    static fn (TranslationUnit $unit): string => preg_replace('!<c\d+/>\s*!', '', '[de]' . $unit->text),
                    $units,
                );
            }
        };
    }

    private static function blankTranslationStrategy(string $text = ''): Strategy
    {
        return new class ($text) implements Strategy {
            public function __construct(private string $text)
            {
            }

            public function execute(array $units, ExecutionOptions $options): array
            {
                return array_map(fn (): string => $this->text, $units);
            }
        };
    }

    private function appWithUntranslatableFieldsPage(): App
    {
        return new App([
            'languages' => self::threeLanguages(),
            'blueprints' => [
                'pages/default' => [
                    'fields' => [
                        'intro' => ['type' => 'text'],
                        'price' => ['type' => 'text'],
                        'website' => ['type' => 'text'],
                    ],
                ],
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'untranslatable',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => [
                                'intro' => 'Hello',
                                'price' => '49.99',
                                'website' => 'https://example.com',
                            ]],
                        ],
                    ],
                ],
            ],
            'options' => self::pluginOptions(),
        ]);
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

    private function appWithKirbyTagsPage(\Closure|null $translateFn = null): App
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
                    [
                        'slug' => 'tag-attributes',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => [
                                'title' => 'Tag Attributes',
                                'text' => 'Photo (image: photo.jpg alt: 2024 caption: Our team)',
                            ]],
                        ],
                    ],
                ],
            ],
            'options' => self::pluginOptions($translateFn),
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

    #[Test]
    public function translates_scalar_text_field_types(): void
    {
        $app = $this->appWithScalarFieldPage();
        $page = $app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $content = $translator->model()->content('en');

        $this->assertSame('[de]tag1, tag2', $content->get('tags')->value());
        $this->assertSame('[de]item1, item2', $content->get('list')->value());
        $this->assertSame('[de]Writer content', $content->get('writer')->value());
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
    public function translates_nested_field_types_through_the_model(): void
    {
        $app = $this->appWithNestedFieldsPage();
        $translator = new Translator($app->page('home'));
        $translator->translateContent('en', 'de');

        $content = $translator->model()->content('en');

        $structure = Yaml::decode($content->get('structure')->value());
        $this->assertSame('[de]Section 1', $structure[0]['heading']);
        $this->assertSame('[de]Description 1', $structure[0]['description']);
        $this->assertSame('[de]Section 2', $structure[1]['heading']);
        $this->assertSame('[de]Description 2', $structure[1]['description']);

        $object = Yaml::decode($content->get('object')->value());
        $this->assertSame('[de]Object title', $object['title']);
        $this->assertSame('[de]Object description', $object['description']);

        $layout = Json::decode($content->get('layout')->value());
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

    #[Test]
    public function skips_untranslatable_kirby_tag_attributes(): void
    {
        $app = $this->appWithKirbyTagsPage();
        $translator = new Translator($app->page('tag-attributes'), [
            'kirbyTags' => [
                'image' => ['alt', 'caption'],
            ],
        ]);
        $translator->translateContent('en', 'de');

        $this->assertSame(
            '[de]Photo (image: photo.jpg alt: 2024 caption: [de]Our team)',
            $translator->model()->content('en')->get('text')->value()
        );
    }

    #[Test]
    public function translate_texts_sends_only_translatable_entries(): void
    {
        $this->appWithTranslateFn();
        $strategy = self::recordingStrategy();

        $result = Translator::translateTexts(['', 'Hello', '2024', 'https://example.com'], 'de', null, $strategy);

        $this->assertSame([['Hello']], $strategy->calls);
        $this->assertSame(['', '[de]Hello', '2024', 'https://example.com'], $result);
    }

    #[Test]
    public function translate_texts_skips_the_strategy_when_nothing_is_translatable(): void
    {
        $this->appWithTranslateFn();
        $strategy = self::recordingStrategy();

        $result = Translator::translateTexts(['', '2024'], 'de', null, $strategy);

        $this->assertSame([], $strategy->calls);
        $this->assertSame(['', '2024'], $result);
    }

    #[Test]
    public function leaves_untranslatable_fields_untouched_while_translating_the_rest(): void
    {
        $app = $this->appWithUntranslatableFieldsPage();
        $translator = new Translator($app->page('untranslatable'));
        $translator->translateContent('en', 'de');

        $content = $translator->model()->content('en');
        $this->assertSame('[de]Hello', $content->get('intro')->value());
        $this->assertSame('49.99', $content->get('price')->value());
        $this->assertSame('https://example.com', $content->get('website')->value());
    }

    #[Test]
    public function keeps_source_text_when_a_translation_drops_a_placeholder(): void
    {
        $this->appWithTranslateFn();

        $this->assertSame(
            ['Click <c0/> now', '[de]Hello'],
            Translator::translateTexts(
                ['Click <c0/> now', 'Hello'],
                'de',
                null,
                self::mangledPlaceholderStrategy(),
            ),
        );
    }

    #[Test]
    public function fires_translate_warning_hook_on_placeholder_mismatch(): void
    {
        $warnings = [];
        new App([
            'languages' => self::threeLanguages(),
            'hooks' => [
                'content-translator.translate:warning' => function ($unit, $reason, $previous) use (&$warnings) {
                    $warnings[] = [$unit->text, $reason, $previous];
                },
            ],
        ]);

        Translator::translateTexts(['Click <c0/> now'], 'de', null, self::mangledPlaceholderStrategy());

        $this->assertSame([['Click <c0/> now', 'placeholder mismatch', null]], $warnings);
    }

    #[Test]
    public function translate_batch_reports_the_index_and_reason_of_a_unit_it_could_not_translate(): void
    {
        $this->appWithTranslateFn();

        $result = Translator::translateBatch(
            ['Click <c0/> now', 'Hello'],
            'de',
            null,
            self::mangledPlaceholderStrategy(),
        );

        $this->assertSame(['Click <c0/> now', '[de]Hello'], $result->texts);
        $this->assertCount(1, $result->rejections);
        $this->assertSame(0, $result->rejections[0]->index);
        $this->assertSame('placeholder mismatch', $result->rejections[0]->reason);
    }

    #[Test]
    public function translate_batch_omits_an_untranslatable_text_from_rejections(): void
    {
        $this->appWithTranslateFn();

        // Never handed to the strategy, so it is skipped rather than rejected.
        $result = Translator::translateBatch(['2024'], 'de', null, self::recordingStrategy());

        $this->assertSame(['2024'], $result->texts);
        $this->assertSame([], $result->rejections);
    }

    /**
     * @return iterable<string, array{string, string, string|int|null}>
     */
    public static function rejectionReasons(): iterable
    {
        $contract = json_decode(file_get_contents(__DIR__ . '/fixtures/contract.json'), true);

        foreach ($contract['rejectionReasons'] as $case) {
            yield $case['reason'] => [$case['reason'], $case['sourceText'], $case['answer']];
        }
    }

    /**
     * Shared with `contract.test.ts` – a one-sided rename fails here first.
     */
    #[Test]
    #[DataProvider('rejectionReasons')]
    public function names_a_rejection_per_contract_vocabulary(string $reason, string $sourceText, string|int|null $answer): void
    {
        $this->appWithTranslateFn();

        $result = Translator::translateBatch([$sourceText], 'de', null, new class ($answer) implements Strategy {
            public function __construct(private string|int|null $answer)
            {
            }

            public function execute(array $units, ExecutionOptions $options): array
            {
                return [$this->answer];
            }
        });

        $this->assertCount(1, $result->rejections);
        $this->assertSame($reason, $result->rejections[0]->reason);
    }

    #[Test]
    public function translate_batch_reports_a_unit_the_strategy_left_unanswered(): void
    {
        $this->appWithTranslateFn();

        $result = Translator::translateBatch(['Hello'], 'de', null, new class () implements Strategy {
            public function execute(array $units, ExecutionOptions $options): array
            {
                return [];
            }
        });

        $this->assertSame(['Hello'], $result->texts);
        $this->assertCount(1, $result->rejections);
        $this->assertSame('missing translation', $result->rejections[0]->reason);
    }

    #[Test]
    public function restores_the_kirby_tag_when_a_translation_pads_a_placeholder_with_a_space(): void
    {
        // The strategy pads the placeholder it was handed.
        $app = $this->appWithKirbyTagsPage(
            static fn (string $text): string => str_replace('<c0/>', '<c0 />', "[de]$text"),
        );
        $translator = new Translator($app->page('kirbytags'));
        $translator->translateContent('en', 'de');

        $this->assertSame(
            '[de]Visit (link: https://example.com text: our website title: Click here)!',
            $translator->model()->content('en')->get('text')->value()
        );
    }

    #[Test]
    public function keeps_source_text_when_a_translation_is_only_whitespace(): void
    {
        $this->appWithTranslateFn();

        $this->assertSame(
            ['Hello'],
            Translator::translateTexts(
                ['Hello'],
                'de',
                null,
                // A no-break space: `UntranslatableText` would drop this as a source.
                self::blankTranslationStrategy("\u{00A0} "),
            ),
        );
    }

    #[Test]
    public function fires_translate_warning_hook_on_an_empty_translation(): void
    {
        $warnings = [];
        new App([
            'languages' => self::threeLanguages(),
            'hooks' => [
                'content-translator.translate:warning' => function ($unit, $reason, $previous) use (&$warnings) {
                    $warnings[] = [$unit->text, $reason, $previous];
                },
            ],
        ]);

        Translator::translateTexts(['Hello'], 'de', null, self::blankTranslationStrategy());

        $this->assertSame([['Hello', 'empty translation', null]], $warnings);
    }

    #[Test]
    public function keeps_source_text_when_a_strategy_returns_a_non_string(): void
    {
        $warnings = [];
        new App([
            'languages' => self::threeLanguages(),
            'hooks' => [
                'content-translator.translate:warning' => function ($unit, $reason, $previous) use (&$warnings) {
                    $warnings[] = [$unit->text, $reason, $previous];
                },
            ],
        ]);

        $strategy = new class () implements Strategy {
            public function execute(array $units, ExecutionOptions $options): array
            {
                return [123, 'Welt'];
            }
        };

        $result = Translator::translateTexts(['Hello', 'World'], 'de', null, $strategy);

        $this->assertSame(['Hello', 'Welt'], $result);
        $this->assertSame([['Hello', 'non-string translation', null]], $warnings);
    }
}
