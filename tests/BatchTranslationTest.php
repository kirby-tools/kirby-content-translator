<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\DeepL;
use JohannSchopplich\ContentTranslator\Translator;
use Kirby\Cms\App;
use Kirby\Data\Json;
use Kirby\Data\Yaml;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class BatchTranslationTest extends TestCase
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
                            'type' => 'text',
                            'translate' => true
                        ],
                        'tags' => [
                            'type' => 'tags',
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
                                                    'type' => 'text',
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
                                                ]
                                            ]
                                        ]
                                    ]
                                ],
                                'section' => [
                                    'tabs' => [
                                        'content' => [
                                            'fields' => [
                                                'heading' => [
                                                    'type' => 'text',
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
                                                                            'type' => 'text',
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
                                    'type' => 'text',
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
                                    'type' => 'text',
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
                                                    'type' => 'text',
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
                                    'text' => 'Welcome',
                                    'tags' => 'tag1, tag2',
                                    'blocks' => Json::encode([
                                        [
                                            'type' => 'text',
                                            'id' => '1234',
                                            'content' => [
                                                'text' => 'Block text'
                                            ]
                                        ],
                                        [
                                            'type' => 'heading',
                                            'id' => '5678',
                                            'content' => [
                                                'title' => 'Block heading'
                                            ]
                                        ],
                                        [
                                            'type' => 'section',
                                            'id' => 'section1',
                                            'content' => [
                                                'heading' => 'Section heading',
                                                'blocks' => Json::encode([
                                                    [
                                                        'type' => 'text',
                                                        'id' => 'nested1',
                                                        'content' => [
                                                            'text' => 'Nested block text'
                                                        ]
                                                    ]
                                                ])
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
                    'DeepL.apiKey' => 'test-key:fx'
                ]
            ]
        ]);
    }

    protected function tearDown(): void
    {
        App::destroy();
    }

    private function mockDeepL(callable $translateManyCallback, bool $expectOnce = true): void
    {
        $reflection = new \ReflectionClass(DeepL::class);
        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setAccessible(true);

        $mockDeepL = $this->createMock(DeepL::class);

        if ($expectOnce) {
            $mockDeepL->expects($this->once())
                ->method('translateMany')
                ->willReturnCallback($translateManyCallback);
        } else {
            $mockDeepL->method('translateMany')
                ->willReturnCallback($translateManyCallback);
        }

        $instanceProperty->setValue(null, $mockDeepL);
    }

    public function testBatchesMultipleFieldsIntoSingleCall(): void
    {
        $translateManyCallCount = 0;
        $batchSizes = [];

        $this->mockDeepL(function ($texts, $target) use (&$translateManyCallCount, &$batchSizes) {
            $translateManyCallCount++;
            $batchSizes[] = count($texts);
            return array_map(fn ($t) => "[{$target}]{$t}", $texts);
        });

        $page = $this->app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        // Should batch all text fields (text, tags, block texts, structure fields, object fields) into one call
        $this->assertSame(1, $translateManyCallCount, 'Should make only one translateMany call');
        $this->assertGreaterThan(1, $batchSizes[0], 'Should batch multiple texts together');

        // Verify translations were applied
        $model = $translator->model();
        $this->assertSame('[de]Welcome', $model->content('en')->get('text')->value());
        $this->assertSame('[de]tag1, tag2', $model->content('en')->get('tags')->value());
    }

    public function testNestedBlocksAreBatched(): void
    {
        $collectedTexts = [];

        $this->mockDeepL(function ($texts, $target) use (&$collectedTexts) {
            $collectedTexts = $texts;
            return array_map(fn ($t) => "[{$target}]{$t}", $texts);
        });

        $page = $this->app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        // Verify nested block text was collected
        $this->assertContains('Nested block text', $collectedTexts, 'Nested block text should be batched');
        $this->assertContains('Section heading', $collectedTexts, 'Section heading should be batched');

        // Verify nested blocks were translated
        $blocks = Json::decode($translator->model()->content('en')->get('blocks')->value());
        $nestedBlocksJson = $blocks[2]['content']['blocks'];
        $nestedBlocks = Json::decode($nestedBlocksJson);

        $this->assertSame('[de]Section heading', $blocks[2]['content']['heading']);
        $this->assertSame('[de]Nested block text', $nestedBlocks[0]['content']['text']);
    }

    public function testLayoutBlocksAreBatched(): void
    {
        $collectedTexts = [];

        $this->mockDeepL(function ($texts, $target) use (&$collectedTexts) {
            $collectedTexts = $texts;
            return array_map(fn ($t) => "[{$target}]{$t}", $texts);
        });

        $page = $this->app->page('about');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        // Verify layout block text was collected
        $this->assertContains('Layout block content', $collectedTexts, 'Layout block text should be batched');

        // Verify layout blocks were translated
        $layout = Json::decode($translator->model()->content('en')->get('layout')->value());
        $this->assertSame('[de]Layout block content', $layout[0]['columns'][0]['blocks'][0]['content']['text']);
    }

    public function testStructureFieldsAreBatched(): void
    {
        $collectedTexts = [];

        $this->mockDeepL(function ($texts, $target) use (&$collectedTexts) {
            $collectedTexts = $texts;
            return array_map(fn ($t) => "[{$target}]{$t}", $texts);
        });

        $page = $this->app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        // Verify structure fields were collected
        $this->assertContains('Section 1', $collectedTexts, 'Structure heading should be batched');
        $this->assertContains('Description 1', $collectedTexts, 'Structure description should be batched');

        // Verify structure was translated
        $structure = Yaml::decode($translator->model()->content('en')->get('structure')->value());
        $this->assertSame('[de]Section 1', $structure[0]['heading']);
        $this->assertSame('[de]Description 1', $structure[0]['description']);
    }

    public function testObjectFieldsAreBatched(): void
    {
        $collectedTexts = [];

        $this->mockDeepL(function ($texts, $target) use (&$collectedTexts) {
            $collectedTexts = $texts;
            return array_map(fn ($t) => "[{$target}]{$t}", $texts);
        });

        $page = $this->app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        // Verify object fields were collected
        $this->assertContains('Object title', $collectedTexts, 'Object title should be batched');
        $this->assertContains('Object description', $collectedTexts, 'Object description should be batched');

        // Verify object was translated
        $object = Yaml::decode($translator->model()->content('en')->get('object')->value());
        $this->assertSame('[de]Object title', $object['title']);
        $this->assertSame('[de]Object description', $object['description']);
    }

    public function testLargeNumberOfFieldsAreBatchedInSingleCall(): void
    {
        // Create 60 fields to test that all are sent in one call
        $fields = [];
        $content = [];
        for ($i = 1; $i <= 60; $i++) {
            $fields["field{$i}"] = ['type' => 'text'];
            $content["field{$i}"] = "Text {$i}";
        }

        $batchApp = new App([
            'languages' => [
                [
                    'code' => 'en',
                    'name' => 'English',
                    'default' => true
                ],
                [
                    'code' => 'de',
                    'name' => 'Deutsch'
                ]
            ],
            'blueprints' => [
                'pages/batch-test' => [
                    'fields' => $fields
                ]
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'batch-test',
                        'template' => 'batch-test',
                        'translations' => [
                            [
                                'code' => 'en',
                                'content' => $content
                            ]
                        ]
                    ]
                ]
            ],
            'options' => [
                'debug' => true,
                'johannschopplich.content-translator' => [
                    'DeepL.apiKey' => 'test-key:fx'
                ]
            ]
        ]);

        $translateManyCallCount = 0;
        $batchSizes = [];

        $this->mockDeepL(function ($texts, $target) use (&$translateManyCallCount, &$batchSizes) {
            $translateManyCallCount++;
            $batchSizes[] = count($texts);
            return array_map(fn ($t) => "[{$target}]{$t}", $texts);
        });

        $page = $batchApp->page('batch-test');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        // All 60 items should be sent in one call (DeepL handles internal chunking to 50)
        $this->assertSame(1, $translateManyCallCount, 'Should call translateMany once');
        $this->assertSame(60, $batchSizes[0], 'Should send all 60 items in one batch');

        // Verify translations were applied
        $model = $translator->model();
        $this->assertSame('[de]Text 1', $model->content('en')->get('field1')->value());
        $this->assertSame('[de]Text 60', $model->content('en')->get('field60')->value());

        App::destroy();
    }
}
