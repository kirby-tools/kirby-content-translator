<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing;

use Composer\Semver\Semver;
use UnexpectedValueException;

/**
 * @link      https://kirby.tools
 * @copyright Johann Schopplich
 * @license   AGPL-3.0
 */
final class LicenseValidator
{
    private const LICENSE_PATTERN = '!^KT(\d+)-\w+-\w+$!';

    public function __construct(
        private readonly string $packageName
    ) {
    }

    /**
     * Checks whether a license key has the expected `KT<generation>-…` shape.
     */
    public function isValid(string|null $licenseKey): bool
    {
        return $licenseKey !== null && preg_match(self::LICENSE_PATTERN, $licenseKey) === 1;
    }

    /**
     * Checks whether the installed plugin version satisfies the license's version constraint.
     */
    public function isCompatible(string|null $versionConstraint): bool
    {
        $version = $this->getPluginVersion();

        if ($versionConstraint === null || $version === null) {
            return false;
        }

        try {
            return Semver::satisfies($version, $versionConstraint);
        } catch (UnexpectedValueException) {
            // A hand-edited or truncated license file can hold a constraint
            // Composer cannot parse, which must not escape as a fatal error.
            return false;
        }
    }

    /**
     * Checks whether the installed plugin's major version is newer than every major the license covers.
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

        // The licensing API validates compatibility as `||`-separated caret, tilde
        // or exact versions, so each alternative opens with the major it licenses.
        $maxLicensedMajor = null;

        foreach (explode('||', $versionConstraint) as $constraint) {
            if (preg_match('/^[\^~]?(\d+)/', trim($constraint), $matches)) {
                $maxLicensedMajor = max($maxLicensedMajor ?? 0, (int)$matches[1]);
            }
        }

        // A constraint naming no major at all cannot have been outgrown.
        if ($maxLicensedMajor === null) {
            return false;
        }

        if (preg_match('/^(\d+)\./', $version, $matches)) {
            $currentMajor = (int)$matches[1];

            return $currentMajor > $maxLicensedMajor;
        }

        return false;
    }

    public function getLicenseGeneration(string|null $licenseKey): int|null
    {
        if ($licenseKey !== null && preg_match(self::LICENSE_PATTERN, $licenseKey, $matches) === 1) {
            return (int)$matches[1];
        }

        return null;
    }

    public function getPluginVersion(): string|null
    {
        return LicenseUtils::getPluginVersion($this->packageName);
    }
}
