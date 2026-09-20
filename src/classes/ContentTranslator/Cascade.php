<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator;

use Kirby\Cms\File;
use Kirby\Cms\Find;
use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Exception\NotFoundException;
use Kirby\Toolkit\A;

final class Cascade
{
    /**
     * Resolves the models the host's blueprint names in its `cascade` option
     * and the user may update, keyed by their Panel API path.
     *
     * The queries come from the blueprint only, never from a request: a Kirby
     * query can call any method of the models it reaches.
     *
     * @return array<string, ModelWithContent>
     */
    public static function models(ModelWithContent $host): array
    {
        $hostPath = self::path($host);
        $models = [];

        foreach (self::queries($host) as $query) {
            $result = $host->query($query);
            $candidates = match (true) {
                $result instanceof ModelWithContent => [$result],
                is_iterable($result) => $result,
                default => []
            };

            foreach ($candidates as $candidate) {
                if (!$candidate instanceof Page && !$candidate instanceof File) {
                    continue;
                }

                $path = self::path($candidate);

                if ($path === $hostPath) {
                    continue;
                }

                // A query reaches models regardless of the user's permissions.
                if (!self::isReachable($path) || !$candidate->permissions()->can('update')) {
                    continue;
                }

                $models[$path] = $candidate;
            }
        }

        return $models;
    }

    /**
     * Collects the queries of the `cascade` option from the host's
     * `content-translator` sections and view button.
     *
     * Reads the raw section props rather than `Blueprint::sections()`, which
     * would instantiate every section of the host.
     *
     * @return list<string>
     */
    private static function queries(ModelWithContent $host): array
    {
        $options = [];

        foreach ($host->blueprint()->tabs() as $tab) {
            foreach ($tab['columns'] ?? [] as $column) {
                foreach ($column['sections'] ?? [] as $section) {
                    if (($section['type'] ?? null) === 'content-translator') {
                        $options[] = $section['cascade'] ?? [];
                    }
                }
            }
        }

        $buttons = $host->blueprint()->buttons();

        if (is_array($buttons) && is_array($buttons['content-translator'] ?? null)) {
            $options[] = $buttons['content-translator']['cascade'] ?? [];
        }

        $queries = [];

        foreach ($options as $option) {
            foreach (A::wrap($option) as $query) {
                if (is_string($query)) {
                    $queries[] = $query;
                }
            }
        }

        return $queries;
    }

    /**
     * Checks that Kirby's API resolves the path for the user, as it has to when
     * the Panel loads and saves the model. That rules out more than the model's
     * own `access` option, such as a file of a page the user cannot access.
     */
    private static function isReachable(string $path): bool
    {
        try {
            Find::parent($path);
            return true;
        } catch (NotFoundException) {
            return false;
        }
    }

    /**
     * Returns the Panel API path, such as `pages/notes+exploring`, which also
     * tells a page file from a user file that shares its id.
     */
    private static function path(ModelWithContent $model): string
    {
        return ltrim($model->panel()->url(true), '/');
    }
}
