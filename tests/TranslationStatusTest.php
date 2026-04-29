<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\TranslationStatus;
use Kirby\Cms\App;
use Kirby\Cms\Pages;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TranslationStatusTest extends TestCase
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
                        'text' => [
                            'type' => 'textarea',
                            'translate' => true
                        ],
                        'tags' => [
                            'type' => 'tags',
                            'translate' => true
                        ],
                        'untranslatable' => [
                            'type' => 'text',
                            'translate' => false
                        ]
                    ]
                ],
                'pages/no-translatable' => [
                    'fields' => [
                        'slug' => [
                            'type' => 'slug'
                        ],
                        'toggle' => [
                            'type' => 'toggle'
                        ]
                    ]
                ]
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'fully-translated',
                        'template' => 'default',
                        'translations' => [
                            [
                                'code' => 'en',
                                'content' => [
                                    'title' => 'Fully Translated',
                                    'text' => 'English text',
                                    'tags' => 'tag1, tag2',
                                    'untranslatable' => 'Do not translate'
                                ]
                            ],
                            [
                                'code' => 'de',
                                'content' => [
                                    'title' => 'Voll übersetzt',
                                    'text' => 'Deutscher Text',
                                    'tags' => 'tag1, tag2'
                                ]
                            ],
                            [
                                'code' => 'fr',
                                'content' => [
                                    'title' => 'Entièrement traduit',
                                    'text' => 'Texte français',
                                    'tags' => 'tag1, tag2'
                                ]
                            ]
                        ]
                    ],
                    [
                        'slug' => 'partially-translated',
                        'template' => 'default',
                        'translations' => [
                            [
                                'code' => 'en',
                                'content' => [
                                    'title' => 'Partial',
                                    'text' => 'English text',
                                    'tags' => 'tag1'
                                ]
                            ],
                            [
                                'code' => 'de',
                                'content' => [
                                    'title' => 'Teilweise',
                                    'text' => 'Deutscher Text',
                                    'tags' => ''
                                ]
                            ],
                            [
                                'code' => 'fr',
                                'content' => [
                                    'title' => 'Partiel',
                                    'text' => '',
                                    'tags' => ''
                                ]
                            ]
                        ]
                    ],
                    [
                        'slug' => 'untranslated',
                        'template' => 'default',
                        'translations' => [
                            [
                                'code' => 'en',
                                'content' => [
                                    'title' => 'Untranslated',
                                    'text' => 'English only',
                                    'tags' => 'tag1'
                                ]
                            ]
                        ]
                    ],
                    [
                        'slug' => 'no-fields',
                        'template' => 'no-translatable',
                        'translations' => [
                            [
                                'code' => 'en',
                                'content' => [
                                    'title' => 'No translatable fields'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);
    }

    protected function tearDown(): void
    {
        App::destroy();
    }

    #[Test]
    public function fully_translated_page_returns_100_percent(): void
    {
        $page = $this->app->page('fully-translated');
        $status = new TranslationStatus(new Pages([$page]));
        $pageStatus = $status->pageStatus($page);

        $this->assertSame(2, $pageStatus['de']['totalFields']);
        $this->assertSame(2, $pageStatus['de']['translatedFields']);
        $this->assertSame(2, $pageStatus['fr']['totalFields']);
        $this->assertSame(2, $pageStatus['fr']['translatedFields']);
    }

    #[Test]
    public function partially_translated_page_counts_only_filled_fields(): void
    {
        $page = $this->app->page('partially-translated');
        $status = new TranslationStatus(new Pages([$page]));
        $pageStatus = $status->pageStatus($page);

        // DE: text filled, tags empty
        $this->assertSame(2, $pageStatus['de']['totalFields']);
        $this->assertSame(1, $pageStatus['de']['translatedFields']);

        // FR: text empty, tags empty
        $this->assertSame(2, $pageStatus['fr']['totalFields']);
        $this->assertSame(0, $pageStatus['fr']['translatedFields']);
    }

    #[Test]
    public function untranslated_page_returns_zero_translated_fields(): void
    {
        $page = $this->app->page('untranslated');
        $status = new TranslationStatus(new Pages([$page]));
        $pageStatus = $status->pageStatus($page);

        $this->assertSame(2, $pageStatus['de']['totalFields']);
        $this->assertSame(0, $pageStatus['de']['translatedFields']);
        $this->assertSame(2, $pageStatus['fr']['totalFields']);
        $this->assertSame(0, $pageStatus['fr']['translatedFields']);
    }

    #[Test]
    public function skips_pages_without_translatable_fields(): void
    {
        $page = $this->app->page('no-fields');
        $status = new TranslationStatus(new Pages([$page]));
        $pageStatus = $status->pageStatus($page);

        $this->assertSame([], $pageStatus);
    }

    #[Test]
    public function respects_include_fields_filter(): void
    {
        $page = $this->app->page('fully-translated');
        $status = new TranslationStatus(new Pages([$page]), [
            'includeFields' => ['text']
        ]);
        $pageStatus = $status->pageStatus($page);

        // Only text should be counted
        $this->assertSame(1, $pageStatus['de']['totalFields']);
        $this->assertSame(1, $pageStatus['de']['translatedFields']);
    }

    #[Test]
    public function respects_exclude_fields_filter(): void
    {
        $page = $this->app->page('fully-translated');
        $status = new TranslationStatus(new Pages([$page]), [
            'excludeFields' => ['tags']
        ]);
        $pageStatus = $status->pageStatus($page);

        // Only text should be counted (tags excluded)
        $this->assertSame(1, $pageStatus['de']['totalFields']);
        $this->assertSame(1, $pageStatus['de']['translatedFields']);
    }

    #[Test]
    public function excludes_default_language_from_results(): void
    {
        $page = $this->app->page('fully-translated');
        $status = new TranslationStatus(new Pages([$page]));
        $pageStatus = $status->pageStatus($page);

        $this->assertArrayNotHasKey('en', $pageStatus);
        $this->assertArrayHasKey('de', $pageStatus);
        $this->assertArrayHasKey('fr', $pageStatus);
    }

    #[Test]
    public function tree_status_aggregates_language_totals(): void
    {
        $pages = $this->app->site()->index();
        $status = new TranslationStatus($pages);
        $result = $status->treeStatus();

        $this->assertCount(2, $result['languages']);

        // No-fields page should be auto-excluded from tree
        foreach ($result['tree'] as $entry) {
            $this->assertNotSame('no-fields', $entry['id']);
        }
    }

    #[Test]
    public function tree_status_calculates_percentage_per_language(): void
    {
        $pages = $this->app->site()->index();
        $status = new TranslationStatus($pages);
        $result = $status->treeStatus();

        $languages = array_column($result['languages'], null, 'code');

        // DE: fully-translated (2/2), partially-translated (1/2), untranslated (0/2) = 3/6 = 50%
        $this->assertSame(50, $languages['de']['percentage']);

        // FR: fully-translated (2/2), partially-translated (0/2), untranslated (0/2) = 2/6 = 33%
        $this->assertSame(33, $languages['fr']['percentage']);
    }

    #[Test]
    public function tree_status_lists_incomplete_pages(): void
    {
        $pages = $this->app->site()->index();
        $status = new TranslationStatus($pages);
        $result = $status->treeStatus();

        $incompleteIds = array_column($result['tree'], 'id');

        $this->assertNotContains('fully-translated', $incompleteIds);
        $this->assertContains('partially-translated', $incompleteIds);
        $this->assertContains('untranslated', $incompleteIds);
    }

    #[Test]
    public function respects_custom_pages_scope(): void
    {
        // Only include fully-translated page
        $pages = new Pages([$this->app->page('fully-translated')]);
        $status = new TranslationStatus($pages);
        $result = $status->treeStatus();

        $languages = array_column($result['languages'], null, 'code');

        $this->assertSame(100, $languages['de']['percentage']);
        $this->assertSame(100, $languages['fr']['percentage']);
        $this->assertEmpty($result['tree']);
    }

    #[Test]
    public function tree_status_reports_full_completion_when_no_pages(): void
    {
        $status = new TranslationStatus(new Pages([]));
        $result = $status->treeStatus();

        $this->assertIsArray($result['languages']);
        $this->assertEmpty($result['tree']);

        // Each language should have zero totals but 100% (nothing to translate = complete)
        foreach ($result['languages'] as $language) {
            $this->assertSame(0, $language['totalFields']);
            $this->assertSame(0, $language['translatedFields']);
            $this->assertSame(100, $language['percentage']);
        }
    }

    #[Test]
    public function tree_status_emits_is_fully_untranslated_flag(): void
    {
        $app = new App([
            'languages' => [
                ['code' => 'en', 'name' => 'English', 'default' => true],
                ['code' => 'de', 'name' => 'Deutsch'],
                ['code' => 'fr', 'name' => 'Français']
            ],
            'blueprints' => [
                'pages/default' => [
                    'fields' => ['text' => ['type' => 'text', 'translate' => true]]
                ]
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'all-missing',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => ['text' => 'hello']]
                        ]
                    ],
                    [
                        'slug' => 'one-missing',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => ['text' => 'hello']],
                            ['code' => 'de', 'content' => ['text' => 'hallo']]
                        ]
                    ]
                ]
            ]
        ]);

        $status = new TranslationStatus($app->site()->index());
        $entries = array_column($status->treeStatus()['tree'], null, 'id');

        $this->assertTrue($entries['all-missing']['isFullyUntranslated']);
        $this->assertFalse($entries['one-missing']['isFullyUntranslated']);
    }

    #[Test]
    public function counts_empty_blocks_field_as_untranslated(): void
    {
        $app = new App([
            'languages' => [
                ['code' => 'en', 'name' => 'English', 'default' => true],
                ['code' => 'de', 'name' => 'Deutsch']
            ],
            'blueprints' => [
                'pages/default' => [
                    'fields' => [
                        'blocks' => ['type' => 'blocks', 'translate' => true]
                    ]
                ]
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'page',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => ['blocks' => '[{"id":"x","type":"text","content":{"text":"hi"}}]']],
                            ['code' => 'de', 'content' => ['blocks' => '[]']]
                        ]
                    ]
                ]
            ]
        ]);

        $page = $app->page('page');
        $status = new TranslationStatus(new Pages([$page]));
        $pageStatus = $status->pageStatus($page);

        $this->assertSame(1, $pageStatus['de']['totalFields']);
        $this->assertSame(0, $pageStatus['de']['translatedFields']);
    }

    #[Test]
    public function tree_status_includes_ancestors_of_incomplete_pages(): void
    {
        $app = new App([
            'languages' => [
                ['code' => 'en', 'name' => 'English', 'default' => true],
                ['code' => 'de', 'name' => 'Deutsch']
            ],
            'blueprints' => [
                'pages/default' => [
                    'fields' => ['text' => ['type' => 'text', 'translate' => true]]
                ]
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'parent',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => ['text' => 'hi']],
                            ['code' => 'de', 'content' => ['text' => 'hallo']]
                        ],
                        'children' => [
                            [
                                'slug' => 'child',
                                'template' => 'default',
                                'translations' => [
                                    ['code' => 'en', 'content' => ['text' => 'hi']]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $status = new TranslationStatus($app->site()->index());
        $tree = $status->treeStatus()['tree'];

        // Parent appears because its descendant is incomplete, even though the parent itself is fully translated
        $this->assertCount(1, $tree);
        $this->assertSame('parent', $tree[0]['id']);
        $this->assertTrue($tree[0]['hasChildren']);
        $this->assertSame(1, $tree[0]['incompleteDescendants']);
        $this->assertNull($tree[0]['missing']);
    }
}
