<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation;

use Kirby\Cms\App;

/**
 * Language identifier (code + display name) for translation operations.
 */
final readonly class TranslationLanguage
{
    public function __construct(
        public string $code,
        public string $name,
    ) {
    }

    public static function fromCode(string $code): static
    {
        $language = App::instance()->languages()->find($code);

        return new static(
            code: $code,
            name: $language?->name() ?? $code,
        );
    }
}
