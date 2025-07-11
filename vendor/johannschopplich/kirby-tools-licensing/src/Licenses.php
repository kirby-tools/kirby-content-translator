<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing;

use Composer\Semver\Semver;
use JohannSchopplich\Licensing\Http\HttpClientInterface;
use JohannSchopplich\Licensing\Http\KirbyHttpClient;
use Kirby\Cms\App;
use Kirby\Data\Json;
use Kirby\Exception\LogicException;
use Kirby\Filesystem\F;
use Kirby\Http\Request;
use Throwable;

/**
 * Manages licenses for Kirby Tools plugins.
 *
 * If you're here to learn all about how licenses are managed in Kirby Tools,
 * you're in the right place.
 *
 * However, if you're trying to figure out how to crack the license system,
 * please stop. I've put a lot of effort into creating Kirby Tools and I'm
 * sure you can appreciate that. If you like the plugin, please consider
 * supporting me by purchasing a license. Thank you!
 *
 * @link      https://kirby.tools
 * @copyright Johann Schopplich
 * @license   AGPL-3.0
 */
class Licenses
{
    protected const LICENSE_PATTERN = '!^KT(\d+)-\w+-\w+$!';
    protected const API_URL = 'https://repo.kirby.tools/api';
    protected string $licenseFile;

    public const LICENSE_FILE = '.kirby-tools-licenses';

    public function __construct(
        protected array $licenses,
        protected string $packageName,
        protected HttpClientInterface|null $httpClient = null
    ) {
        $this->licenseFile = dirname(App::instance()->root('license')) . '/' . static::LICENSE_FILE;
        $this->httpClient = $httpClient ?? new KirbyHttpClient();
    }

    public static function read(string $packageName, array $options = []): static
    {
        try {
            $licenses = Json::read(dirname(App::instance()->root('license')) . '/' . static::LICENSE_FILE);
        } catch (Throwable) {
            $licenses = [];
        }

        $instance = new static(
            licenses: $licenses,
            packageName: $packageName,
            httpClient: $options['httpClient'] ?? null
        );
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

        $compatibility = $this->getLicenseCompatibility();

        if ($this->isCompatible($compatibility)) {
            return 'active';
        }

        if ($this->isUpgradeable($compatibility)) {
            return 'upgradeable';
        }

        return 'incompatible';
    }

    public function getLicense(): array|bool
    {
        if (!$this->isActivated()) {
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

        return null;
    }

    public function getLicenseCompatibility(): string|null
    {
        return $this->licenses[$this->packageName]['licenseCompatibility'] ?? null;
    }

    public function getPluginVersion(): string|null
    {
        // Map package name to Kirby plugin name by removing the vendor prefix
        $kirbyPluginName = str_replace('/kirby-', '/', $this->packageName);

        return App::instance()->plugin($kirbyPluginName)?->version();
    }

    public function isActivated(): bool
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

        return $versionConstraint !== null
            && $version !== null
            && Semver::satisfies($version, $versionConstraint);
    }

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

    public function activate(string $email, string|int $orderId): void
    {
        if ($this->isActivated()) {
            throw new LogicException('License key already activated');
        }

        $response = $this->request('auth/activate', [
            'method' => 'POST',
            'data' => [
                'email' => $email,
                'orderId' => $orderId
            ]
        ]);

        if ($response['packageName'] !== $this->packageName) {
            throw new LogicException('License key not valid for this plugin');
        }

        $compatibility = $response['licenseCompatibility'];

        if (!$this->isCompatible($compatibility)) {
            if ($this->isUpgradeable($compatibility)) {
                throw new LogicException('License key not valid for this plugin version, please upgrade your license');
            }

            throw new LogicException('License key not valid for this plugin version');
        }

        $this->update($this->packageName, $response);
    }

    public function activateFromRequest(Request|null $request = null): array
    {
        $request = $request ?? App::instance()->request();
        $email = $request->get('email');
        $orderId = $request->get('orderId');

        if (!$email || !$orderId) {
            throw new LogicException('Missing license registration parameters "email" or "orderId"');
        }

        $this->activate($email, $orderId);

        return [
            'code' => 200,
            'status' => 'ok',
            'message' => 'License key successfully activated'
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

    protected function migration(): void
    {
        // Migration 1: Move license file to license directory
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
    }

    protected function refresh(): void
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

    protected function request(string $path, array $options = []): array
    {
        return $this->httpClient->request(static::API_URL . '/' . $path, $options);
    }
}
