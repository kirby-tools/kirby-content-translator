<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation\Strategies;

use JohannSchopplich\ContentTranslator\DeepL;
use JohannSchopplich\ContentTranslator\Translation\Exception\TranslationException;
use JohannSchopplich\ContentTranslator\Translation\ExecutionOptions;
use JohannSchopplich\ContentTranslator\Translation\Strategy;
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
        $texts = array_map(static fn (TranslationUnit $unit): string => $unit->text, $units);

        try {
            return $client->translateMany($texts, $options->targetLanguage->code, $options->sourceLanguage?->code);
        } catch (Throwable $error) {
            foreach ($units as $unit) {
                self::warn($unit, $error->getMessage(), $error);
            }

            throw new TranslationException(
                strategy: 'deepl',
                reason: $error->getMessage(),
                unitsAttempted: count($units),
            );
        }
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
}
