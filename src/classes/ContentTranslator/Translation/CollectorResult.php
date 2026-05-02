<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation;

use Closure;

/**
 * @internal
 */
final readonly class CollectorResult
{
    /**
     * @param list<CollectedTranslation> $translations
     * @param list<Closure(): void> $finalizers
     */
    public function __construct(
        public array $translations,
        public array $finalizers,
    ) {
    }
}
