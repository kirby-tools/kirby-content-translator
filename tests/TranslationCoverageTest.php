<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\TranslationCoverage;
use Kirby\Cms\App;
use Kirby\Cms\Pages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TranslationCoverageTest extends TestCase
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

    private static function twoLanguages(): array
    {
        return [
            ['code' => 'en', 'name' => 'English', 'default' => true],
            ['code' => 'de', 'name' => 'Deutsch'],
        ];
    }

    private function appWithLanguagesOnly(): App
    {
        return new App([
            'languages' => self::threeLanguages(),
        ]);
    }

    private function appWithMixedCoverageFixture(): App
    {
        return new App([
            'languages' => self::threeLanguages(),
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

    private function appWithMissingTranslationsFixture(): App
    {
        return new App([
            'languages' => self::threeLanguages(),
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

    private function appWithEmptyBlocksTranslationFixture(): App
    {
        return new App([
            'languages' => self::twoLanguages(),
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

    private function appWithEmptyDefaultContentFixture(): App
    {
        return new App([
            'languages' => self::twoLanguages(),
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

    private function appWithPartiallyFilledSourceFixture(): App
    {
        return new App([
            'languages' => self::twoLanguages(),
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

    private function appWithNestedPagesFixture(): App
    {
        return new App([
            'languages' => self::twoLanguages(),
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

    #[Test]
    public function fully_translated_page_returns_100_percent(): void
    {
        $app = $this->appWithMixedCoverageFixture();
        $page = $app->page('fully-translated');
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
        $app = $this->appWithMixedCoverageFixture();
        $page = $app->page('partially-translated');
        $coverage = new TranslationCoverage(new Pages([$page]));
        $pageCoverage = $coverage->pageCoverage($page);

        $this->assertSame(2, $pageCoverage['de']['totalFields']);
        $this->assertSame(1, $pageCoverage['de']['translatedFields']);

        $this->assertSame(2, $pageCoverage['fr']['totalFields']);
        $this->assertSame(0, $pageCoverage['fr']['translatedFields']);
    }

    #[Test]
    public function untranslated_page_returns_zero_translated_fields(): void
    {
        $app = $this->appWithMixedCoverageFixture();
        $page = $app->page('untranslated');
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
        $app = $this->appWithMixedCoverageFixture();
        $page = $app->page('no-fields');
        $coverage = new TranslationCoverage(new Pages([$page]));

        $this->assertSame([], $coverage->pageCoverage($page));
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function fieldFilters(): array
    {
        return [
            'includeFields keeps only `text`' => [['includeFields' => ['text']]],
            'excludeFields drops `tags`'      => [['excludeFields' => ['tags']]],
        ];
    }

    #[Test]
    #[DataProvider('fieldFilters')]
    public function respects_field_name_filter(array $options): void
    {
        $app = $this->appWithMixedCoverageFixture();
        $page = $app->page('fully-translated');
        $coverage = new TranslationCoverage(new Pages([$page]), $options);
        $pageCoverage = $coverage->pageCoverage($page);

        $this->assertSame(1, $pageCoverage['de']['totalFields']);
        $this->assertSame(1, $pageCoverage['de']['translatedFields']);
    }

    #[Test]
    public function excludes_default_language_from_results(): void
    {
        $app = $this->appWithMixedCoverageFixture();
        $page = $app->page('fully-translated');
        $coverage = new TranslationCoverage(new Pages([$page]));
        $pageCoverage = $coverage->pageCoverage($page);

        $this->assertArrayNotHasKey('en', $pageCoverage);
        $this->assertArrayHasKey('de', $pageCoverage);
        $this->assertArrayHasKey('fr', $pageCoverage);
    }

    #[Test]
    public function tree_coverage_aggregates_language_totals(): void
    {
        $app = $this->appWithMixedCoverageFixture();
        $coverage = new TranslationCoverage($app->site()->index());
        $result = $coverage->treeCoverage();

        $this->assertCount(2, $result['languages']);

        foreach ($result['tree'] as $entry) {
            $this->assertNotSame('no-fields', $entry['id']);
        }
    }

    #[Test]
    public function tree_coverage_calculates_percentage_per_language(): void
    {
        $app = $this->appWithMixedCoverageFixture();
        $coverage = new TranslationCoverage($app->site()->index());
        $languages = array_column($coverage->treeCoverage()['languages'], null, 'code');

        // DE: fully (2/2) + partial (1/2) + untranslated (0/2) = 3/6 = 50%
        // FR: fully (2/2) + partial (0/2) + untranslated (0/2) = 2/6 = 33%
        $this->assertSame(50, $languages['de']['percentage']);
        $this->assertSame(33, $languages['fr']['percentage']);
    }

    #[Test]
    public function tree_coverage_lists_incomplete_pages(): void
    {
        $app = $this->appWithMixedCoverageFixture();
        $coverage = new TranslationCoverage($app->site()->index());
        $incompleteIds = array_column($coverage->treeCoverage()['tree'], 'id');

        $this->assertNotContains('fully-translated', $incompleteIds);
        $this->assertContains('partially-translated', $incompleteIds);
        $this->assertContains('untranslated', $incompleteIds);
    }

    #[Test]
    public function respects_custom_pages_scope(): void
    {
        $app = $this->appWithMixedCoverageFixture();
        $pages = new Pages([$app->page('fully-translated')]);
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
        $this->appWithLanguagesOnly();
        $coverage = new TranslationCoverage(new Pages([]));
        $result = $coverage->treeCoverage();

        $this->assertEmpty($result['tree']);

        foreach ($result['languages'] as $language) {
            $this->assertSame(0, $language['totalFields']);
            $this->assertSame(0, $language['translatedFields']);
            $this->assertSame(100, $language['percentage']);
        }
    }

    #[Test]
    public function tree_coverage_reports_missing_languages_per_page(): void
    {
        $app = $this->appWithMissingTranslationsFixture();
        $coverage = new TranslationCoverage($app->site()->index());
        $entries = array_column($coverage->treeCoverage()['tree'], null, 'id');

        $this->assertSame(['de', 'fr'], array_column($entries['all-missing']['missing'], 'code'));
        $this->assertSame(['fr'], array_column($entries['one-missing']['missing'], 'code'));
    }

    #[Test]
    public function counts_empty_blocks_field_as_untranslated(): void
    {
        $app = $this->appWithEmptyBlocksTranslationFixture();
        $page = $app->page('page');
        $coverage = new TranslationCoverage(new Pages([$page]));
        $pageCoverage = $coverage->pageCoverage($page);

        $this->assertSame(1, $pageCoverage['de']['totalFields']);
        $this->assertSame(0, $pageCoverage['de']['translatedFields']);
    }

    #[Test]
    public function skips_pages_with_empty_default_language_content(): void
    {
        $app = $this->appWithEmptyDefaultContentFixture();
        $page = $app->page('stub');
        $coverage = new TranslationCoverage(new Pages([$page]));

        $this->assertSame([], $coverage->pageCoverage($page));
        $this->assertSame([], $coverage->treeCoverage()['tree']);
    }

    #[Test]
    public function excludes_unfilled_default_fields_from_total(): void
    {
        $app = $this->appWithPartiallyFilledSourceFixture();
        $page = $app->page('partial-source');
        $coverage = new TranslationCoverage(new Pages([$page]));
        $pageCoverage = $coverage->pageCoverage($page);

        $this->assertSame(1, $pageCoverage['de']['totalFields']);
        $this->assertSame(1, $pageCoverage['de']['translatedFields']);
    }

    #[Test]
    public function tree_coverage_includes_ancestors_of_incomplete_pages(): void
    {
        $app = $this->appWithNestedPagesFixture();
        $coverage = new TranslationCoverage($app->site()->index());
        $tree = $coverage->treeCoverage()['tree'];

        $this->assertCount(1, $tree);
        $this->assertSame('parent', $tree[0]['id']);
        $this->assertTrue($tree[0]['hasChildren']);
        $this->assertSame(1, $tree[0]['incompleteDescendants']);
        $this->assertNull($tree[0]['missing']);
    }
}
