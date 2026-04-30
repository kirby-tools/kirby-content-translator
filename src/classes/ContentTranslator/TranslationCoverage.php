<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator;

use JohannSchopplich\KirbyTools\FieldResolver;
use Kirby\Cms\App;
use Kirby\Cms\Language;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Content\Content;

final readonly class TranslationCoverage
{
    private App $kirby;
    private TranslatorConfig $config;

    public function __construct(
        private Pages $pages,
        array $options = []
    ) {
        $this->kirby = App::instance();
        $this->config = TranslatorConfig::fromOptions($options);
    }

    public function treeCoverage(): array
    {
        $treeIndex = $this->treeIndex();

        return [
            'languages' => $treeIndex['languages'],
            'tree' => $this->treeChildren(null, $treeIndex),
        ];
    }

    /**
     * Returns pruned tree children for a given parent page.
     * Only includes pages that are incomplete or are ancestors of incomplete pages.
     *
     * @return array<int, array{id: string, label: string, icon: string|null, link: string, hasChildren: bool, incompleteDescendants: int, missing: array|null, isFullyUntranslated: bool}>
     */
    public function treeChildren(string|null $parentId, array|null $treeIndex = null): array
    {
        $treeIndex ??= $this->treeIndex();

        $children = $parentId === null
            ? $this->kirby->site()->children()
            : $this->kirby->page($parentId)?->children();

        if ($children === null) {
            return [];
        }

        $entries = [];

        foreach ($children as $child) {
            $childId = $child->id();

            if (!isset($treeIndex['visibleIds'][$childId])) {
                continue;
            }

            $incomplete = $treeIndex['incompleteIds'][$childId] ?? null;

            $entries[] = [
                'id' => $childId,
                'label' => $child->title()->value(),
                'icon' => $child->blueprint()->icon(),
                'link' => $child->panel()->url(true),
                'hasChildren' => $this->hasVisibleChildren($child, $treeIndex),
                'incompleteDescendants' => $treeIndex['descendantCounts'][$childId] ?? 0,
                'missing' => $incomplete['missing'] ?? null,
                'isFullyUntranslated' => $incomplete['isFullyUntranslated'] ?? false,
            ];
        }

        return $entries;
    }

    /**
     * @return array<string, array{totalFields: int, translatedFields: int}>
     */
    public function pageCoverage(Page $page): array
    {
        $cache = $this->kirby->cache('pages');
        $cacheKey = 'johannschopplich.content-translator.coverage.' . $page->id();

        $cached = $cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $translatableFields = $this->translatableFields($page);

        if ($translatableFields === []) {
            return [];
        }

        $totalFields = count($translatableFields);
        $defaultLanguage = $this->kirby->defaultLanguage();
        $coverage = [];

        foreach ($this->kirby->languages() as $language) {
            if ($language->code() === $defaultLanguage->code()) {
                continue;
            }

            $coverage[$language->code()] = $this->languageCoverage(
                $page,
                $language,
                $translatableFields,
                $totalFields
            );
        }

        $cache->set($cacheKey, $coverage);

        return $coverage;
    }

    /**
     * Returns the tree index from cache or computes it fresh.
     */
    private function treeIndex(): array
    {
        $cache = $this->kirby->cache('pages');
        $cacheKey = 'johannschopplich.content-translator.treeIndex';

        $cached = $cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        return $this->computeTreeIndex();
    }

    private function computeTreeIndex(): array
    {
        $defaultLanguage = $this->kirby->defaultLanguage();
        $languages = [];
        $incompleteIds = [];

        // Initialize per-language counters
        foreach ($this->kirby->languages() as $language) {
            if ($language->code() === $defaultLanguage->code()) {
                continue;
            }

            $languages[$language->code()] = [
                'code' => $language->code(),
                'name' => $language->name(),
                'totalFields' => 0,
                'translatedFields' => 0,
                'incompletePageCount' => 0,
            ];
        }

        $languageCount = count($languages);

        // Iterate all pages to find incomplete ones and aggregate language stats
        foreach ($this->pages as $page) {
            $pageCoverage = $this->pageCoverage($page);

            if ($pageCoverage === []) {
                continue;
            }

            $missingLanguages = [];

            foreach ($pageCoverage as $langCode => $coverage) {
                if (!isset($languages[$langCode])) {
                    continue;
                }

                $languages[$langCode]['totalFields'] += $coverage['totalFields'];
                $languages[$langCode]['translatedFields'] += $coverage['translatedFields'];

                if ($coverage['translatedFields'] < $coverage['totalFields']) {
                    $languages[$langCode]['incompletePageCount']++;
                    $missingLanguages[] = [
                        'code' => $langCode,
                        'name' => $languages[$langCode]['name'],
                    ];
                }
            }

            if ($missingLanguages !== []) {
                $incompleteIds[$page->id()] = [
                    'missing' => $missingLanguages,
                    'isFullyUntranslated' => count($missingLanguages) === $languageCount,
                ];
            }
        }

        // Compute percentages
        foreach ($languages as &$lang) {
            $lang['percentage'] = $lang['totalFields'] > 0
                ? (int)round($lang['translatedFields'] / $lang['totalFields'] * 100)
                : 100;
        }

        // Compute ancestor IDs and descendant counts from incomplete page IDs
        $ancestorIds = [];
        $descendantCounts = [];

        foreach (array_keys($incompleteIds) as $id) {
            $parts = explode('/', $id);
            $path = '';

            for ($i = 0, $count = count($parts) - 1; $i < $count; $i++) {
                $path = $path === '' ? $parts[$i] : $path . '/' . $parts[$i];
                $ancestorIds[$path] = true;
                $descendantCounts[$path] = ($descendantCounts[$path] ?? 0) + 1;
            }
        }

        // Visible IDs = incomplete pages + their ancestors (paths shown in pruned tree)
        $visibleIds = $ancestorIds;

        foreach (array_keys($incompleteIds) as $id) {
            $visibleIds[$id] = true;
        }

        $treeIndex = [
            'languages' => array_values($languages),
            'incompleteIds' => $incompleteIds,
            'visibleIds' => $visibleIds,
            'descendantCounts' => $descendantCounts,
        ];

        // 5-minute TTL is a belt-and-braces safety net;
        // Kirby's ModelCommit::after() flushes the `pages` cache on every save.
        $this->kirby->cache('pages')->set(
            'johannschopplich.content-translator.treeIndex',
            $treeIndex,
            5
        );

        return $treeIndex;
    }

    private function hasVisibleChildren(Page $page, array $treeIndex): bool
    {
        foreach ($page->children() as $child) {
            if (isset($treeIndex['visibleIds'][$child->id()])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string>
     */
    private function translatableFields(Page $page): array
    {
        $fields = FieldResolver::resolveModelFields($page);
        $translatable = [];

        foreach ($fields as $key => $props) {
            if ($this->config->isTranslatable($key, $props)) {
                $translatable[] = $key;
            }
        }

        return $translatable;
    }

    /**
     * @return array{totalFields: int, translatedFields: int}
     */
    private function languageCoverage(
        Page $page,
        Language $language,
        array $translatableFields,
        int $totalFields
    ): array {
        // Fast path: no content file means nothing is translated
        if (!$page->version()->exists($language)) {
            return ['totalFields' => $totalFields, 'translatedFields' => 0];
        }

        // Read raw content _without_ default-language fallback
        $fields = $page->version()->read($language);

        if ($fields === null) {
            return ['totalFields' => $totalFields, 'translatedFields' => 0];
        }

        $content = new Content(parent: $page, data: $fields, normalize: false);
        $translatedFields = 0;

        foreach ($translatableFields as $key) {
            if ($content->get($key)->isNotEmpty()) {
                $translatedFields++;
            }
        }

        return ['totalFields' => $totalFields, 'translatedFields' => $translatedFields];
    }
}
