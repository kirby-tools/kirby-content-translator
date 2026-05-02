<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator;

use Closure;
use JohannSchopplich\ContentTranslator\Translation\Collector;
use JohannSchopplich\ContentTranslator\Translation\Exception\TranslationException;
use JohannSchopplich\ContentTranslator\Translation\ExecutionOptions;
use JohannSchopplich\ContentTranslator\Translation\Strategies\CallableStrategy;
use JohannSchopplich\ContentTranslator\Translation\Strategies\CopilotAIStrategy;
use JohannSchopplich\ContentTranslator\Translation\Strategies\DeepLStrategy;
use JohannSchopplich\ContentTranslator\Translation\Strategy;
use JohannSchopplich\ContentTranslator\Translation\TextFilter;
use JohannSchopplich\ContentTranslator\Translation\TranslationLanguage;
use JohannSchopplich\ContentTranslator\Translation\TranslationUnit;
use JohannSchopplich\Copilot\AI\Client as CopilotClient;
use JohannSchopplich\KirbyTools\FieldResolver;
use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Exception\AuthException;
use Kirby\Exception\LogicException;

final class Translator
{
    private readonly App $kirby;
    private Site|Page|File $model;
    private readonly array $fields;
    private readonly TranslatorConfig $config;

    public function __construct(
        Site|Page|File $model,
        array $options = []
    ) {
        $this->kirby = $model->kirby();
        $this->model = $model;
        $this->fields = FieldResolver::resolveModelFields($model);
        $this->config = TranslatorConfig::fromOptions($options);
    }

    public function model(): Site|Page|File
    {
        return $this->model;
    }

    /**
     * @throws TranslationException
     * @throws LogicException
     * @throws AuthException
     */
    public static function translateText(string $text, string $targetLanguage, string|null $sourceLanguage = null, Strategy|null $strategy = null): string
    {
        if (TextFilter::shouldSkip($text)) {
            return $text;
        }

        $result = self::translateTexts([$text], $targetLanguage, $sourceLanguage, $strategy);
        return $result[0];
    }

    /**
     * @param list<string> $texts
     * @return list<string>
     *
     * @throws TranslationException
     * @throws LogicException
     * @throws AuthException
     */
    public static function translateTexts(array $texts, string $targetLanguage, string|null $sourceLanguage = null, Strategy|null $strategy = null): array
    {
        if ($texts === []) {
            return [];
        }

        $kirby = App::instance();
        $strategy ??= self::resolveStrategy();
        $options = self::buildOptions($targetLanguage, $sourceLanguage);

        $units = array_map(
            fn (string $text): TranslationUnit => new TranslationUnit(
                text: $kirby->apply('content-translator.translate:before', [
                    'text' => $text,
                    'targetLanguage' => $targetLanguage,
                    'sourceLanguage' => $sourceLanguage,
                    'type' => 'text',
                    'unit' => new TranslationUnit($text),
                    'options' => $options,
                ], 'text'),
            ),
            $texts,
        );

        $translatedResult = $strategy->execute($units, $options);

        $translatedTexts = [];
        foreach ($translatedResult as $index => $translatedText) {
            $translatedTexts[] = $kirby->apply('content-translator.translate:after', [
                'text' => $translatedText,
                'originalText' => $texts[$index],
                'targetLanguage' => $targetLanguage,
                'sourceLanguage' => $sourceLanguage,
                'type' => 'text',
                'unit' => $units[$index],
                'options' => $options,
            ], 'text');
        }

        return $translatedTexts;
    }

    private static function resolveStrategy(): Strategy
    {
        $kirby = App::instance();
        $strategyOption = $kirby->option('johannschopplich.content-translator.strategy');

        if ($strategyOption instanceof Strategy) {
            return $strategyOption;
        }

        if ($strategyOption instanceof Closure) {
            return new CallableStrategy($strategyOption);
        }

        if (is_string($strategyOption)) {
            return match ($strategyOption) {
                'deepl' => new DeepLStrategy(),
                'ai' => class_exists(CopilotClient::class)
                    ? new CopilotAIStrategy()
                    : throw new LogicException('Strategy "ai" requires the kirby-copilot plugin'),
                default => throw new LogicException('Unknown strategy "' . $strategyOption . '"'),
            };
        }

        // TODO: remove `translateFn` fallback in v4 – use the `strategy` option instead
        $translateFn = $kirby->option('johannschopplich.content-translator.translateFn');
        if (is_callable($translateFn)) {
            return new CallableStrategy(Closure::fromCallable($translateFn));
        }

        return new DeepLStrategy();
    }

    private static function buildOptions(string $targetLanguage, string|null $sourceLanguage): ExecutionOptions
    {
        return new ExecutionOptions(
            targetLanguage: TranslationLanguage::fromCode($targetLanguage),
            sourceLanguage: $sourceLanguage !== null ? TranslationLanguage::fromCode($sourceLanguage) : null,
        );
    }

    public function copyContent(string $toLanguageCode, string $fromLanguageCode): void
    {
        $this->kirby->impersonate('kirby', function () use ($toLanguageCode, $fromLanguageCode) {
            $defaultLanguage = $this->kirby->defaultLanguage();

            // When copying from the default language to a secondary language,
            // delete the target content file so Kirby's built-in inheritance
            // keeps it in sync with the default language automatically.
            // TODO: Remove `method_exists` check in the next major version
            if (
                $defaultLanguage !== null &&
                $defaultLanguage->code() === $fromLanguageCode &&
                $defaultLanguage->code() !== $toLanguageCode &&
                method_exists($this->model, 'version')
            ) {
                $this->model->version()->delete($toLanguageCode);
                return;
            }

            $content = [];

            foreach ($this->fields as $field => $props) {
                if ($this->config->isTranslatable($field, $props)) {
                    $content[$field] = $this->model->content($fromLanguageCode)->get($field)->value();
                }
            }

            $this->model = $this->model->update($content, $toLanguageCode);
        });
    }

    /**
     * @throws TranslationException
     * @throws LogicException
     * @throws AuthException
     */
    public function translateContent(string $contentLanguageCode, string $toLanguageCode, string|null $fromLanguageCode = null, Strategy|null $strategy = null): void
    {
        $this->kirby->impersonate('kirby', function () use ($contentLanguageCode, $toLanguageCode, $fromLanguageCode, $strategy) {
            $content = $this->model->content($contentLanguageCode)->toArray();

            $fields = array_filter(
                $this->fields,
                fn ($props, $fieldName) => $this->config->isTranslatable($fieldName, $props),
                ARRAY_FILTER_USE_BOTH
            );

            $collected = (new Collector($fields, $this->config))->collect($content);

            if ($collected->translations !== []) {
                $strategy ??= self::resolveStrategy();
                $options = self::buildOptions($toLanguageCode, $fromLanguageCode);

                $processedUnits = array_map(
                    fn ($collectedTranslation): TranslationUnit => new TranslationUnit(
                        text: $this->kirby->apply('content-translator.translate:before', [
                            'text' => $collectedTranslation->unit->text,
                            'targetLanguage' => $toLanguageCode,
                            'sourceLanguage' => $fromLanguageCode,
                            'type' => 'text',
                            'unit' => $collectedTranslation->unit,
                            'options' => $options,
                        ], 'text'),
                        mode: $collectedTranslation->unit->mode,
                        fieldKey: $collectedTranslation->unit->fieldKey,
                    ),
                    $collected->translations,
                );

                $translations = $strategy->execute($processedUnits, $options);

                foreach ($collected->translations as $index => $collectedTranslation) {
                    $translatedText = $this->kirby->apply('content-translator.translate:after', [
                        'text' => $translations[$index],
                        'originalText' => $collectedTranslation->unit->text,
                        'targetLanguage' => $toLanguageCode,
                        'sourceLanguage' => $fromLanguageCode,
                        'type' => 'text',
                        'unit' => $processedUnits[$index],
                        'options' => $options,
                    ], 'text');
                    ($collectedTranslation->writeBack)($translatedText);
                }

                foreach ($collected->finalizers as $finalize) {
                    $finalize();
                }
            }

            $this->model = $this->model->update($content, $contentLanguageCode);
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
                $translatedTitle = self::translateText(
                    text: $originalTitle,
                    targetLanguage: $toLanguageCode,
                    sourceLanguage: $fromLanguageCode,
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

            $translatedSlug = self::translateText(
                text: $originalSlug,
                targetLanguage: $toLanguageCode,
                sourceLanguage: $fromLanguageCode,
            );

            $this->model = $this->model->changeSlug($translatedSlug, $contentLanguageCode);
        });
    }
}
