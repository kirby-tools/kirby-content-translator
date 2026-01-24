<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing;

use Kirby\Cms\App;
use Kirby\Data\Json;
use Throwable;

/**
 * Handles reading and writing license data for Kirby Tools plugins.
 *
 * @link      https://kirby.tools
 * @copyright Johann Schopplich
 * @license   AGPL-3.0
 */
class LicenseRepository
{
    public const LICENSE_FILE = '.kirby-tools-licenses';

    protected string $licenseFile;
    protected array|null $cache = null;

    public function __construct()
    {
        $this->licenseFile = dirname(App::instance()->root('license')) . '/' . static::LICENSE_FILE;
    }

    /**
     * Reads all licenses from the license file.
     */
    public function readAll(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        try {
            $this->cache = Json::read($this->licenseFile);
        } catch (Throwable) {
            $this->cache = [];
        }

        return $this->cache;
    }

    /**
     * Gets a specific license by package name.
     */
    public function get(string $packageName): array|null
    {
        $licenses = $this->readAll();
        return $licenses[$packageName] ?? null;
    }

    /**
     * Gets the license key for a package.
     */
    public function getLicenseKey(string $packageName): string|null
    {
        return $this->get($packageName)['licenseKey'] ?? null;
    }

    /**
     * Gets the license compatibility constraint for a package.
     */
    public function getLicenseCompatibility(string $packageName): string|null
    {
        return $this->get($packageName)['licenseCompatibility'] ?? null;
    }

    /**
     * Gets the stored plugin version for a package.
     */
    public function getPluginVersion(string $packageName): string|null
    {
        return $this->get($packageName)['pluginVersion'] ?? null;
    }

    /**
     * Saves license data for a package.
     */
    public function save(string $packageName, array $data, string|null $pluginVersion): void
    {
        $licenses = $this->readAll();

        $licenses[$packageName] = [
            'licenseKey' => $data['licenseKey'],
            'licenseCompatibility' => $data['licenseCompatibility'],
            'pluginVersion' => $pluginVersion,
            'createdAt' => $data['order']['createdAt']
        ];

        Json::write($this->licenseFile, $licenses);

        // Invalidate cache after write
        $this->cache = $licenses;
    }
}
