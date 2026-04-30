<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing;

use Kirby\Cms\App;

/**
 * Utility functions for license-related string operations.
 *
 * @link      https://kirby.tools
 * @copyright Johann Schopplich
 * @license   AGPL-3.0
 */
final class LicenseUtils
{
    /**
     * Gets the current plugin version for a package.
     */
    public static function getPluginVersion(string $packageName): string|null
    {
        $kirbyPluginName = str_replace('/kirby-', '/', $packageName);

        return App::instance()->plugin($kirbyPluginName)?->version();
    }

    /**
     * Converts package name to slug (e.g., `johannschopplich/kirby-copilot` → `johannschopplich-kirby-copilot`)
     */
    public static function toPackageSlug(string $packageName): string
    {
        return str_replace('/', '-', $packageName);
    }

    /**
     * Extracts plugin ID from package name (e.g., `johannschopplich/kirby-copilot` → `copilot`)
     */
    public static function toPluginId(string $packageName): string
    {
        return preg_replace('!^.*/kirby-!', '', $packageName);
    }

    /**
     * Converts package name to API prefix (e.g., `johannschopplich/kirby-copilot` → `__copilot__`)
     */
    public static function toApiPrefix(string $packageName): string
    {
        return '__' . self::toPluginId($packageName) . '__';
    }

    /**
     * Formats a compatibility string like `^1 || ^2 || ^3` into `v1–v3`.
     */
    public static function formatCompatibility(string $compatibility): string
    {
        $versions = array_map(
            fn ($part) => (int)preg_replace('/\D/', '', trim($part)),
            explode('||', $compatibility)
        );

        if (count($versions) <= 1) {
            return 'v' . $versions[0];
        }

        return 'v' . $versions[0] . "\u{2013}" . 'v' . end($versions);
    }
}
