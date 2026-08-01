<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator\Translation;

use Kirby\Cms\App;
use Kirby\Exception\InvalidArgumentException;

/**
 * Language identifier for translation operations.
 *
 * Carries everything a {@see Strategy} may need to name the language, so no
 * strategy has to reach back into Kirby's language registry: the display name
 * for a prompt, the locale for a provider that resolves regional variants.
 */
final readonly class TranslationLanguage
{
    public function __construct(
        public string $code,
        public string $name,
        public string|null $locale = null,
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

        // Kirby falls back to a per-category locale array, which names no single
        // language and is unusable here
        $locale = $language?->locale(LC_ALL);

        return new self(
            code: $code,
            name: $language?->name() ?? $code,
            locale: is_string($locale) ? $locale : null,
        );
    }
}
