<?php

declare(strict_types = 1);

namespace KirbyTools\Licensing;

use Kirby\Cms\App;

/**
 * @link      https://kirby.tools
 * @copyright Johann Schopplich
 * @license   AGPL-3.0
 */
final class LicenseUtils
{
    public static function getPluginVersion(string $packageName): string|null
    {
        // Kirby registers plugins without the `kirby-` prefix the Composer package name carries.
        $kirbyPluginName = str_replace('/kirby-', '/', $packageName);

        return App::instance()->plugin($kirbyPluginName)?->version();
    }

    /**
     * Converts package name to slug (e.g., `johannschopplich/kirby-copilot` → `johannschopplich-kirby-copilot`).
     */
    public static function toPackageSlug(string $packageName): string
    {
        return str_replace('/', '-', $packageName);
    }

    /**
     * Builds the ID of the activation dialog `LicensePanel::dialogs()` registers
     * (e.g., `johannschopplich/kirby-copilot` → `johannschopplich-kirby-copilot/activate`).
     */
    public static function toActivationDialog(string $packageName): string
    {
        return self::toPackageSlug($packageName) . '/activate';
    }

    /**
     * Extracts plugin ID from package name (e.g., `johannschopplich/kirby-copilot` → `copilot`).
     */
    public static function toPluginId(string $packageName): string
    {
        return preg_replace('!^.*/kirby-!', '', $packageName);
    }

    /**
     * Converts a compatibility constraint like `^1 || ^2 || ^3` into the
     * compatible versions `v1–v3` customers read.
     */
    public static function toCompatibleVersions(string $compatibilityConstraint): string
    {
        // Only the leading major of each alternative counts, so tilde and exact
        // constraints like `~1.2` must not collapse into their digits.
        $versions = array_map(
            fn ($part) => preg_match('/^[\^~]?(\d+)/', trim($part), $matches) ? (int)$matches[1] : 0,
            explode('||', $compatibilityConstraint)
        );

        if (count($versions) <= 1) {
            return 'v' . $versions[0];
        }

        return 'v' . $versions[0] . "\u{2013}" . 'v' . end($versions);
    }
}
