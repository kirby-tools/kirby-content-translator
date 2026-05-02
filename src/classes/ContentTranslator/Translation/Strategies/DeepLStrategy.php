<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation\Strategies;

use JohannSchopplich\ContentTranslator\DeepL;
use JohannSchopplich\ContentTranslator\Translation\Exception\TranslationException;
use JohannSchopplich\ContentTranslator\Translation\ExecutionOptions;
use JohannSchopplich\ContentTranslator\Translation\Strategy;
use JohannSchopplich\ContentTranslator\Translation\TranslationMode;
use JohannSchopplich\ContentTranslator\Translation\TranslationUnit;
use Kirby\Cms\App;
use Throwable;

/**
 * DeepL translation strategy.
 */
final readonly class DeepLStrategy implements Strategy
{
    public function __construct(
        private DeepL|null $deepL = null,
    ) {
    }

    public function execute(array $units, ExecutionOptions $options): array
    {
        if ($units === []) {
            return [];
        }

        $client = $this->client();
        $targetCode = $options->targetLanguage->code;
        $sourceCode = $options->sourceLanguage?->code;

        ['batch' => $batchUnits, 'single' => $singleUnits] = self::partitionByMode($units);
        $results = array_map(static fn (TranslationUnit $unit): string => $unit->text, $units);
        $translatedCount = 0;
        $lastError = null;

        if ($batchUnits !== []) {
            $batchTexts = array_map(static fn (array $entry): string => $entry[1]->text, $batchUnits);
            try {
                $translated = $client->translateMany($batchTexts, $targetCode, $sourceCode);
                foreach ($batchUnits as $batchIndex => [$index]) {
                    $results[$index] = $translated[$batchIndex];
                }
                $translatedCount += count($batchUnits);
            } catch (Throwable $error) {
                $lastError = $error;
                foreach ($batchUnits as [, $unit]) {
                    self::warn($unit, $error->getMessage(), $error);
                }
            }
        }

        foreach ($singleUnits as [$index, $unit]) {
            try {
                $results[$index] = $client->translateMany([$unit->text], $targetCode, $sourceCode)[0];
                $translatedCount++;
            } catch (Throwable $error) {
                $lastError = $error;
                self::warn($unit, $error->getMessage(), $error);
            }
        }

        if ($translatedCount === 0) {
            throw new TranslationException(
                strategy: 'deepl',
                reason: $lastError?->getMessage() ?? 'unknown error',
                unitsAttempted: count($units),
                previous: $lastError,
            );
        }

        return $results;
    }

    private static function warn(TranslationUnit $unit, string $reason, Throwable|null $previous): void
    {
        App::instance()->trigger('content-translator.translate:warning', [
            'unit' => $unit,
            'reason' => $reason,
            'previous' => $previous,
        ]);
    }

    private function client(): DeepL
    {
        return $this->deepL ?? DeepL::instance();
    }

    /**
     * @param list<TranslationUnit> $units
     * @return array{batch: list<array{int, TranslationUnit}>, single: list<array{int, TranslationUnit}>}
     */
    private static function partitionByMode(array $units): array
    {
        $batchUnits = [];
        $singleUnits = [];

        foreach ($units as $index => $unit) {
            if ($unit->mode === TranslationMode::Single) {
                $singleUnits[] = [$index, $unit];
            } else {
                $batchUnits[] = [$index, $unit];
            }
        }

        return ['batch' => $batchUnits, 'single' => $singleUnits];
    }
}
