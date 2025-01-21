<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\Translator;
use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Data\Json;
use Kirby\Data\Yaml;
use PHPUnit\Framework\TestCase;

final class ContentTranslatorTest extends TestCase
{
    protected App $app;
    protected Page $page;

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
                                    'blocks' => Json::encode([
                                        [
                                            'type' => 'text',
                                            'id' => '1234',
                                            'content' => [
                                                'text' => 'Block content'
                                            ]
                                        ]
                                    ]),
                                    'structure' => Yaml::encode([
                                        [
                                            'heading' => 'Section 1',
                                            'description' => 'Description 1'
                                        ]
                                    ])
                                ]
                            ],
                            [
                                'code' => 'de',
                                'slug' => 'startseite',
                                'content' => []
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

    public function testTranslateTextWithEmptyString(): void
    {
        $this->assertSame('', Translator::translateText('', 'de'));
    }

    public function testTranslateTextWithCustomFunction(): void
    {
        $this->assertSame('[de]hello', Translator::translateText('hello', 'de'));
    }

    public function testCopyContent(): void
    {
        $page = $this->app->clone()->page('home');

        $translator = new Translator($page);
        $translator->copyContent('de', 'en');

        $this->assertSame(
            'Welcome to our website',
            $translator->model()->content('de')->get('text')->value()
        );
    }

    public function testTranslateContent(): void
    {
        $page = $this->app->clone()->page('home');

        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $this->assertSame(
            '[de]Welcome to our website',
            $translator->model()->content('en')->get('text')->value()
        );
    }

    public function testTranslateContentWithSourceLanguage(): void
    {
        $page = $this->app->clone()->page('home');

        $translator = new Translator($page);
        $translator->translateContent('en', 'de', 'en');

        $this->assertSame(
            '[de]Welcome to our website',
            $translator->model()->content('en')->get('text')->value()
        );
    }

    public function testTranslateTitle(): void
    {
        $page = $this->app->clone()->page('home');

        $translator = new Translator($page);
        $translator->translateTitle('en', 'de');

        $this->assertSame('[de]Home', $translator->model()->title()->value());
    }

    public function testDoNotTranslateHomePageSlug(): void
    {
        $page = $this->app->clone()->page('home');
        $originalSlug = $page->slug('en');

        $translator = new Translator($page);
        $translator->translateSlug('en', 'de');

        $this->assertSame($originalSlug, $translator->model()->slug('en'));
    }

    public function testTranslateBlocksContent(): void
    {
        $page = $this->app->clone()->page('home');

        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $blocks = Json::decode($translator->model()->content('en')->get('blocks')->value());
        $this->assertSame('[de]Block content', $blocks[0]['content']['text']);
    }

    public function testTranslateStructureContent(): void
    {
        $page = $this->app->clone()->page('home');

        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $structure = Yaml::decode($translator->model()->content('en')->get('structure')->value());
        $this->assertSame('[de]Section 1', $structure[0]['heading']);
        $this->assertSame('[de]Description 1', $structure[0]['description']);
    }

    public function testDoNotTranslateUntranslatableFields(): void
    {
        $page = $this->app->clone()->page('home');

        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $this->assertSame(
            'Do not translate',
            $translator->model()->content('en')->get('keepText')->value()
        );
    }

    public function testCustomFieldTypeConfiguration(): void
    {
        $page = $this->app->clone()->page('home');

        $translator = new Translator($page, [
            'fieldTypes' => ['textarea'],
            'includeFields' => ['text'],
            'excludeFields' => ['keepText']
        ]);

        $translator->translateContent('en', 'de');

        // Only text should be translated
        $this->assertSame('[de]Welcome to our website', $translator->model()->content('en')->get('text')->value());
        $this->assertSame('Do not translate', $translator->model()->content('en')->get('keepText')->value());
    }
}
