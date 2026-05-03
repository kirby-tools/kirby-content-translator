<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator;

use JohannSchopplich\KirbyTools\FieldResolver;
use Kirby\Cms\App;
use Kirby\Cms\Language;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Content\Content;
use Kirby\Exception\LogicException;

final readonly class TranslationCoverage
{
    private const TREE_INDEX_TTL_MINUTES = 5;

    private App $kirby;
    private TranslatorConfig $config;

    /**
     * @throws LogicException When called on a single-language Kirby installation.
     */
    public function __construct(
        private Pages $pages,
        array $options = []
    ) {
        $this->kirby = App::instance();

        if (!$this->kirby->multilang()) {
            // TODO: Drop K4 compat in v4 – use named arg (message:) once Kirby 5 is the floor
            throw new LogicException(
                ['fallback' => 'TranslationCoverage requires a multi-language Kirby installation.'],
            );
        }

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
     * @return array<int, array{id: string, label: string, icon: string|null, link: string, hasChildren: bool, incompleteDescendants: int, missing: array|null}>
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

            $entries[] = [
                'id' => $childId,
                'label' => $child->title()->value(),
                'icon' => $child->blueprint()->icon(),
                'link' => $child->panel()->url(true),
                'hasChildren' => $this->hasVisibleChildren($child, $treeIndex),
                'incompleteDescendants' => $treeIndex['descendantCounts'][$childId] ?? 0,
                'missing' => $treeIndex['incompleteIds'][$childId]['missing'] ?? null,
            ];
        }

        return $entries;
    }

    /**
     * @return array<string, array{totalFields: int, translatedFields: int}>
     */
    public function pageCoverage(Page $page): array
    {
        return $this->kirby->cache('pages')->getOrSet(
            'johannschopplich.content-translator.coverage.' . $page->id(),
            function () use ($page): array {
                $defaultLanguage = $this->kirby->defaultLanguage();
                $translatableFields = $this->translatableFields($page, $defaultLanguage);

                if ($translatableFields === []) {
                    return [];
                }

                $totalFields = count($translatableFields);
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

                return $coverage;
            }
        );
    }

    private function treeIndex(): array
    {
        return $this->kirby->cache('pages')->getOrSet(
            'johannschopplich.content-translator.treeIndex',
            function (): array {
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

                return [
                    'languages' => array_values($languages),
                    'incompleteIds' => $incompleteIds,
                    'visibleIds' => $visibleIds,
                    'descendantCounts' => $descendantCounts,
                ];
            },
            self::TREE_INDEX_TTL_MINUTES,
        );
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
     * Returns top-level translatable field keys that are non-empty in
     * the default language. The denominator is content-driven so that
     * stub pages without source data don't get flagged as untranslated.
     *
     * @return array<string>
     */
    private function translatableFields(Page $page, Language $defaultLanguage): array
    {
        // Read raw default-language content _without_ fallback so the
        // denominator reflects only fields actually filled at the source
        if (!$page->version()->exists($defaultLanguage)) {
            return [];
        }

        $fields = $page->version()->read($defaultLanguage);

        if ($fields === null) {
            return [];
        }

        $defaultContent = new Content(parent: $page, data: $fields, normalize: false);
        $blueprintFields = FieldResolver::resolveModelFields($page);
        $translatableFields = [];

        foreach ($blueprintFields as $key => $props) {
            if (!$this->config->isTranslatable($key, $props)) {
                continue;
            }

            if ($defaultContent->get($key)->isNotEmpty()) {
                $translatableFields[] = $key;
            }
        }

        return $translatableFields;
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
