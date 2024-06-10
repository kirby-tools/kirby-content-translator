<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator;

use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Data\Json;
use Kirby\Form\Form;
use Kirby\Toolkit\A;

final class Translator
{
    private App $kirby;
    private Site|Page $model;
    private string|null $targetLanguage;
    private string|null $sourceLanguage;
    private array $fields;
    private array $fieldTypes;
    private array $includeFields;
    private array $excludeFields;

    public function __construct(Site|Page|File $model, array $options = [])
    {
        $this->kirby = $model->kirby();
        $this->model = $model;
        $this->fields = Translator::resolveModelFields($model);
        $config = $model->kirby()->option('johannschopplich.content-translator', []);

        $this->fieldTypes = $options['fieldTypes'] ?? $config['fieldTypes'] ?? [
            'blocks',
            'layout',
            'list',
            'object',
            'structure',
            'text',
            'textarea',
            'writer'
        ];
        $this->includeFields = $options['includeFields'] ?? $config['includeFields'] ?? [];
        $this->excludeFields = $options['excludeFields'] ?? $config['excludeFields'] ?? [];

        // Lowercase fields keys, sine the Kirby Panel content object keys are lowercase
        $this->fieldTypes = array_map('strtolower', $this->fieldTypes);
        $this->includeFields = array_map('strtolower', $this->includeFields);
        $this->excludeFields = array_map('strtolower', $this->excludeFields);
    }

    public static function translateText(string $text, string $targetLanguage, string|null $sourceLanguage = null): string
    {
        $kirby = App::instance();
        $translateFn = $kirby->option('johannschopplich.content-translator.translateFn');

        if ($translateFn && is_callable($translateFn)) {
            return $translateFn($text, $targetLanguage, $sourceLanguage);
        }

        $deepL = new DeepL();
        return $deepL->translate($text, $targetLanguage, $sourceLanguage);
    }

    public static function resolveModelFields(Site|Page $model): array
    {
        $fields = $model->blueprint()->fields();
        $lang = $model->kirby()->languageCode();
        $content = $model->content($lang)->toArray();
        $form = new Form([
            'fields' => $fields,
            'values' => $content,
            'model' => $model,
            'strict' => true
        ]);

        $fields = $form->fields()->toArray();
        unset($fields['title']);

        foreach ($fields as $index => $props) {
            unset($fields[$index]['value']);
        }

        return $fields;
    }

    public function synchronizeContent(string $fromLanguageCode, string $toLanguageCode): void
    {
        $this->kirby->impersonate('kirby', function () use ($fromLanguageCode, $toLanguageCode) {
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

    public function translateContent(string $contentLanguageCode, string $targetLanguageCode): void
    {
        $this->targetLanguage = $targetLanguageCode;
        $this->sourceLanguage = $contentLanguageCode;

        $this->kirby->impersonate('kirby', function () use ($contentLanguageCode) {
            $content = $this->model->content($contentLanguageCode)->toArray();
            $translatedContent = $this->handleTranslation($content, $this->fields);

            // Write the translated content
            $this->model = $this->model->update($translatedContent, $contentLanguageCode);
        });
    }

    public function translateTitle(string $contentLanguageCode, string $targetLanguageCode): void
    {
        $this->kirby->impersonate('kirby', function () use ($contentLanguageCode, $targetLanguageCode) {
            $translatedTitle = $this->translateText(
                $this->model->content($contentLanguageCode)->get('title')->value(),
                $targetLanguageCode,
                $contentLanguageCode
            );
            $this->model = $this->model->changeTitle($translatedTitle, $contentLanguageCode);
        });
    }

    public function model(): Site|Page|File
    {
        return $this->model;
    }

    private function handleTranslation(array &$obj, array $fields): array
    {
        foreach ($obj as $key => $value) {
            if (empty($value)) {
                continue;
            }
            if (!isset($fields[$key])) {
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

            // Parse blocks encoded as JSON
            if ($fields[$key]['type'] === 'blocks' || $fields[$key]['type'] === 'layout') {
                $obj[$key] = Json::decode($value);
            }

            // Handle text-like fields
            if (in_array($fields[$key]['type'], ['list', 'text', 'textarea', 'writer'], true)) {
                $obj[$key] = $this->translateText($value, $this->targetLanguage, $this->sourceLanguage);
            }

            // Handle structure fields
            elseif ($fields[$key]['type'] === 'structure' && is_array($value)) {
                foreach ($value as &$item) {
                    $this->handleTranslation($item, $fields[$key]['fields']);
                }
            }

            // Handle object fields
            elseif ($fields[$key]['type'] === 'object' && A::isAssociative($value)) {
                $this->handleTranslation($value, $fields[$key]['fields']);
            }

            // Handle layout fields
            elseif ($fields[$key]['type'] === 'layout' && is_array($value)) {
                foreach ($value as &$layout) {
                    foreach ($layout['columns'] as &$column) {
                        foreach ($column['blocks'] as &$block) {
                            if ($this->isBlockTranslatable($block) && isset($fields[$key]['fieldsets'][$block['type']])) {
                                $blockFields = $this->reduceFieldsFromTabs($fields[$key]['fieldsets'], $block);
                                $this->handleTranslation($block['content'], $blockFields);
                            }
                        }
                    }
                }
            }

            // Handle block fields
            elseif ($fields[$key]['type'] === 'blocks' && is_array($value)) {
                foreach ($value as &$block) {
                    if ($this->isBlockTranslatable($block) && isset($fields[$key]['fieldsets'][$block['type']])) {
                        $blockFields = $this->reduceFieldsFromTabs($fields[$key]['fieldsets'], $block);
                        $this->handleTranslation($block['content'], $blockFields);
                    }
                }
            }

            // Encode blocks as JSON
            if ($fields[$key]['type'] === 'blocks' || $fields[$key]['type'] === 'layout') {
                $obj[$key] = Json::encode($obj[$key]);
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

    private function reduceFieldsFromTabs(array $fieldsets, array $block): array
    {
        $blockFields = [];

        foreach ($fieldsets[$block['type']]['tabs'] as $tab) {
            $blockFields = array_merge($blockFields, $tab['fields']);
        }

        return $blockFields;
    }
}
