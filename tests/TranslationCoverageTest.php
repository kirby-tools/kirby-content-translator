<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\TranslationCoverage;
use Kirby\Cms\Pages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TranslationCoverageTest extends TranslationCoverageTestCase
{
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
}
