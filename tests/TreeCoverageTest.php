<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\TranslationCoverage;
use Kirby\Cms\Pages;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TreeCoverageTest extends TranslationCoverageTestCase
{
    #[Test]
    public function aggregates_language_totals(): void
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
    public function calculates_percentage_per_language(): void
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
    public function lists_incomplete_pages(): void
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
    public function reports_full_completion_when_no_pages(): void
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
    public function reports_missing_languages_per_page(): void
    {
        $app = $this->appWithMissingTranslationsFixture();
        $coverage = new TranslationCoverage($app->site()->index());
        $entries = array_column($coverage->treeCoverage()['tree'], null, 'id');

        $this->assertSame(['de', 'fr'], array_column($entries['all-missing']['missing'], 'code'));
        $this->assertSame(['fr'], array_column($entries['one-missing']['missing'], 'code'));
    }

    #[Test]
    public function includes_ancestors_of_incomplete_pages(): void
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
