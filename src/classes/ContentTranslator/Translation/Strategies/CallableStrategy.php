<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation\Strategies;

use Closure;
use JohannSchopplich\ContentTranslator\Translation\ExecutionOptions;
use JohannSchopplich\ContentTranslator\Translation\Strategy;

/**
 * Adapts a user-supplied closure to the {@see Strategy} contract.
 */
final readonly class CallableStrategy implements Strategy
{
    /**
     * @param Closure(string, string, string|null): string $translate
     */
    public function __construct(
        private Closure $translate,
    ) {
    }

    public function execute(array $units, ExecutionOptions $options): array
    {
        $results = [];

        foreach ($units as $unit) {
            $results[] = ($this->translate)(
                $unit->text,
                $options->targetLanguage->code,
                $options->sourceLanguage?->code,
            );
        }

        return $results;
    }
}
