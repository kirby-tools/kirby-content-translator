<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator;

use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Data\Data;
use Kirby\Toolkit\A;

final class Translator
{
    private App $kirby;
    private Site|Page|File $model;
    private string|null $targetLanguage;
    private string|null $sourceLanguage;
    private array $fields;
    private array $fieldTypes;
    private array $includeFields;
    private array $excludeFields;
    private array $kirbyTags;

    public function __construct(Site|Page|File $model, array $options = [])
    {
        $this->kirby = $model->kirby();
        $this->model = $model;
        $this->fields = FieldResolver::resolveModelFields($model);
        $config = $model->kirby()->option('johannschopplich.content-translator', []);

        $this->fieldTypes = $options['fieldTypes'] ?? $config['fieldTypes'] ?? [
            'blocks',
            'layout',
            'list',
            'object',
            'structure',
            'tags',
            'text',
            'textarea',
            'writer',
            // Community plugins
            'markdown',
            'table'
        ];
        $this->includeFields = $options['includeFields'] ?? $config['includeFields'] ?? [];
        $this->excludeFields = $options['excludeFields'] ?? $config['excludeFields'] ?? [];

        // Lowercase fields keys, sine the Kirby Panel content object keys are lowercase
        $this->fieldTypes = array_map('strtolower', $this->fieldTypes);
        $this->includeFields = array_map('strtolower', $this->includeFields);
        $this->excludeFields = array_map('strtolower', $this->excludeFields);

        $this->kirbyTags = $options['kirbyTags'] ?? $config['kirbyTags'] ?? [];
    }

    public static function translateText(string $text, string $targetLanguage, string|null $sourceLanguage = null): string
    {
        if (empty(trim($text))) {
            return '';
        }

        $kirby = App::instance();

        $text = $kirby->apply('content-translator.translate:before', [
            'text' => $text,
            'targetLanguage' => $targetLanguage,
            'sourceLanguage' => $sourceLanguage,
            'type' => 'text'
        ], 'text');

        $translateFn = $kirby->option('johannschopplich.content-translator.translateFn');

        if ($translateFn && is_callable($translateFn)) {
            $result = $translateFn($text, $targetLanguage, $sourceLanguage);
        } else {
            $deepL = DeepL::instance();
            $result = $deepL->translate($text, $targetLanguage, $sourceLanguage);
        }

        $result = $kirby->apply('content-translator.translate:after', [
            'text' => $result,
            'originalText' => $text,
            'targetLanguage' => $targetLanguage,
            'sourceLanguage' => $sourceLanguage,
            'type' => 'text'
        ], 'text');

        return $result;
    }

    public function copyContent(string $toLanguageCode, string $fromLanguageCode): void
    {
        $this->kirby->impersonate('kirby', function () use ($toLanguageCode, $fromLanguageCode) {
            $content = [];

            foreach ($this->fields as $field => $props) {
                if (
                    in_array($props['type'], $this->fieldTypes, true) &&
                    (empty($this->includeFields) || in_array($field, $this->includeFields, true)) &&
                    (empty($this->excludeFields) || !in_array($field, $this->excludeFields, true))
                ) {
                    $content[$field] = $this->model->content($fromLanguageCode)->get($field)->value();
                }
            }

            $this->model = $this->model->update($content, $toLanguageCode);
        });
    }

    public function translateContent(string $contentLanguageCode, string $toLanguageCode, string|null $fromLanguageCode = null): void
    {
        $this->targetLanguage = $toLanguageCode;
        $this->sourceLanguage = $fromLanguageCode;

        $this->kirby->impersonate('kirby', function () use ($contentLanguageCode, $toLanguageCode, $fromLanguageCode) {
            $content = $this->model->content($contentLanguageCode)->toArray();
            $translatedContent = $this->walkTranslatableFields($content, $this->fields);

            // Write the translated content
            $this->model = $this->model->update($translatedContent, $contentLanguageCode);
        });
    }

    public function translateTitle(string $contentLanguageCode, string $toLanguageCode, string|null $fromLanguageCode = null): void
    {
        $this->kirby->impersonate('kirby', function () use ($contentLanguageCode, $toLanguageCode, $fromLanguageCode) {
            $originalTitle = $this->model->content($contentLanguageCode)->get('title')->value();

            if (empty($originalTitle) && $fromLanguageCode && $fromLanguageCode !== $contentLanguageCode) {
                $originalTitle = $this->model->content($fromLanguageCode)->get('title')->value();
            }

            if (!empty($originalTitle)) {
                $translatedTitle = $this->translateText(
                    $originalTitle,
                    $toLanguageCode,
                    $fromLanguageCode
                );

                $this->model = $this->model->changeTitle($translatedTitle, $contentLanguageCode);
            }
        });
    }

    public function translateSlug(string $contentLanguageCode, string $toLanguageCode, string|null $fromLanguageCode = null): void
    {
        if ($this->model::CLASS_ALIAS !== 'page' || $this->model->isHomePage() || $this->model->isErrorPage()) {
            return;
        }

        $this->kirby->impersonate('kirby', function () use ($contentLanguageCode, $toLanguageCode, $fromLanguageCode) {
            $originalSlug = $this->model->slug($contentLanguageCode);

            $translatedSlug = $this->translateText(
                $originalSlug,
                $toLanguageCode,
                $fromLanguageCode
            );

            $this->model = $this->model->changeSlug($translatedSlug, $contentLanguageCode);
        });
    }

    public function model(): Site|Page|File
    {
        return $this->model;
    }

    private function walkTranslatableFields(array &$obj, array $fields, $isRecursive = false): array
    {
        foreach ($obj as $key => $value) {
            if (empty($value)) {
                continue;
            }
            if (!isset($fields[$key])) {
                continue;
            }
            if (!($fields[$key]['translate'] ?? true)) {
                continue;
            }
            if (!in_array($fields[$key]['type'], $this->fieldTypes, true)) {
                continue;
            }

            // Include/exclude fields
            if (!empty($this->includeFields) && !in_array($key, $this->includeFields)) {
                continue;
            }
            if (!empty($this->excludeFields) && in_array($key, $this->excludeFields)) {
                continue;
            }

            // Parse JSON-encoded fields
            if (($fields[$key]['type'] === 'blocks' || $fields[$key]['type'] === 'layout') && is_string($obj[$key])) {
                $obj[$key] = Data::decode($obj[$key], 'json');
            }

            // Parse YAML-encoded fields
            elseif (($fields[$key]['type'] === 'structure' || $fields[$key]['type'] === 'object' || $fields[$key]['type'] === 'table') && is_string($obj[$key])) {
                $obj[$key] = Data::decode($obj[$key], 'yaml');
            }

            // Handle text-like fields
            if (in_array($fields[$key]['type'], ['list', 'tags', 'text', 'writer'], true)) {
                $obj[$key] = $this->translateText($obj[$key], $this->targetLanguage, $this->sourceLanguage);
            }
            // Handle markdown content separately
            elseif (in_array($fields[$key]['type'], ['textarea', 'markdown'], true)) {
                $obj[$key] = KirbyText::translateText($obj[$key], $this->targetLanguage, $this->sourceLanguage, $this->kirbyTags);
            }

            // Handle structure fields
            elseif ($fields[$key]['type'] === 'structure' && is_array($obj[$key])) {
                foreach ($obj[$key] as &$item) {
                    $this->walkTranslatableFields($item, $fields[$key]['fields'], true);
                }
            }

            // Handle object fields
            elseif ($fields[$key]['type'] === 'object' && A::isAssociative($obj[$key])) {
                $this->walkTranslatableFields($obj[$key], $fields[$key]['fields'], true);
            }

            // Handle table fields
            elseif ($fields[$key]['type'] === 'table' && is_array($obj[$key])) {
                foreach ($obj[$key] as &$row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    foreach ($row as &$cell) {
                        if (!empty($cell) && is_string($cell)) {
                            $cell = $this->translateText($cell, $this->targetLanguage, $this->sourceLanguage);
                        }
                    }
                }
            }

            // Handle layout fields
            elseif ($fields[$key]['type'] === 'layout' && is_array($obj[$key])) {
                foreach ($obj[$key] as &$layout) {
                    foreach ($layout['columns'] as &$column) {
                        foreach ($column['blocks'] as &$block) {
                            if ($this->isBlockTranslatable($block) && isset($fields[$key]['fieldsets'][$block['type']])) {
                                $blockFields = $this->flattenTabFields($fields[$key]['fieldsets'], $block);
                                $this->walkTranslatableFields($block['content'], $blockFields, true);
                            }
                        }
                    }
                }
            }

            // Handle block fields
            elseif ($fields[$key]['type'] === 'blocks' && is_array($obj[$key])) {
                foreach ($obj[$key] as &$block) {
                    if ($this->isBlockTranslatable($block) && isset($fields[$key]['fieldsets'][$block['type']])) {
                        $blockFields = $this->flattenTabFields($fields[$key]['fieldsets'], $block);
                        $this->walkTranslatableFields($block['content'], $blockFields, true);
                    }
                }
            }

            // Encode fields back to JSON
            if ($fields[$key]['type'] === 'blocks' || $fields[$key]['type'] === 'layout') {
                $obj[$key] = Data::encode($obj[$key], 'json');
            }

            if (!$isRecursive) {
                // Encode fields back to YAML
                if ($fields[$key]['type'] === 'structure' || $fields[$key]['type'] === 'object' || $fields[$key]['type'] === 'table') {
                    $obj[$key] = Data::encode($obj[$key], 'yaml');
                }
            }
        }

        return $obj;
    }

    private function isBlockTranslatable(array $block): bool
    {
        return isset($block['content']) &&
            A::isAssociative($block['content'])
            && isset($block['id'])
            && ($block['isHidden'] ?? false) !== true;
    }

    private function flattenTabFields(array $fieldsets, array $block): array
    {
        $blockFields = [];

        foreach ($fieldsets[$block['type']]['tabs'] as $tab) {
            $blockFields = array_merge($blockFields, $tab['fields']);
        }

        return $blockFields;
    }
}
