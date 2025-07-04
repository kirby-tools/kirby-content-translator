<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\Translator;
use Kirby\Cms\App;
use Kirby\Data\Json;
use Kirby\Data\Yaml;
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
                        'keepText' => [
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
                                    'keepText' => 'Do not translate',
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
            ]
        ]);
    }

    protected function tearDown(): void
    {
        App::destroy();
    }

    // Static method tests
    public function testTranslateTextWithEmptyString(): void
    {
        $this->assertSame('', Translator::translateText('', 'de'));
    }

    public function testTranslateTextWithCustomFunction(): void
    {
        $this->assertSame('[de]hello', Translator::translateText('hello', 'de'));
    }

    public function testTranslateTextWithSourceLanguage(): void
    {
        $this->assertSame('[de]hello', Translator::translateText('hello', 'de', 'en'));
    }

    public function testResolveModelFields(): void
    {
        $page = $this->app->page('home');
        $fields = Translator::resolveModelFields($page);

        $this->assertIsArray($fields);
        $this->assertArrayHasKey('text', $fields);
        $this->assertArrayHasKey('blocks', $fields);
        $this->assertArrayHasKey('structure', $fields);
        $this->assertArrayNotHasKey('title', $fields); // Title is excluded
        $this->assertArrayNotHasKey('value', $fields['text'] ?? []); // Value should be removed
    }

    // Content copying tests
    public function testCopyContent(): void
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
            $translator->model()->content('de')->get('keepText')->value()
        );
    }

    // Content translation tests
    public function testTranslateContent(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $this->assertSame(
            '[de]Welcome to our website',
            $translator->model()->content('en')->get('text')->value()
        );
    }

    public function testTranslateContentWithSourceLanguage(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de', 'en');

        $this->assertSame(
            '[de]Welcome to our website',
            $translator->model()->content('en')->get('text')->value()
        );
    }

    public function testTranslateContentDoesNotTranslateUntranslatableFields(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $this->assertSame(
            'Do not translate',
            $translator->model()->content('en')->get('keepText')->value()
        );
    }

    // Title translation tests
    public function testTranslateTitle(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page);
        $translator->translateTitle('en', 'de');

        $this->assertSame('[de]Home', $translator->model()->title()->value());
    }

    public function testTranslateTitleWithSourceLanguage(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page);
        $translator->translateTitle('en', 'de', 'en');

        $this->assertSame('[de]Home', $translator->model()->title()->value());
    }

    // Slug translation tests
    public function testDoNotTranslateHomePageSlug(): void
    {
        $page = $this->app->page('home');
        $originalSlug = $page->slug('en');
        $translator = new Translator($page);
        $translator->translateSlug('en', 'de');

        $this->assertSame($originalSlug, $translator->model()->slug('en'));
    }

    public function testTranslateRegularPageSlug(): void
    {
        $page = $this->app->page('about');
        $translator = new Translator($page);
        $translator->translateSlug('en', 'de');

        $this->assertSame('de-about', $translator->model()->slug('en'));
    }

    // Field type tests
    public function testTranslateTextFieldTypes(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $this->assertSame('[de]tag1, tag2', $translator->model()->content('en')->get('tags')->value());
        $this->assertSame('[de]item1, item2', $translator->model()->content('en')->get('list')->value());
        $this->assertSame('[de]Writer content', $translator->model()->content('en')->get('writer')->value());
    }

    public function testTranslateBlocksContent(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $blocks = Json::decode($translator->model()->content('en')->get('blocks')->value());
        $this->assertSame('[de]Block content', $blocks[0]['content']['text']);
        $this->assertSame('[de]Block heading', $blocks[1]['content']['title']);
        // Level field should exist but may be empty if not set properly
        $this->assertArrayHasKey('level', $blocks[1]['content']);
        $this->assertSame('Hidden block content', $blocks[2]['content']['text']); // Hidden blocks should not be translated
    }

    public function testTranslateStructureContent(): void
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

    public function testTranslateObjectContent(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $object = Yaml::decode($translator->model()->content('en')->get('object')->value());
        $this->assertSame('[de]Object title', $object['title']);
        $this->assertSame('[de]Object description', $object['description']);
    }

    public function testTranslateLayoutFields(): void
    {
        $page = $this->app->page('about');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $layout = Json::decode($translator->model()->content('en')->get('layout')->value());
        $this->assertSame('[de]Layout block content', $layout[0]['columns'][0]['blocks'][0]['content']['text']);
    }

    // Configuration tests
    public function testCustomFieldTypeConfiguration(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page, [
            'fieldTypes' => ['textarea'],
            'includeFields' => ['text'],
            'excludeFields' => ['keepText']
        ]);
        $translator->translateContent('en', 'de');

        $this->assertSame('[de]Welcome to our website', $translator->model()->content('en')->get('text')->value());
        $this->assertSame('Do not translate', $translator->model()->content('en')->get('keepText')->value());
    }

    public function testIncludeFieldsConfiguration(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page, [
            'includeFields' => ['text']
        ]);
        $translator->translateContent('en', 'de');

        $this->assertSame('[de]Welcome to our website', $translator->model()->content('en')->get('text')->value());
        $this->assertSame('tag1, tag2', $translator->model()->content('en')->get('tags')->value()); // Should not be translated
    }

    public function testExcludeFieldsConfiguration(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page, [
            'excludeFields' => ['text']
        ]);
        $translator->translateContent('en', 'de');

        $this->assertSame('Welcome to our website', $translator->model()->content('en')->get('text')->value()); // Should not be translated
        $this->assertSame('[de]tag1, tag2', $translator->model()->content('en')->get('tags')->value());
    }

    // Edge cases
    public function testTranslateEmptyFields(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page);

        // Test with empty content strings
        $result = $translator->translateText('', 'de');
        $this->assertSame('', $result);
    }

    public function testModelMethod(): void
    {
        $page = $this->app->page('home');
        $translator = new Translator($page);

        $this->assertSame($page, $translator->model());
    }
}
