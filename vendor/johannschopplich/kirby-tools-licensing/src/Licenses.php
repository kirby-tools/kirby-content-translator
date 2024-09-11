<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing;

use Composer\Semver\Semver;
use Kirby\Cms\App;
use Kirby\Data\Json;
use Kirby\Exception\LogicException;
use Kirby\Filesystem\F;
use Kirby\Http\Remote;
use Kirby\Toolkit\Str;
use Throwable;

/**
 * If you're here to learn all about how licenses are managed in Kirby Tools,
 * you're in the right place.
 *
 * However, if you're trying to figure out how to crack the license system,
 * please stop right here. I've put a lot of effort into creating Kirby Tools
 * and I'm sure you can appreciate that. If you like the plugin, please consider
 * supporting me by purchasing a license. Thank you!
 *
 * @see https://kirby.tools
 */
class Licenses
{
    private const LICENSE_FILE = '.kirby-tools-licenses';
    private const LICENSE_PATTERN = '!^KT(\d+)-\w+-\w+$!';
    private const API_URL = 'https://repo.kirby.tools/api';
    private string $licenseFile;

    public function __construct(
        private array $licenses,
        private string $packageName,
    ) {
        $this->licenseFile = dirname(App::instance()->root('license')) . '/' . static::LICENSE_FILE;
    }

    public static function read(string $packageName, array $options = []): static
    {
        try {
            $licenses = Json::read(dirname(App::instance()->root('license')) . '/' . static::LICENSE_FILE);
        } catch (Throwable) {
            $licenses = [];
        }

        $instance = new static($licenses, $packageName);
        $instance->migration();
        $instance->refresh();

        return $instance;
    }

    public function getStatus(): string
    {
        $licenseKey = $this->getLicenseKey();

        if ($licenseKey === null) {
            return 'inactive';
        }

        if (!$this->isValid($licenseKey)) {
            return 'invalid';
        }

        if (!$this->isCompatible($this->getLicenseCompatibility())) {
            return 'incompatible';
        }

        return 'active';
    }

    public function getLicense(): array|bool
    {
        if (!$this->isRegistered()) {
            return false;
        }

        return [
            'key' => $this->getLicenseKey(),
            'version' => $this->getLicenseVersion(),
            'compatibility' => $this->getLicenseCompatibility()
        ];
    }

    public function getLicenseKey(): string|null
    {
        return $this->licenses[$this->packageName]['licenseKey'] ?? null;
    }

    public function getLicenseVersion(): int|null
    {
        $licenseKey = $this->getLicenseKey();

        if (preg_match(static::LICENSE_PATTERN, $licenseKey, $matches) === 1) {
            return (int)$matches[1];
        }
    }

    public function getLicenseCompatibility(): string|null
    {
        return $this->licenses[$this->packageName]['licenseCompatibility'] ?? null;
    }

    public function getPluginVersion(): string|null
    {
        $kirbyPackageName = str_replace('/kirby-', '/', $this->packageName);
        return App::instance()->plugin($kirbyPackageName)?->version();
    }

    public function isRegistered(): bool
    {
        return $this->isValid($this->getLicenseKey()) && $this->isCompatible($this->getLicenseCompatibility());
    }

    public function isValid(string|null $licenseKey): bool
    {
        return $licenseKey !== null && preg_match(static::LICENSE_PATTERN, $licenseKey) === 1;
    }

    public function isCompatible(string|null $versionConstraint): bool
    {
        $version = $this->getPluginVersion();

        if ($version !== null && str_starts_with($version, 'dev-')) {
            throw new LogicException('Development versions are not supported');
        }

        return $versionConstraint !== null && Semver::satisfies(
            $version,
            $versionConstraint
        );
    }

    public function register(string $email, string|int $orderId): void
    {
        if ($this->isRegistered()) {
            throw new LogicException('License key already registered');
        }

        $response = $this->request('licenses', [
            'method' => 'POST',
            'data' => [
                'email' => $email,
                'orderId' => $orderId
            ]
        ]);

        if ($response['packageName'] !== $this->packageName) {
            throw new LogicException('License key not valid for this plugin');
        }

        if (!$this->isCompatible($response['licenseCompatibility'])) {
            throw new LogicException('License key not valid for this plugin version');
        }

        $this->update($this->packageName, $response);
    }

    public function registerFromRequest(): array
    {
        $request = App::instance()->request();
        $email = $request->get('email');
        $orderId = $request->get('orderId');

        if (!$email || !$orderId) {
            throw new LogicException('Missing license registration parameters "email" or "orderId"');
        }

        $this->register($email, $orderId);

        return [
            'code' => 200,
            'status' => 'ok',
            'message' => 'License key registered successfully'
        ];
    }

    public function update(string $packageName, array $data): void
    {
        $this->licenses[$packageName] = [
            'licenseKey' => $data['licenseKey'],
            'licenseCompatibility' => $data['licenseCompatibility'],
            'pluginVersion' => $this->getPluginVersion(),
            'createdAt' => $data['order']['createdAt']
        ];

        Json::write($this->licenseFile, $this->licenses);
    }

    private function migration(): void
    {
        // Migration 1: Move license file to license directory (if applicable)
        $oldLicenseFile = App::instance()->root('config') . '/' . static::LICENSE_FILE;
        if (F::exists($oldLicenseFile) && $oldLicenseFile !== $this->licenseFile) {
            F::move($oldLicenseFile, $this->licenseFile);
            $this->licenses = Json::read($this->licenseFile);
        }

        // Migration 2: If license value is a string, re-fetch license data from API
        if (is_string($this->licenses[$this->packageName] ?? null)) {
            $response = $this->request('licenses/' . $this->licenses[$this->packageName] . '/package');
            $this->update($this->packageName, $response);
        }

        // Migration 3: Migrate licenses from private Composer repository
        $authFile = App::instance()->root('base') . '/auth.json';
        try {
            $auth = Json::read($authFile);
            $collection = $auth['bearer']['repo.kirby.tools'] ?? null;

            if (empty($collection)) {
                return;
            }

            // Extract all current license keys
            $licenseKeys = array_map(
                fn ($license) => is_array($license) ? $license['licenseKey'] : $license,
                $this->licenses
            );

            // Get package name for licenses and update them
            foreach (Str::split($collection, ',', 8) as $licenseKey) {
                if (!$this->isValid($licenseKey) || in_array($licenseKey, $licenseKeys)) {
                    continue;
                }

                $response = $this->request('licenses/' . $licenseKey . '/package');

                if ($response['packageName'] === $this->packageName) {
                    $this->update($this->packageName, $response);
                }
            }
        } catch (Throwable) {
            // Ignore
        }
    }

    private function refresh(): void
    {
        $currentVersion = $this->licenses[$this->packageName]['pluginVersion'] ?? null;

        // If the plugin version has changed, refresh the license data for the package
        if (
            $this->isValid($this->getLicenseKey()) &&
            $this->getPluginVersion() !== $currentVersion
        ) {
            $response = $this->request('licenses/' . $this->getLicenseKey() . '/package');
            $this->update($this->packageName, $response);
        }
    }

    private function request(string $path, array $options = []): array
    {
        $response = new Remote(static::API_URL . '/' . $path, array_merge($options, [
            'headers' => [
                'X-App-Url' => App::instance()->url()
            ]
        ]));

        if (!in_array($response->code(), [200, 201], true)) {
            $message = $response->json()['message'] ?? 'Request failed';
            throw new LogicException($message, $response->code());
        }

        return $response->json();
    }
}
