<?php

declare(strict_types = 1);

namespace JohannSchopplich\KirbyTools;

use Kirby\Cms\ModelWithContent;

final class QueryResolver
{
    /**
     * Replaces each `{{ ... }}` placeholder in a string with the result of its
     * Kirby query against the model. Any other value passes through untouched.
     */
    public static function resolve(
        ModelWithContent $model,
        mixed $value,
        mixed $fallback = null
    ): mixed {
        if (is_string($value)) {
            $value = preg_replace_callback(
                '!\{\{(.+?)\}\}!',
                fn (array $matches) => $model->query(trim($matches[1])) ?? '',
                $value
            );
        }

        return $value ?? $fallback;
    }
}
