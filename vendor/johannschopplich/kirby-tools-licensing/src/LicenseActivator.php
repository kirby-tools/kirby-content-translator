<?php

declare(strict_types = 1);

namespace KirbyTools\Licensing;

use Kirby\Cms\App;
use Kirby\Http\Request;
use KirbyTools\Licensing\Http\HttpClientInterface;
use KirbyTools\Licensing\Http\KirbyHttpClient;
use Throwable;
use WeakMap;

/**
 * @link      https://kirby.tools
 * @copyright Johann Schopplich
 * @license   AGPL-3.0
 */
final class LicenseActivator
{
    private const API_URL = 'https://repo.kirby.tools/api';

    /** @var WeakMap<App, array<string, true>>|null */
    private static WeakMap|null $refreshAttempts = null;

    private readonly HttpClientInterface $httpClient;

    public function __construct(
        private readonly string $packageName,
        private readonly LicenseRepository $repository,
        private readonly LicenseValidator $validator,
        HttpClientInterface|null $httpClient = null
    ) {
        $this->httpClient = $httpClient ?? new KirbyHttpClient();
    }

    /**
     * @throws ActivationException When the license is already activated, belongs to another plugin, does not cover the installed version, or when the licensing API rejects the request
     */
    public function activate(string $email, string $licenseKey): void
    {
        if ($this->isActivated()) {
            throw new ActivationException(ActivationOutcome::AlreadyActivated);
        }

        $response = $this->post('plugin/activate', [
            'email' => $email,
            'licenseKey' => $licenseKey
        ]);

        if (($response['packageName'] ?? null) !== $this->packageName) {
            throw new ActivationException(ActivationOutcome::WrongPlugin);
        }

        $compatibilityConstraint = $response['licenseCompatibility'];

        if (!$this->validator->isCompatible($compatibilityConstraint)) {
            if ($this->validator->isUpgradeable($compatibilityConstraint)) {
                throw new ActivationException(ActivationOutcome::Upgradeable);
            }

            throw new ActivationException(ActivationOutcome::Incompatible);
        }

        $this->repository->save(
            $this->packageName,
            $response,
            $this->validator->getPluginVersion()
        );
    }

    /**
     * @throws ActivationException When `email` or `licenseKey` is missing, or when activation fails
     */
    public function activateFromRequest(Request|null $request = null): void
    {
        $request ??= App::instance()->request();
        $email = $request->get('email');
        $licenseKey = $request->get('licenseKey');

        if (!$email || !$licenseKey) {
            throw new ActivationException(ActivationOutcome::MissingParameters);
        }

        $this->activate($email, (string)$licenseKey);
    }

    /**
     * Refetches the license data, at most once per request, when the installed
     * plugin version differs from the one it was last stored for.
     */
    public function refresh(): void
    {
        $kirby = App::instance();
        self::$refreshAttempts ??= new WeakMap();
        $attempts = self::$refreshAttempts[$kirby] ?? [];

        $licenseKey = $this->repository->getLicenseKey($this->packageName);
        $storedVersion = $this->repository->getPluginVersion($this->packageName);
        $currentVersion = $this->validator->getPluginVersion();

        if (
            !isset($attempts[$this->packageName]) &&
            $this->validator->isValid($licenseKey) &&
            $currentVersion !== $storedVersion
        ) {
            self::$refreshAttempts[$kirby] = [...$attempts, $this->packageName => true];

            try {
                $response = $this->post('plugin/refresh', [
                    'licenseKey' => $licenseKey
                ]);
            } catch (Throwable $e) {
                // The licensing API answers 401 for a license key it does not
                // know. Any other failure keeps the stored license, so the next
                // request refreshes again.
                if ($e instanceof ActivationException && $e->outcome === ActivationOutcome::InvalidCredentials) {
                    $this->repository->remove($this->packageName);
                }

                return;
            }

            $this->repository->save($this->packageName, $response, $currentVersion);
        }
    }

    /**
     * Checks whether a valid license is stored and covers the installed version.
     */
    public function isActivated(): bool
    {
        $licenseKey = $this->repository->getLicenseKey($this->packageName);
        $compatibilityConstraint = $this->repository->getCompatibilityConstraint($this->packageName);

        return $this->validator->isValid($licenseKey) &&
            $this->validator->isCompatible($compatibilityConstraint);
    }

    private function post(string $path, array $data): array
    {
        $headers = [];

        if (($pluginVersion = LicenseUtils::getPluginVersion($this->packageName)) !== null) {
            $headers['X-Plugin-Version'] = $pluginVersion;
        }

        if (($kirbyVersion = App::instance()->version()) !== null) {
            $headers['X-Kirby-Version'] = $kirbyVersion;
        }

        return $this->httpClient->request(self::API_URL . '/' . $path, [
            'method' => 'POST',
            'data' => $data,
            'headers' => $headers
        ]);
    }
}
