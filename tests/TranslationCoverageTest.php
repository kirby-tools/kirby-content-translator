<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\TranslationCoverage;
use Kirby\Cms\App;
use Kirby\Cms\Blueprint;
use Kirby\Cms\Pages;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TranslationCoverageTest extends TestCase
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
        $coverage = new TranslationCoverage(new Pages([$page]));
        $pageCoverage = $coverage->pageCoverage($page);

        $this->assertSame(2, $pageCoverage['de']['totalFields']);
        $this->assertSame(2, $pageCoverage['de']['translatedFields']);
        $this->assertSame(2, $pageCoverage['fr']['totalFields']);
        $this->assertSame(2, $pageCoverage['fr']['translatedFields']);
    }

    #[Test]
    public function partially_translated_page_counts_only_filled_fields(): void
    {
        $page = $this->app->page('partially-translated');
        $coverage = new TranslationCoverage(new Pages([$page]));
        $pageCoverage = $coverage->pageCoverage($page);

        // DE: text filled, tags empty
        $this->assertSame(2, $pageCoverage['de']['totalFields']);
        $this->assertSame(1, $pageCoverage['de']['translatedFields']);

        // FR: text empty, tags empty
        $this->assertSame(2, $pageCoverage['fr']['totalFields']);
        $this->assertSame(0, $pageCoverage['fr']['translatedFields']);
    }

    #[Test]
    public function untranslated_page_returns_zero_translated_fields(): void
    {
        $page = $this->app->page('untranslated');
        $coverage = new TranslationCoverage(new Pages([$page]));
        $pageCoverage = $coverage->pageCoverage($page);

        $this->assertSame(2, $pageCoverage['de']['totalFields']);
        $this->assertSame(0, $pageCoverage['de']['translatedFields']);
        $this->assertSame(2, $pageCoverage['fr']['totalFields']);
        $this->assertSame(0, $pageCoverage['fr']['translatedFields']);
    }

    #[Test]
    public function skips_pages_without_translatable_fields(): void
    {
        $page = $this->app->page('no-fields');
        $coverage = new TranslationCoverage(new Pages([$page]));
        $pageCoverage = $coverage->pageCoverage($page);

        $this->assertSame([], $pageCoverage);
    }

    #[Test]
    public function respects_include_fields_filter(): void
    {
        $page = $this->app->page('fully-translated');
        $coverage = new TranslationCoverage(new Pages([$page]), [
            'includeFields' => ['text']
        ]);
        $pageCoverage = $coverage->pageCoverage($page);

        // Only text should be counted
        $this->assertSame(1, $pageCoverage['de']['totalFields']);
        $this->assertSame(1, $pageCoverage['de']['translatedFields']);
    }

    #[Test]
    public function respects_exclude_fields_filter(): void
    {
        $page = $this->app->page('fully-translated');
        $coverage = new TranslationCoverage(new Pages([$page]), [
            'excludeFields' => ['tags']
        ]);
        $pageCoverage = $coverage->pageCoverage($page);

        // Only text should be counted (tags excluded)
        $this->assertSame(1, $pageCoverage['de']['totalFields']);
        $this->assertSame(1, $pageCoverage['de']['translatedFields']);
    }

    #[Test]
    public function excludes_default_language_from_results(): void
    {
        $page = $this->app->page('fully-translated');
        $coverage = new TranslationCoverage(new Pages([$page]));
        $pageCoverage = $coverage->pageCoverage($page);

        $this->assertArrayNotHasKey('en', $pageCoverage);
        $this->assertArrayHasKey('de', $pageCoverage);
        $this->assertArrayHasKey('fr', $pageCoverage);
    }

    #[Test]
    public function tree_coverage_aggregates_language_totals(): void
    {
        $pages = $this->app->site()->index();
        $coverage = new TranslationCoverage($pages);
        $result = $coverage->treeCoverage();

        $this->assertCount(2, $result['languages']);

        // No-fields page should be auto-excluded from tree
        foreach ($result['tree'] as $entry) {
            $this->assertNotSame('no-fields', $entry['id']);
        }
    }

    #[Test]
    public function tree_coverage_calculates_percentage_per_language(): void
    {
        $pages = $this->app->site()->index();
        $coverage = new TranslationCoverage($pages);
        $result = $coverage->treeCoverage();

        $languages = array_column($result['languages'], null, 'code');

        // DE: fully-translated (2/2), partially-translated (1/2), untranslated (0/2) = 3/6 = 50%
        $this->assertSame(50, $languages['de']['percentage']);

        // FR: fully-translated (2/2), partially-translated (0/2), untranslated (0/2) = 2/6 = 33%
        $this->assertSame(33, $languages['fr']['percentage']);
    }

    #[Test]
    public function tree_coverage_lists_incomplete_pages(): void
    {
        $pages = $this->app->site()->index();
        $coverage = new TranslationCoverage($pages);
        $result = $coverage->treeCoverage();

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
        $coverage = new TranslationCoverage($pages);
        $result = $coverage->treeCoverage();

        $languages = array_column($result['languages'], null, 'code');

        $this->assertSame(100, $languages['de']['percentage']);
        $this->assertSame(100, $languages['fr']['percentage']);
        $this->assertEmpty($result['tree']);
    }

    #[Test]
    public function tree_coverage_reports_full_completion_when_no_pages(): void
    {
        $coverage = new TranslationCoverage(new Pages([]));
        $result = $coverage->treeCoverage();

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
    public function tree_coverage_emits_is_fully_untranslated_flag(): void
    {
        // Drop setUp's `pages/default` from Kirby's process-wide blueprint cache
        Blueprint::$loaded = [];

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

        $coverage = new TranslationCoverage($app->site()->index());
        $entries = array_column($coverage->treeCoverage()['tree'], null, 'id');

        $this->assertTrue($entries['all-missing']['isFullyUntranslated']);
        $this->assertFalse($entries['one-missing']['isFullyUntranslated']);
    }

    #[Test]
    public function counts_empty_blocks_field_as_untranslated(): void
    {
        // Drop setUp's `pages/default` from Kirby's process-wide blueprint cache
        Blueprint::$loaded = [];

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
        $coverage = new TranslationCoverage(new Pages([$page]));
        $pageCoverage = $coverage->pageCoverage($page);

        $this->assertSame(1, $pageCoverage['de']['totalFields']);
        $this->assertSame(0, $pageCoverage['de']['translatedFields']);
    }

    #[Test]
    public function skips_pages_with_empty_default_language_content(): void
    {
        // Drop setUp's `pages/default` from Kirby's process-wide blueprint cache
        Blueprint::$loaded = [];

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
                        'slug' => 'stub',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => ['text' => '']],
                            ['code' => 'de', 'content' => ['text' => 'irrelevant']]
                        ]
                    ]
                ]
            ]
        ]);

        $page = $app->page('stub');
        $coverage = new TranslationCoverage(new Pages([$page]));

        // No translatable fields are filled at the source, so the page is omitted
        $this->assertSame([], $coverage->pageCoverage($page));

        // And it must not show up in the tree
        $tree = $coverage->treeCoverage()['tree'];
        $this->assertSame([], $tree);
    }

    #[Test]
    public function excludes_unfilled_default_fields_from_total(): void
    {
        // Drop setUp's `pages/default` from Kirby's process-wide blueprint cache
        Blueprint::$loaded = [];

        $app = new App([
            'languages' => [
                ['code' => 'en', 'name' => 'English', 'default' => true],
                ['code' => 'de', 'name' => 'Deutsch']
            ],
            'blueprints' => [
                'pages/default' => [
                    'fields' => [
                        'text' => ['type' => 'text', 'translate' => true],
                        'tags' => ['type' => 'tags', 'translate' => true]
                    ]
                ]
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'partial-source',
                        'template' => 'default',
                        'translations' => [
                            // Only `text` is filled at the source; `tags` is empty
                            ['code' => 'en', 'content' => ['text' => 'hello', 'tags' => '']],
                            ['code' => 'de', 'content' => ['text' => 'hallo', 'tags' => 'tag1']]
                        ]
                    ]
                ]
            ]
        ]);

        $page = $app->page('partial-source');
        $coverage = new TranslationCoverage(new Pages([$page]));
        $pageCoverage = $coverage->pageCoverage($page);

        // `tags` is empty in default and so dropped from total; DE has `text` translated
        $this->assertSame(1, $pageCoverage['de']['totalFields']);
        $this->assertSame(1, $pageCoverage['de']['translatedFields']);
    }

    #[Test]
    public function tree_coverage_includes_ancestors_of_incomplete_pages(): void
    {
        // Drop setUp's `pages/default` from Kirby's process-wide blueprint cache
        Blueprint::$loaded = [];

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

        $coverage = new TranslationCoverage($app->site()->index());
        $tree = $coverage->treeCoverage()['tree'];

        // Parent appears because its descendant is incomplete, even though the parent itself is fully translated
        $this->assertCount(1, $tree);
        $this->assertSame('parent', $tree[0]['id']);
        $this->assertTrue($tree[0]['hasChildren']);
        $this->assertSame(1, $tree[0]['incompleteDescendants']);
        $this->assertNull($tree[0]['missing']);
    }
}
