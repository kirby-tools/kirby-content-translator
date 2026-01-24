<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing;

use JohannSchopplich\Licensing\Http\HttpClientInterface;
use JohannSchopplich\Licensing\Http\KirbyHttpClient;
use Kirby\Cms\App;
use Kirby\Exception\LogicException;
use Kirby\Http\Request;

/**
 * Handles license activation for Kirby Tools plugins.
 *
 * @link      https://kirby.tools
 * @copyright Johann Schopplich
 * @license   AGPL-3.0
 */
class LicenseActivator
{
    protected const API_URL = 'https://repo.kirby.tools/api';

    protected HttpClientInterface $httpClient;

    public function __construct(
        protected string $packageName,
        protected LicenseRepository $repository,
        protected LicenseValidator $validator,
        HttpClientInterface|null $httpClient = null
    ) {
        $this->httpClient = $httpClient ?? new KirbyHttpClient();
    }

    /**
     * Activates a license with the given credentials.
     */
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
     * Activates a license from a Kirby request.
     */
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

    /**
     * Refreshes the license data if the plugin version has changed.
     */
    public function refresh(): void
    {
        $licenseKey = $this->repository->getLicenseKey($this->packageName);
        $storedVersion = $this->repository->getPluginVersion($this->packageName);
        $currentVersion = $this->validator->getPluginVersion();

        // If the plugin version has changed, refresh the license data for the package
        if (
            $this->validator->isValid($licenseKey) &&
            $currentVersion !== $storedVersion
        ) {
            $response = $this->request('licenses/' . $licenseKey . '/package');
            $this->repository->save($this->packageName, $response, $currentVersion);
        }
    }

    /**
     * Checks if the license is already activated and compatible.
     */
    public function isActivated(): bool
    {
        $licenseKey = $this->repository->getLicenseKey($this->packageName);
        $compatibility = $this->repository->getLicenseCompatibility($this->packageName);

        return $this->validator->isValid($licenseKey)
            && $this->validator->isCompatible($compatibility);
    }

    /**
     * Makes an API request.
     */
    protected function request(string $path, array $options = []): array
    {
        return $this->httpClient->request(static::API_URL . '/' . $path, $options);
    }
}
