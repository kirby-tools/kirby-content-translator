<?php

declare(strict_types = 1);

namespace KirbyTools\Licensing;

use Closure;
use Exception;
use Kirby\Cms\App;
use Kirby\Data\Data;
use Kirby\Data\Json;
use Kirby\Filesystem\F;
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

        if (is_file($this->licenseFile) === false) {
            return $this->cache = [];
        }

        try {
            $this->cache = $this->readFile();
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
        $this->update(function (array $licenses) use ($packageName, $license, $pluginVersion): array {
            // `licenseCompatibility` is the compatibility constraint under the name the
            // licensing API sends and every installed license file stores.
            $licenses[$packageName] = [
                'licenseKey' => $license['licenseKey'],
                'licenseCompatibility' => $license['licenseCompatibility'],
                'pluginVersion' => $pluginVersion,
                'createdAt' => $license['order']['createdAt']
            ];

            return $licenses;
        });

        $this->readError = null;
    }

    public function remove(string $packageName): void
    {
        $this->update(function (array $licenses) use ($packageName): array {
            unset($licenses[$packageName]);

            return $licenses;
        });
    }

    /**
     * Reads the license file under a shared lock, so that a write in progress,
     * which truncates the file first, never reads as an empty file.
     */
    private function readFile(): array
    {
        $handle = @fopen($this->licenseFile, 'r');

        if ($handle === false) {
            throw new Exception('The file "' . $this->licenseFile . '" cannot be read');
        }

        try {
            flock($handle, LOCK_SH);
            return Json::decode(stream_get_contents($handle));
        } finally {
            fclose($handle);
        }
    }

    /**
     * Rewrites the license file from its current contents rather than the
     * cache, so that a license another request stored meanwhile stays. A file
     * that cannot be parsed starts over.
     */
    private function update(Closure $modify): void
    {
        $licenses = [];
        $isWritten = F::update($this->licenseFile, function (string $contents) use ($modify, &$licenses): string {
            $licenses = $modify(Data::decode($contents, 'json', fail: false));
            return Json::encode($licenses);
        });

        if ($isWritten === false) {
            throw new Exception('The file "' . $this->licenseFile . '" is not writable');
        }

        $this->cache = $licenses;
    }
}
