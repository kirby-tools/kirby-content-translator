<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\Translator;
use JohannSchopplich\KirbyTools\FieldResolver;
use Kirby\Cms\App;
use Kirby\Data\Json;
use Kirby\Data\Yaml;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TranslatorTest extends TestCase
{
    protected App $app;

    protected function setUp(): void
    {
        $this->app = new App([
            'languages' => [
                [
                    'code' => 'en',
                    'name' => 'English',
                    'default' => true
                ],
                [
                    'code' => 'de',
                    'name' => 'Deutsch'
                ],
                [
                    'code' => 'fr',
                    'name' => 'Français'
                ]
            ],
            'blueprints' => [
                'pages/default' => [
                    'fields' => [
                        'title' => [
                            'type' => 'text',
                            'translate' => true
                        ],
                        'text' => [
                            'type' => 'textarea',
                            'translate' => true
                        ],
                        'untranslatableText' => [
                            'type' => 'text',
                            'translate' => false
                        ],
                        'tags' => [
                            'type' => 'tags',
                            'translate' => true
                        ],
                        'list' => [
                            'type' => 'list',
                            'translate' => true
                        ],
                        'writer' => [
                            'type' => 'writer',
                            'translate' => true
                        ],
                        'blocks' => [
                            'type' => 'blocks',
                            'translate' => true,
                            'fieldsets' => [
                                'text' => [
                                    'tabs' => [
                                        'content' => [
                                            'fields' => [
                                                'text' => [
                                                    'type' => 'textarea',
                                                    'translate' => true
                                                ]
                                            ]
                                        ]
                                    ]
                                ],
                                'heading' => [
                                    'tabs' => [
                                        'content' => [
                                            'fields' => [
                                                'title' => [
                                                    'type' => 'text',
                                                    'translate' => true
                                                ],
                                                'level' => [
                                                    'type' => 'select',
                                                    'translate' => false
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'structure' => [
                            'type' => 'structure',
                            'translate' => true,
                            'fields' => [
                                'heading' => [
                                    'type' => 'text',
                                    'translate' => true
                                ],
                                'description' => [
                                    'type' => 'textarea',
                                    'translate' => true
                                ]
                            ]
                        ],
                        'object' => [
                            'type' => 'object',
                            'translate' => true,
                            'fields' => [
                                'title' => [
                                    'type' => 'text',
                                    'translate' => true
                                ],
                                'description' => [
                                    'type' => 'textarea',
                                    'translate' => true
                                ]
                            ]
                        ]
                    ]
                ],
                'pages/article' => [
                    'fields' => [
                        'title' => [
                            'type' => 'text',
                            'translate' => true
                        ],
                        'layout' => [
                            'type' => 'layout',
                            'translate' => true,
                            'fieldsets' => [
                                'text' => [
                                    'tabs' => [
                                        'content' => [
                                            'fields' => [
                                                'text' => [
                                                    'type' => 'textarea',
                                                    'translate' => true
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
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
                                    'blocks' => Json::encode([
                                        [
                                            'type' => 'text',
                                            'id' => '1234',
                                            'content' => [
                                                'text' => 'Block content'
                                            ]
                                        ],
                                        [
                                            'type' => 'heading',
                                            'id' => '5678',
                                            'content' => [
                                                'title' => 'Block heading',
                                                'level' => 'h2'
                                            ]
                                        ],
                                        [
                                            'type' => 'text',
                                            'id' => '9999',
                                            'isHidden' => true,
                                            'content' => [
                                                'text' => 'Hidden block content'
                                            ]
                                        ]
                                    ]),
                                    'structure' => Yaml::encode([
                                        [
                                            'heading' => 'Section 1',
                                            'description' => 'Description 1'
                                        ],
                                        [
                                            'heading' => 'Section 2',
                                            'description' => 'Description 2'
                                        ]
                                    ]),
                                    'object' => Yaml::encode([
                                        'title' => 'Object title',
                                        'description' => 'Object description'
                                    ])
                                ]
                            ],
                            [
                                'code' => 'de',
                                'slug' => 'startseite',
                                'content' => []
                            ]
                        ]
                    ],
                    [
                        'slug' => 'kirbytags',
                        'template' => 'default',
                        'translations' => [
                            [
                                'code' => 'en',
                                'content' => [
                                    'title' => 'KirbyTags Test',
                                    'text' => 'Visit (link: https://example.com text: our website title: Click here)!'
                                ]
                            ]
                        ]
                    ],
                    [
                        'slug' => 'about',
                        'template' => 'article',
                        'translations' => [
                            [
                                'code' => 'en',
                                'content' => [
                                    'title' => 'About Us',
                                    'layout' => Json::encode([
                                        [
                                            'id' => 'layout1',
                                            'attrs' => [],
                                            'columns' => [
                                                [
                                                    'id' => 'col1',
                                                    'blocks' => [
                                                        [
                                                            'type' => 'text',
                                                            'id' => 'block1',
                                                            'content' => [
                                                                'text' => 'Layout block content'
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ])
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'options' => [
                'debug' => true,
                'johannschopplich.content-translator' => [
                    'translateFn' => function (string $text, string $toLanguageCode, string|null $fromLanguageCode = null): string {
                        return "[$toLanguageCode]$text";
                    }
                ]
            ],
            'tags' => [
                'link' => [
                    'attr' => ['text', 'title', 'class', 'rel', 'target', 'lang', 'role'],
                    'html' => function ($tag) {
                        return '<a href="' . $tag->link . '">' . ($tag->text ?? $tag->link) . '</a>';
                    }
                ]
            ]
        ]);
    }

    protected function tearDown(): void
    {
        App::destroy();
    }

    #[Test]
    public function translate_text_with_empty_string(): void
    {
        $this->assertSame('', Translator::translateText('', 'de'));
    }

    #[Test]
    public function translate_text_with_custom_function(): void
    {
        $this->assertSame('[de]hello', Translator::translateText('hello', 'de'));
    }

    #[Test]
    public function translate_text_with_source_language(): void
    {
        $this->assertSame('[de]hello', Translator::translateText('hello', 'de', 'en'));
    }

    #[Test]
    public function resolve_model_fields(): void
    {
        $page = $this->app->page('home');
        $fields = FieldResolver::resolveModelFields($page);

        $this->assertIsArray($fields);
        $this->assertArrayHasKey('text', $fields);
        $this->assertArrayHasKey('blocks', $fields);
        $this->assertArrayHasKey('structure', $fields);
        $this->assertArrayNotHasKey('title', $fields); // Title is excluded
        $this->assertArrayNotHasKey('value', $fields['text'] ?? []); // Value should be removed
    }

    #[Test]
    public function copy_content(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page);
        $translator->copyContent('de', 'en');

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
    public function translate_content(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $this->assertSame(
            '[de]Welcome to our website',
            $translator->model()->content('en')->get('text')->value()
        );
    }

    #[Test]
    public function translate_content_with_source_language(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de', 'en');

        $this->assertSame(
            '[de]Welcome to our website',
            $translator->model()->content('en')->get('text')->value()
        );
    }

    #[Test]
    public function skips_fields_with_translate_false(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $this->assertSame(
            'Do not translate',
            $translator->model()->content('en')->get('untranslatableText')->value()
        );
    }

    #[Test]
    public function translate_title(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page);
        $translator->translateTitle('en', 'de');

        $this->assertSame('[de]Home', $translator->model()->title()->value());
    }

    #[Test]
    public function translate_title_with_source_language(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page);
        $translator->translateTitle('en', 'de', 'en');

        $this->assertSame('[de]Home', $translator->model()->title()->value());
    }

    #[Test]
    public function does_not_translate_home_page_slug(): void
    {
        $page = $this->app->page('home');
        $originalSlug = $page->slug('en');
        $translator = new Translator($page);
        $translator->translateSlug('en', 'de');

        $this->assertSame($originalSlug, $translator->model()->slug('en'));
    }

    #[Test]
    public function translate_regular_page_slug(): void
    {
        $page = $this->app->page('about');
        $translator = new Translator($page);
        $translator->translateSlug('en', 'de');

        $this->assertSame('de-about', $translator->model()->slug('en'));
    }

    #[Test]
    public function translate_text_field_types(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $this->assertSame('[de]tag1, tag2', $translator->model()->content('en')->get('tags')->value());
        $this->assertSame('[de]item1, item2', $translator->model()->content('en')->get('list')->value());
        $this->assertSame('[de]Writer content', $translator->model()->content('en')->get('writer')->value());
    }

    #[Test]
    public function traverses_blocks_recursively(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $blocks = Json::decode($translator->model()->content('en')->get('blocks')->value());
        $this->assertSame('[de]Block content', $blocks[0]['content']['text']);
        $this->assertSame('[de]Block heading', $blocks[1]['content']['title']);
        // Level field should exist but may be empty if not set properly
        $this->assertArrayHasKey('level', $blocks[1]['content']);
    }

    #[Test]
    public function skips_hidden_blocks(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $blocks = Json::decode($translator->model()->content('en')->get('blocks')->value());
        // Hidden block should not be translated
        $this->assertSame('Hidden block content', $blocks[2]['content']['text']);
    }

    #[Test]
    public function traverses_structure_recursively(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $structure = Yaml::decode($translator->model()->content('en')->get('structure')->value());
        $this->assertSame('[de]Section 1', $structure[0]['heading']);
        $this->assertSame('[de]Description 1', $structure[0]['description']);
        $this->assertSame('[de]Section 2', $structure[1]['heading']);
        $this->assertSame('[de]Description 2', $structure[1]['description']);
    }

    #[Test]
    public function traverses_object_recursively(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $object = Yaml::decode($translator->model()->content('en')->get('object')->value());
        $this->assertSame('[de]Object title', $object['title']);
        $this->assertSame('[de]Object description', $object['description']);
    }

    #[Test]
    public function traverses_layout_recursively(): void
    {
        $page = $this->app->page('about');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $layout = Json::decode($translator->model()->content('en')->get('layout')->value());
        $this->assertSame('[de]Layout block content', $layout[0]['columns'][0]['blocks'][0]['content']['text']);
    }

    #[Test]
    public function custom_field_type_configuration(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page, [
            'fieldTypes' => ['textarea'],
            'includeFields' => ['text'],
            'excludeFields' => ['untranslatableText']
        ]);
        $translator->translateContent('en', 'de');

        $this->assertSame('[de]Welcome to our website', $translator->model()->content('en')->get('text')->value());
        $this->assertSame('Do not translate', $translator->model()->content('en')->get('untranslatableText')->value());
    }

    #[Test]
    public function respects_include_fields_filter(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page, [
            'includeFields' => ['text']
        ]);
        $translator->translateContent('en', 'de');

        $this->assertSame('[de]Welcome to our website', $translator->model()->content('en')->get('text')->value());
        $this->assertSame('tag1, tag2', $translator->model()->content('en')->get('tags')->value());
    }

    #[Test]
    public function respects_exclude_fields_filter(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page, [
            'excludeFields' => ['text']
        ]);
        $translator->translateContent('en', 'de');

        $this->assertSame('Welcome to our website', $translator->model()->content('en')->get('text')->value());
        $this->assertSame('[de]tag1, tag2', $translator->model()->content('en')->get('tags')->value());
    }

    #[Test]
    public function traverses_structure_with_include_fields_filter(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page, [
            'includeFields' => ['structure']
        ]);
        $translator->translateContent('en', 'de');

        // Inner field names (`heading`, `description`) must not be checked against `includeFields`
        $structure = Yaml::decode($translator->model()->content('en')->get('structure')->value());
        $this->assertSame('[de]Section 1', $structure[0]['heading']);
        $this->assertSame('[de]Description 1', $structure[0]['description']);

        // Other top-level fields must **not** be translated
        $this->assertSame('Welcome to our website', $translator->model()->content('en')->get('text')->value());
    }

    #[Test]
    public function skips_empty_values(): void
    {
        $result = Translator::translateText('', 'de');
        $this->assertSame('', $result);

        $result = Translator::translateText('   ', 'de');
        $this->assertSame('   ', $result);
    }

    #[Test]
    public function skips_whitespace_only_content(): void
    {
        $result = Translator::translateText('   ', 'de');
        $this->assertSame('   ', $result);

        $result = Translator::translateText("\n\t", 'de');
        $this->assertSame("\n\t", $result);
    }

    #[Test]
    public function skips_pure_numeric_values(): void
    {
        $result = Translator::translateText('123', 'de');
        $this->assertSame('123', $result);

        $result = Translator::translateText('45.67', 'de');
        $this->assertSame('45.67', $result);

        $result = Translator::translateText('-99', 'de');
        $this->assertSame('-99', $result);

        $result = Translator::translateText('1.5e10', 'de');
        $this->assertSame('1.5e10', $result);

        // Should translate: contains text
        $result = Translator::translateText('Product 123', 'de');
        $this->assertSame('[de]Product 123', $result);
    }

    #[Test]
    public function skips_url_values(): void
    {
        $result = Translator::translateText('https://example.com', 'de');
        $this->assertSame('https://example.com', $result);

        $result = Translator::translateText('http://localhost:3000/path?query=1', 'de');
        $this->assertSame('http://localhost:3000/path?query=1', $result);

        // Should translate: contains additional text
        $result = Translator::translateText('Visit https://example.com today', 'de');
        $this->assertSame('[de]Visit https://example.com today', $result);
    }

    #[Test]
    public function model_returns_original_page(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page);

        $this->assertSame($page, $translator->model());
    }

    #[Test]
    public function kirby_tags_translation_with_empty_config(): void
    {
        $page = $this->app->page('kirbytags');
        $translator = new Translator($page);

        $translator->translateContent('en', 'de');
        $content = $translator->model()->content('en')->get('text')->value();

        $this->assertStringContainsString('(link: https://example.com text: our website title: Click here)', $content);
        $this->assertStringContainsString('[de]Visit', $content);
        $this->assertStringNotContainsString('[de]our website', $content); // KirbyTag content should not be translated
    }

    #[Test]
    public function kirby_tags_link_translation(): void
    {
        $page = $this->app->page('kirbytags');
        $translator = new Translator($page, [
            'kirbyTags' => [
                'link' => ['text', 'title']
            ]
        ]);

        $translator->translateContent('en', 'de');
        $content = $translator->model()->content('en')->get('text')->value();

        $this->assertStringContainsString('https://example.com', $content);
        $this->assertStringContainsString('text: [de]our website', $content);
        $this->assertStringContainsString('title: [de]Click here', $content);
        $this->assertStringContainsString('[de]Visit', $content);
    }

    #[Test]
    public function kirby_tags_email_translation(): void
    {
        $page = $this->app->page('home');
        $content = $page->content('en')->get('text')->value();

        $translator = new Translator($page, [
            'kirbyTags' => [
                'link' => ['text'] // Test that only configured tags work
            ]
        ]);

        $translator->translateContent('en', 'de');
        $translatedContent = $translator->model()->content('en')->get('text')->value();

        $this->assertStringContainsString('[de]Welcome to our website', $translatedContent);
    }
}
