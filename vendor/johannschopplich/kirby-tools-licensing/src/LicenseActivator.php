<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing;

use JohannSchopplich\Licensing\Http\HttpClientInterface;
use JohannSchopplich\Licensing\Http\KirbyHttpClient;
use Kirby\Cms\App;
use Kirby\Exception\LogicException;
use Kirby\Http\Request;

/**
 * @link      https://kirby.tools
 * @copyright Johann Schopplich
 * @license   AGPL-3.0
 */
final class LicenseActivator
{
    private const API_URL = 'https://repo.kirby.tools/api';

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
     * Thrown messages are matched verbatim by `LicensePanel::ACTIVATION_ERROR_KEYS`
     * to resolve a translation, so they cannot be reworded on their own.
     *
     * @throws LogicException When the license is already activated, belongs to another plugin, does not cover the installed version, or when the licensing API rejects the request (message taken verbatim from the response)
     */
    public function activate(string $email, string $licenseKey): void
    {
        if ($this->isActivated()) {
            throw new LogicException('License key already activated');
        }

        $response = $this->request('auth/activate', [
            'method' => 'POST',
            'data' => [
                'email' => $email,
                'licenseKey' => $licenseKey
            ]
        ]);

        if ($response['packageName'] !== $this->packageName) {
            throw new LogicException('License key not valid for this plugin');
        }

        $compatibility = $response['licenseCompatibility'];

        if (!$this->validator->isCompatible($compatibility)) {
            if ($this->validator->isUpgradeable($compatibility)) {
                throw new LogicException('License key not valid for this plugin version, please upgrade your license');
            }

            throw new LogicException('License key not valid for this plugin version');
        }

        $this->repository->save(
            $this->packageName,
            $response,
            $this->validator->getPluginVersion()
        );
    }

    /**
     * @throws LogicException When `email` or `licenseKey` is missing, or when activation fails
     */
    public function activateFromRequest(Request|null $request = null): array
    {
        $request ??= App::instance()->request();
        $email = $request->get('email');
        // TODO: Remove the `orderId` fallback once all plugins ship with licensing-backend >=0.9.
        $licenseKey = $request->get('licenseKey') ?? $request->get('orderId');

        if (!$email || !$licenseKey) {
            throw new LogicException('Missing license registration parameters "email" or "licenseKey"');
        }

        $this->activate($email, (string)$licenseKey);

        return [
            'code' => 200,
            'status' => 'ok',
            'message' => 'License key successfully activated'
        ];
    }

    /**
     * Refetches the license data when the installed plugin version differs from
     * the one it was last stored for.
     */
    public function refresh(): void
    {
        $licenseKey = $this->repository->getLicenseKey($this->packageName);
        $storedVersion = $this->repository->getPluginVersion($this->packageName);
        $currentVersion = $this->validator->getPluginVersion();

        if (
            $this->validator->isValid($licenseKey) &&
            $currentVersion !== $storedVersion
        ) {
            $response = $this->request('licenses/' . $licenseKey . '/package');
            $this->repository->save($this->packageName, $response, $currentVersion);
        }
    }

    /**
     * Checks whether a valid license is stored and covers the installed version.
     */
    public function isActivated(): bool
    {
        $licenseKey = $this->repository->getLicenseKey($this->packageName);
        $compatibility = $this->repository->getLicenseCompatibility($this->packageName);

        return $this->validator->isValid($licenseKey) &&
            $this->validator->isCompatible($compatibility);
    }

    private function request(string $path, array $options = []): array
    {
        $headers = $options['headers'] ?? [];

        if (($pluginVersion = LicenseUtils::getPluginVersion($this->packageName)) !== null) {
            $headers['X-Plugin-Version'] = $pluginVersion;
        }

        if (($kirbyVersion = App::instance()->version()) !== null) {
            $headers['X-Kirby-Version'] = $kirbyVersion;
        }

        $options['headers'] = $headers;

        return $this->httpClient->request(self::API_URL . '/' . $path, $options);
    }
}
