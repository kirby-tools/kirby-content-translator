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

    public function __construct()
    {
        $this->licenseFile = dirname(App::instance()->root('license')) . '/' . self::LICENSE_FILE;
    }

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

    public function get(string $packageName): array|null
    {
        $licenses = $this->readAll();
        return $licenses[$packageName] ?? null;
    }

    public function getLicenseKey(string $packageName): string|null
    {
        return $this->get($packageName)['licenseKey'] ?? null;
    }

    public function getLicenseCompatibility(string $packageName): string|null
    {
        return $this->get($packageName)['licenseCompatibility'] ?? null;
    }

    public function getPluginVersion(string $packageName): string|null
    {
        return $this->get($packageName)['pluginVersion'] ?? null;
    }

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

        $this->cache = $licenses;
    }
}
