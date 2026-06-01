<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\TranslationCoverage;
use Kirby\Cms\App;
use Kirby\Cms\Pages;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CoverageHooksTest extends TestCase
{
    protected function tearDown(): void
    {
        App::destroy();
    }

    private static function pluginOptions(): array
    {
        return [
            'johannschopplich.content-translator' => [
                'cache' => ['type' => 'memory'],
            ],
        ];
    }

    private function appWithHomePage(): App
    {
        return new App([
            'languages' => [
                ['code' => 'en', 'name' => 'English', 'default' => true],
                ['code' => 'de', 'name' => 'Deutsch'],
            ],
            'options' => self::pluginOptions(),
            'blueprints' => [
                'pages/default' => [
                    'fields' => ['text' => ['type' => 'text', 'translate' => true]],
                ],
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'home',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => ['text' => 'Hello']],
                            ['code' => 'de', 'content' => ['text' => 'Hallo']],
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function appWithHomePageHavingUuid(): App
    {
        return new App([
            'languages' => [
                ['code' => 'en', 'name' => 'English', 'default' => true],
                ['code' => 'de', 'name' => 'Deutsch'],
            ],
            'options' => self::pluginOptions(),
            'blueprints' => [
                'pages/default' => [
                    'fields' => ['text' => ['type' => 'text', 'translate' => true]],
                ],
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'home',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => [
                                'uuid' => 'abc123',
                                'text' => 'Hello',
                            ]],
                            ['code' => 'de', 'content' => ['text' => 'Hallo']],
                        ],
                    ],
                ],
            ],
        ]);
    }

    #[Test]
    public function clears_page_coverage_on_page_update(): void
    {
        $app = $this->appWithHomePage();
        $page = $app->page('home');
        $coverage = new TranslationCoverage(new Pages([$page]));
        $coverage->pageCoverage($page);

        $cache = $app->cache('johannschopplich.content-translator');
        $this->assertNotNull($cache->get('coverage.home'));

        $app->impersonate('kirby', fn () => $page->update(['text' => 'Updated'], 'en'));

        $this->assertNull($cache->get('coverage.home'));
        $this->assertNull($cache->get('treeIndex'));
    }

    #[Test]
    public function preserves_page_coverage_on_page_render(): void
    {
        $app = $this->appWithHomePage();
        $page = $app->page('home');
        $coverage = new TranslationCoverage(new Pages([$page]));
        $coverage->pageCoverage($page);

        $cache = $app->cache('johannschopplich.content-translator');
        $this->assertNotNull($cache->get('coverage.home'));

        $app->trigger('page.render:after', [
            'contentType' => 'html',
            'data' => [],
            'page' => $page,
            'html' => '',
        ]);

        $this->assertNotNull($cache->get('coverage.home'));
    }

    #[Test]
    public function clears_uuid_keyed_coverage_on_page_update(): void
    {
        $app = $this->appWithHomePageHavingUuid();
        $page = $app->page('home');
        $coverage = new TranslationCoverage(new Pages([$page]));
        $coverage->pageCoverage($page);

        $cache = $app->cache('johannschopplich.content-translator');
        $this->assertNotNull($cache->get('coverage.abc123'));

        $app->impersonate('kirby', fn () => $page->update(['text' => 'Updated'], 'en'));

        $this->assertNull($cache->get('coverage.abc123'));
    }

    #[Test]
    public function clears_legacy_id_keyed_coverage_on_page_update(): void
    {
        $app = $this->appWithHomePageHavingUuid();
        $page = $app->page('home');
        $cache = $app->cache('johannschopplich.content-translator');
        $cache->set('coverage.home', ['legacy']);

        $app->impersonate('kirby', fn () => $page->update(['text' => 'Updated'], 'en'));

        $this->assertNull($cache->get('coverage.home'));
    }

    #[Test]
    public function flushes_plugin_cache_on_language_change(): void
    {
        $app = $this->appWithHomePage();
        $page = $app->page('home');
        $coverage = new TranslationCoverage(new Pages([$page]));
        $coverage->pageCoverage($page);

        $cache = $app->cache('johannschopplich.content-translator');
        $this->assertNotNull($cache->get('coverage.home'));

        $app->trigger('language.create:after', ['language' => $app->language('de')]);

        $this->assertNull($cache->get('coverage.home'));
    }
}
