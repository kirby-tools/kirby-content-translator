<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing;

use Kirby\Cms\App;
use Kirby\Data\Json;
use Throwable;

/**
 * Stores the licenses of all Kirby Tools plugins in a single JSON file next to
 * Kirby's own license file, keyed by package name.
 *
 * @link      https://kirby.tools
 * @copyright Johann Schopplich
 * @license   AGPL-3.0
 */
final class LicenseRepository
{
    public const LICENSE_FILE = '.kirby-tools-licenses';

    private readonly string $licenseFile;
    private array|null $cache = null;
    private string|null $readError = null;

    public function __construct()
    {
        $this->licenseFile = dirname(App::instance()->root('license')) . '/' . self::LICENSE_FILE;
    }

    public function readAll(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        if (file_exists($this->licenseFile) === false) {
            return $this->cache = [];
        }

        try {
            $this->cache = Json::read($this->licenseFile);
        } catch (Throwable $e) {
            $this->readError = $e->getMessage();
            $this->cache = [];
        }

        return $this->cache;
    }

    public function getReadError(): string|null
    {
        $this->readAll();

        return $this->readError;
    }

    public function get(string $packageName): array|null
    {
        $licenses = $this->readAll();
        return $licenses[$packageName] ?? null;
    }

    public function getLicenseKey(string $packageName): string|null
    {
        return $this->get($packageName)['licenseKey'] ?? null;
    }

    public function getCompatibilityConstraint(string $packageName): string|null
    {
        return $this->get($packageName)['licenseCompatibility'] ?? null;
    }

    public function getPluginVersion(string $packageName): string|null
    {
        return $this->get($packageName)['pluginVersion'] ?? null;
    }

    public function save(string $packageName, array $license, string|null $pluginVersion): void
    {
        $licenses = $this->readAll();

        // `licenseCompatibility` is the compatibility constraint under the name the
        // licensing API sends and every installed license file stores.
        $licenses[$packageName] = [
            'licenseKey' => $license['licenseKey'],
            'licenseCompatibility' => $license['licenseCompatibility'],
            'pluginVersion' => $pluginVersion,
            'createdAt' => $license['order']['createdAt']
        ];

        Json::write($this->licenseFile, $licenses);

        $this->cache = $licenses;
        $this->readError = null;
    }

    public function remove(string $packageName): void
    {
        $licenses = $this->readAll();
        unset($licenses[$packageName]);

        Json::write($this->licenseFile, $licenses);

        $this->cache = $licenses;
    }
}
