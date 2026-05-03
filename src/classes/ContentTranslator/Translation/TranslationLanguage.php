<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation;

use Kirby\Cms\App;
use Kirby\Exception\InvalidArgumentException;

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

    /**
     * @throws InvalidArgumentException When the code is unknown on a multi-language site.
     */
    public static function fromCode(string $code): self
    {
        $kirby = App::instance();
        $language = $kirby->languages()->find($code);

        if ($language === null && $kirby->multilang()) {
            // TODO: Drop K4 compat in v4 – use named arg (message:) once Kirby 5 is the floor
            throw new InvalidArgumentException(
                ['fallback' => 'Unknown language code "' . $code . '"; not registered in Kirby languages.'],
            );
        }

        return new self(
            code: $code,
            name: $language?->name() ?? $code,
        );
    }
}
