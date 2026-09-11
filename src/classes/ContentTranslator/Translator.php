<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator;

use Closure;
use JohannSchopplich\ContentTranslator\Translation\BatchTranslationResult;
use JohannSchopplich\ContentTranslator\Translation\Collector;
use JohannSchopplich\ContentTranslator\Translation\ContentTranslationResult;
use JohannSchopplich\ContentTranslator\Translation\Exception\TranslationException;
use JohannSchopplich\ContentTranslator\Translation\ExecutionOptions;
use JohannSchopplich\ContentTranslator\Translation\Strategies\CallableStrategy;
use JohannSchopplich\ContentTranslator\Translation\Strategies\CopilotAIStrategy;
use JohannSchopplich\ContentTranslator\Translation\Strategies\DeepLStrategy;
use JohannSchopplich\ContentTranslator\Translation\Strategy;
use JohannSchopplich\ContentTranslator\Translation\TranslationLanguage;
use JohannSchopplich\ContentTranslator\Translation\TranslationRejection;
use JohannSchopplich\ContentTranslator\Translation\TranslationUnit;
use JohannSchopplich\ContentTranslator\Translation\UntranslatableText;
use JohannSchopplich\Copilot\AI\Client as CopilotClient;
use JohannSchopplich\KirbyTools\FieldResolver;
use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Exception\AuthException;
use Kirby\Exception\InvalidArgumentException;
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
     * @throws TranslationException When the strategy translates no unit at all, including when the provider rejects the request
     * @throws LogicException When the configured strategy cannot be resolved: an unknown `strategy` value, or `'ai'` without the kirby-copilot plugin
     * @throws AuthException When the DeepL API key is missing
     * @throws InvalidArgumentException When a language code is not registered in the site's languages
     */
    public static function translateText(string $text, string $targetLanguage, string|null $sourceLanguage = null, Strategy|null $strategy = null): string
    {
        $result = self::translateTexts([$text], $targetLanguage, $sourceLanguage, $strategy);
        return $result[0];
    }

    /**
     * @param list<string> $texts
     * @return list<string>
     *
     * @throws TranslationException When the strategy translates no unit at all, including when the provider rejects the request
     * @throws LogicException When the configured strategy cannot be resolved: an unknown `strategy` value, or `'ai'` without the kirby-copilot plugin
     * @throws AuthException When the DeepL API key is missing
     * @throws InvalidArgumentException When a language code is not registered in the site's languages
     */
    public static function translateTexts(array $texts, string $targetLanguage, string|null $sourceLanguage = null, Strategy|null $strategy = null): array
    {
        return self::translateBatch($texts, $targetLanguage, $sourceLanguage, $strategy)->texts;
    }

    /**
     * Translates `$texts` and names the positions that kept their source text.
     *
     * The Panel needs them because a strategy running on the server hands back
     * the source text for a rejected unit, which no caller can tell apart from
     * a translation that legitimately equals its source.
     *
     * @internal Serves the batch API route, not the published surface.
     *
     * @param list<string> $texts
     *
     * @throws TranslationException When the strategy translates no unit at all, including when the provider rejects the request
     * @throws LogicException When the configured strategy cannot be resolved: an unknown `strategy` value, or `'ai'` without the kirby-copilot plugin
     * @throws AuthException When the DeepL API key is missing
     * @throws InvalidArgumentException When a language code is not registered in the site's languages
     */
    public static function translateBatch(array $texts, string $targetLanguage, string|null $sourceLanguage = null, Strategy|null $strategy = null): BatchTranslationResult
    {
        if ($texts === []) {
            return new BatchTranslationResult([], [], 0, 0);
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

        $translatedResult = self::translateUnits($units, $strategy, $options);

        $translatedTexts = [];
        foreach ($translatedResult->texts as $index => $translatedText) {
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

        return new BatchTranslationResult(
            $translatedTexts,
            $translatedResult->rejections,
            $translatedResult->translatableCount,
            $translatedResult->translatedCount,
        );
    }

    /**
     * Names the strategy the Panel would reach through the translate endpoint.
     *
     * @return 'ai'|'custom'|'deepl'
     *
     * @throws LogicException When the `strategy` option names an unknown strategy
     */
    public static function resolveStrategyName(): string
    {
        $source = self::resolveStrategySource();
        return is_string($source) ? $source : 'custom';
    }

    public function copyContent(string $toLanguageCode, string $fromLanguageCode): void
    {
        $this->kirby->impersonate('kirby', function () use ($toLanguageCode, $fromLanguageCode) {
            $defaultLanguage = $this->kirby->defaultLanguage();

            // When copying from the default language to a secondary language,
            // delete the target content file so Kirby's built-in inheritance
            // keeps it in sync with the default language automatically.
            // TODO: Remove `method_exists` check in the next major version.
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
                if ($this->config->isEligibleField($field, $props)) {
                    $content[$field] = $this->model->content($fromLanguageCode)->get($field)->value();
                }
            }

            $this->model = $this->model->update($content, $toLanguageCode);
        });
    }

    /**
     * @throws TranslationException When the strategy translates no unit at all, including when the provider rejects the request
     * @throws LogicException When the configured strategy cannot be resolved: an unknown `strategy` value, or `'ai'` without the kirby-copilot plugin
     * @throws AuthException When the DeepL API key is missing
     * @throws InvalidArgumentException When a language code is not registered in the site's languages
     */
    public function translateContent(string $contentLanguageCode, string $toLanguageCode, string|null $fromLanguageCode = null, Strategy|null $strategy = null): ContentTranslationResult
    {
        return $this->kirby->impersonate('kirby', function () use ($contentLanguageCode, $toLanguageCode, $fromLanguageCode, $strategy) {
            $content = $this->model->content($contentLanguageCode)->toArray();
            $result = (new Collector($this->fields, $this->config))->collect($content);
            $contentResult = new ContentTranslationResult(0, 0, []);

            if ($result->translations !== []) {
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
                        fieldKey: $collectedTranslation->unit->fieldKey,
                    ),
                    $result->translations,
                );

                $unitResult = self::translateUnits($processedUnits, $strategy, $options);
                $translations = $unitResult->texts;
                $contentResult = new ContentTranslationResult(
                    $unitResult->translatableCount,
                    $unitResult->translatedCount,
                    $unitResult->rejections,
                );

                foreach ($result->translations as $index => $collectedTranslation) {
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

                foreach ($result->finalizers as $finalize) {
                    $finalize();
                }
            }

            $this->model = $this->model->update($content, $contentLanguageCode);

            return $contentResult;
        });
    }

    public function translateTitle(string $contentLanguageCode, string $toLanguageCode, string|null $fromLanguageCode = null): void
    {
        $this->kirby->impersonate('kirby', function () use ($contentLanguageCode, $toLanguageCode, $fromLanguageCode) {
            $originalTitle = $this->model->content($contentLanguageCode)->get('title')->value();

            if (
                ($originalTitle === null || $originalTitle === '') &&
                $fromLanguageCode !== null &&
                $fromLanguageCode !== '' &&
                $fromLanguageCode !== $contentLanguageCode
            ) {
                $originalTitle = $this->model->content($fromLanguageCode)->get('title')->value();
            }

            if ($originalTitle !== null && $originalTitle !== '') {
                $result = self::translateBatch(
                    texts: [$originalTitle],
                    targetLanguage: $toLanguageCode,
                    sourceLanguage: $fromLanguageCode,
                );

                // A rejected answer hands back the source title, and writing
                // that would overwrite the target title with the wrong language.
                if ($result->rejections === []) {
                    $this->model = $this->model->changeTitle($result->texts[0], $contentLanguageCode);
                }
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

            $result = self::translateBatch(
                texts: [$originalSlug],
                targetLanguage: $toLanguageCode,
                sourceLanguage: $fromLanguageCode,
            );

            if ($result->rejections === []) {
                $this->model = $this->model->changeSlug($result->texts[0], $contentLanguageCode);
            }
        });
    }

    /**
     * Sends only the units worth translating to the strategy, splicing source
     * text into the untranslatable slots so callers keep a 1:1 mapping with `$units`.
     *
     * Also enforces the KirbyTag placeholder invariant here rather than inside
     * a strategy, so every strategy is covered – including user-supplied ones.
     *
     * @param list<TranslationUnit> $units
     */
    private static function translateUnits(array $units, Strategy $strategy, ExecutionOptions $options): BatchTranslationResult
    {
        $results = array_map(static fn (TranslationUnit $unit): string => $unit->text, $units);
        $rejections = [];

        $translatableIndexes = [];
        $translatableUnits = [];

        foreach ($units as $index => $unit) {
            if (!UntranslatableText::matches($unit->text)) {
                $translatableIndexes[] = $index;
                $translatableUnits[] = $unit;
            }
        }

        if ($translatableUnits === []) {
            return new BatchTranslationResult($results, [], 0, 0);
        }

        $translations = $strategy->execute($translatableUnits, $options);
        $translatedCount = 0;

        // Iterate our own indexes: a `Strategy` that ignores the `list<string>`
        // contract must not be able to write outside the result list.
        foreach ($translatableIndexes as $position => $index) {
            $unit = $translatableUnits[$position];

            if (!array_key_exists($position, $translations)) {
                $rejections[] = self::reject($unit, $index, 'missing translation');
                continue;
            }

            $translation = $translations[$position];

            // A strategy that hands back `null` has already fired the hook with
            // the real reason, so warning again would report one rejection twice.
            if ($translation === null) {
                $rejections[] = new TranslationRejection($index, 'missing translation', $unit->fieldKey);
                continue;
            }

            if (!is_string($translation)) {
                $rejections[] = self::reject($unit, $index, 'non-string translation');
                continue;
            }

            // `UntranslatableText` already dropped the blank sources, so a unit
            // that reaches a strategy cannot legitimately come back blank.
            if (UntranslatableText::isBlank($translation)) {
                $rejections[] = self::reject($unit, $index, 'empty translation');
                continue;
            }

            $expectedIndexes = self::placeholderIndexes($unit->text);
            $actualIndexes = self::placeholderIndexes($translation);

            if ($expectedIndexes !== $actualIndexes) {
                $rejections[] = self::reject($unit, $index, 'placeholder mismatch', $expectedIndexes, $actualIndexes);
                continue;
            }

            $results[$index] = $translation;
            $translatedCount++;
        }

        return new BatchTranslationResult($results, $rejections, count($translatableUnits), $translatedCount);
    }

    /**
     * The `<cN/>` indexes a text carries, sorted so two texts compare directly.
     * A lost or invented KirbyTag placeholder means the `restore` closure from
     * `KirbyText::split()` can no longer rebuild the tag, and counting alone
     * would accept `<c0/> <c0/>` for a source holding `<c0/> <c1/>`, which
     * rebuilds tag 0 twice and drops tag 1.
     *
     * @return list<int>
     */
    private static function placeholderIndexes(string $text): array
    {
        preg_match_all(KirbyText::PLACEHOLDER_PATTERN, $text, $matches);

        $indexes = array_map(intval(...), $matches[1]);
        sort($indexes);

        return $indexes;
    }

    /**
     * @param list<int>|null $expectedIndexes
     * @param list<int>|null $actualIndexes
     */
    private static function reject(TranslationUnit $unit, int $index, string $reason, array|null $expectedIndexes = null, array|null $actualIndexes = null): TranslationRejection
    {
        self::warn($unit, $reason);
        return new TranslationRejection($index, $reason, $unit->fieldKey, $expectedIndexes, $actualIndexes);
    }

    private static function warn(TranslationUnit $unit, string $reason): void
    {
        // TODO: Next major version – back the reason strings with an enum. This
        // hook publishes them, so a consumer branches on them, yet a rename is
        // invisible to the compiler and to both test suites.
        App::instance()->trigger('content-translator.translate:warning', [
            'unit' => $unit,
            'reason' => $reason,
            'previous' => null,
        ]);
    }

    private static function resolveStrategy(): Strategy
    {
        $source = self::resolveStrategySource();

        if ($source instanceof Strategy) {
            return $source;
        }

        if ($source instanceof Closure) {
            return new CallableStrategy($source);
        }

        return match ($source) {
            'deepl' => new DeepLStrategy(),
            'ai' => class_exists(CopilotClient::class)
                ? new CopilotAIStrategy()
                : throw new LogicException('Strategy "ai" requires the kirby-copilot plugin'),
        };
    }

    /**
     * @return 'ai'|'deepl'|Closure|Strategy
     *
     * @throws LogicException When the `strategy` option names an unknown strategy
     */
    private static function resolveStrategySource(): string|Closure|Strategy
    {
        $kirby = App::instance();
        $strategyOption = $kirby->option('johannschopplich.content-translator.strategy');

        if ($strategyOption instanceof Strategy || $strategyOption instanceof Closure) {
            return $strategyOption;
        }

        if (is_string($strategyOption)) {
            return match ($strategyOption) {
                'deepl', 'ai' => $strategyOption,
                default => throw new LogicException('Unknown strategy "' . $strategyOption . '"'),
            };
        }

        // TODO: Remove the `translateFn` fallback in v4 – use the `strategy` option instead.
        $translateFn = $kirby->option('johannschopplich.content-translator.translateFn');
        if (is_callable($translateFn)) {
            return Closure::fromCallable($translateFn);
        }

        return 'deepl';
    }

    private static function buildOptions(string $targetLanguage, string|null $sourceLanguage): ExecutionOptions
    {
        return new ExecutionOptions(
            targetLanguage: TranslationLanguage::fromCode($targetLanguage),
            sourceLanguage: $sourceLanguage !== null ? TranslationLanguage::fromCode($sourceLanguage) : null,
        );
    }
}
