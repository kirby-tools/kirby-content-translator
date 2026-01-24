<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing;

/**
 * @deprecated Use LicensePanel instead. Will be removed in 1.0.0.
 *
 * Backward compatibility wrapper for `PluginLicenseExtensions`.
 * This class proxies all calls to the new `LicensePanel` class.
 *
 * @link      https://kirby.tools
 * @copyright Johann Schopplich
 * @license   AGPL-3.0
 */
class PluginLicenseExtensions
{
    /**
     * Maps exception messages from license activation to translation keys.
     *
     * @deprecated Use `LicensePanel::ACTIVATION_ERROR_KEYS` instead.
     */
    public const ACTIVATION_ERROR_KEYS = LicensePanel::ACTIVATION_ERROR_KEYS;

    /**
     * @deprecated Use `LicensePanel::api()` instead.
     */
    public static function api(string $packageName): array
    {
        return LicensePanel::api($packageName);
    }

    /**
     * @deprecated Use `LicensePanel::dialogs()` instead.
     */
    public static function dialogs(string $packageName, string $pluginLabel): array
    {
        return LicensePanel::dialogs($packageName, $pluginLabel);
    }

    /**
     * @deprecated Use `LicensePanel::translations()` instead.
     */
    public static function translations(): array
    {
        return LicensePanel::translations();
    }

    /**
     * @deprecated Use `LicenseUtils::toPackageSlug()` instead.
     */
    public static function toPackageSlug(string $packageName): string
    {
        return LicenseUtils::toPackageSlug($packageName);
    }

    /**
     * @deprecated Use `LicenseUtils::toPluginId()` instead.
     */
    public static function toPluginId(string $packageName): string
    {
        return LicenseUtils::toPluginId($packageName);
    }

    /**
     * @deprecated Use `LicenseUtils::toApiPrefix()` instead.
     */
    public static function toApiPrefix(string $packageName): string
    {
        return LicenseUtils::toApiPrefix($packageName);
    }

    /**
     * @deprecated Use `LicenseUtils::formatCompatibility()` instead.
     */
    public static function formatCompatibility(string $compatibility): string
    {
        return LicenseUtils::formatCompatibility($compatibility);
    }
}
