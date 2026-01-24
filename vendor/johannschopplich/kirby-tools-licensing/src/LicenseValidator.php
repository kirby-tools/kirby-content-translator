<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing;

use Composer\Semver\Semver;
use Kirby\Exception\LogicException;

/**
 * Validates license keys and version compatibility for Kirby Tools plugins.
 *
 * @link      https://kirby.tools
 * @copyright Johann Schopplich
 * @license   AGPL-3.0
 */
class LicenseValidator
{
    protected const LICENSE_PATTERN = '!^KT(\d+)-\w+-\w+$!';

    public function __construct(
        protected string $packageName
    ) {
    }

    /**
     * Validates if a license key matches the expected format.
     */
    public function isValid(string|null $licenseKey): bool
    {
        return $licenseKey !== null && preg_match(static::LICENSE_PATTERN, $licenseKey) === 1;
    }

    /**
     * Checks if the current plugin version is compatible with the license.
     */
    public function isCompatible(string|null $versionConstraint): bool
    {
        $version = $this->getPluginVersion();

        if ($version !== null && str_starts_with($version, 'dev-')) {
            throw new LogicException('Development versions are not supported');
        }

        return $versionConstraint !== null
            && $version !== null
            && Semver::satisfies($version, $versionConstraint);
    }

    /**
     * Checks if the license can be upgraded to support the current version.
     */
    public function isUpgradeable(string|null $versionConstraint): bool
    {
        if ($versionConstraint === null) {
            return false;
        }

        $version = $this->getPluginVersion();
        if ($version === null) {
            return false;
        }

        // Parse version constraint to get major versions
        $constraints = explode('||', $versionConstraint);
        $maxLicensedMajor = 0;

        foreach ($constraints as $constraint) {
            $constraint = trim($constraint);
            if (preg_match('/\^(\d+)/', $constraint, $matches)) {
                $maxLicensedMajor = max($maxLicensedMajor, (int)$matches[1]);
            }
        }

        // Get current version major
        if (preg_match('/^(\d+)\./', $version, $matches)) {
            $currentMajor = (int)$matches[1];
            // If current major is higher than max supported major, it's upgradeable
            return $currentMajor > $maxLicensedMajor;
        }

        return false;
    }

    /**
     * Extracts the license generation from a license key.
     */
    public function getLicenseGeneration(string|null $licenseKey): int|null
    {
        if ($licenseKey !== null && preg_match(static::LICENSE_PATTERN, $licenseKey, $matches) === 1) {
            return (int)$matches[1];
        }

        return null;
    }

    /**
     * Gets the current plugin version.
     */
    public function getPluginVersion(): string|null
    {
        return LicenseUtils::getPluginVersion($this->packageName);
    }
}
