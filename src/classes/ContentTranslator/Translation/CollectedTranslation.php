<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation;

use Closure;

/**
 * @internal
 */
final readonly class CollectedTranslation
{
    /**
     * @param Closure(string): void $writeBack
     */
    public function __construct(
        public TranslationUnit $unit,
        public Closure $writeBack,
    ) {
    }
}
