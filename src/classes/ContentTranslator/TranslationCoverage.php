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
use Kirby\Uuid\PageUuid;

final class TranslationCoverage
{
    private const TREE_INDEX_TTL_MINUTES = 5;

    private readonly App $kirby;
    private readonly TranslatorConfig $config;

    /** @var array<string, list<string>> Blueprint name → translatable field keys */
    private array $translatableKeysByBlueprint = [];

    /**
     * @throws LogicException When called on a single-language Kirby installation
     */
    public function __construct(
        private readonly Pages $pages,
        array $options = []
    ) {
        $this->kirby = App::instance();

        if (!$this->kirby->multilang()) {
            // TODO: Drop K4 compat in v4 – use the named argument `message:` once Kirby 5 is the floor.
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
     *
     * Only pages that are incomplete themselves, or ancestors of one, survive
     * the pruning.
     *
     * @return array<int, array{id: string, label: string, icon: string|null, link: string, hasChildren: bool, incompleteDescendantCount: int, missingLanguages: array<int, array{code: string, name: string}>}>|null}>
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
                'incompleteDescendantCount' => $treeIndex['descendantCounts'][$childId] ?? 0,
                'missingLanguages' => $treeIndex['incompleteIds'][$childId]['missingLanguages'] ?? [],
            ];
        }

        return $entries;
    }

    /**
     * @return array<string, array{totalFields: int, translatedFields: int}>
     */
    public function pageCoverage(Page $page): array
    {
        return $this->kirby->cache('johannschopplich.content-translator')->getOrSet(
            'coverage.' . self::cacheKey($page),
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

    public static function cacheKey(Page $page): string
    {
        return PageUuid::retrieveId($page) ?? $page->id();
    }

    private function treeIndex(): array
    {
        return $this->kirby->cache('johannschopplich.content-translator')->getOrSet(
            'treeIndex',
            function (): array {
                $defaultLanguage = $this->kirby->defaultLanguage();
                $languages = [];
                $translatableFieldCounts = [];
                $translatedFieldCounts = [];
                $incompleteIds = [];

                foreach ($this->kirby->languages() as $language) {
                    if ($language->code() === $defaultLanguage->code()) {
                        continue;
                    }

                    $languages[$language->code()] = [
                        'code' => $language->code(),
                        'name' => $language->name(),
                        'incompletePageCount' => 0,
                    ];
                }

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

                        $translatableFieldCounts[$langCode] = ($translatableFieldCounts[$langCode] ?? 0) + $coverage['totalFields'];
                        $translatedFieldCounts[$langCode] = ($translatedFieldCounts[$langCode] ?? 0) + $coverage['translatedFields'];

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
                            'missingLanguages' => $missingLanguages,
                        ];
                    }
                }

                foreach ($languages as $langCode => &$lang) {
                    $translatableFieldCount = $translatableFieldCounts[$langCode] ?? 0;
                    $lang['percentage'] = $translatableFieldCount > 0
                        ? (int)round(($translatedFieldCounts[$langCode] ?? 0) / $translatableFieldCount * 100)
                        : 100;
                }

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

                // Ancestors stay visible so the pruned tree keeps a path down to
                // every incomplete page.
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
     * Returns top-level translatable field keys that are non-empty in the
     * default language.
     *
     * The denominator is content-driven so that stub pages without source data
     * don't get flagged as untranslated.
     *
     * @return array<string>
     */
    private function translatableFields(Page $page, Language $defaultLanguage): array
    {
        // Read raw default-language content _without_ fallback so the
        // denominator reflects only fields actually filled at the source.
        if (!$page->version()->exists($defaultLanguage)) {
            return [];
        }

        $fields = $page->version()->read($defaultLanguage);

        if ($fields === null) {
            return [];
        }

        $defaultContent = new Content(parent: $page, data: $fields, normalize: false);
        $translatableFields = [];

        foreach ($this->translatableKeys($page) as $key) {
            if ($defaultContent->get($key)->isNotEmpty()) {
                $translatableFields[] = $key;
            }
        }

        return $translatableFields;
    }

    /**
     * Resolves and memoizes the blueprint-level translatable field keys
     * per blueprint name, so Form construction runs once per blueprint
     * instead of once per page.
     *
     * @return list<string>
     */
    private function translatableKeys(Page $page): array
    {
        return $this->translatableKeysByBlueprint[$page->blueprint()->name()]
            ??= $this->resolveTranslatableKeys($page);
    }

    /**
     * @return list<string>
     */
    private function resolveTranslatableKeys(Page $page): array
    {
        $keys = [];

        foreach (FieldResolver::resolveModelFields($page) as $key => $props) {
            if ($this->config->isTranslatable($key, $props)) {
                $keys[] = $key;
            }
        }

        return $keys;
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
        // Fast path: no content file means nothing is translated.
        if (!$page->version()->exists($language)) {
            return ['totalFields' => $totalFields, 'translatedFields' => 0];
        }

        // Read raw content _without_ default-language fallback.
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
