<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing;

use JohannSchopplich\Licensing\Http\HttpClientInterface;

/**
 * Facade for managing licenses for Kirby Tools plugins.
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
    protected LicenseRepository $repository;
    protected LicenseValidator $validator;
    protected LicenseActivator $activator;

    public function __construct(
        protected string $packageName,
        HttpClientInterface|null $httpClient = null
    ) {
        $this->repository = new LicenseRepository();
        $this->validator = new LicenseValidator($packageName);
        $this->activator = new LicenseActivator(
            $packageName,
            $this->repository,
            $this->validator,
            $httpClient
        );
    }

    public static function read(string $packageName, array $options = []): static
    {
        $instance = new static(
            packageName: $packageName,
            httpClient: $options['httpClient'] ?? null
        );
        $instance->activator->refresh();

        return $instance;
    }

    /**
     * @return string One of: `active`, `inactive`, `invalid`, `incompatible`, `upgradeable`
     */
    public function getStatus(): string
    {
        return $this->getStatusEnum()->value;
    }

    public function getStatusEnum(): LicenseStatus
    {
        $licenseKey = $this->repository->getLicenseKey($this->packageName);

        if ($licenseKey === null) {
            return LicenseStatus::Inactive;
        }

        if (!$this->validator->isValid($licenseKey)) {
            return LicenseStatus::Invalid;
        }

        $compatibility = $this->repository->getLicenseCompatibility($this->packageName);

        if ($this->validator->isCompatible($compatibility)) {
            return LicenseStatus::Active;
        }

        if ($this->validator->isUpgradeable($compatibility)) {
            return LicenseStatus::Upgradeable;
        }

        return LicenseStatus::Incompatible;
    }

    public function getLicense(): array|null
    {
        $licenseKey = $this->repository->getLicenseKey($this->packageName);

        if ($licenseKey === null || !$this->validator->isValid($licenseKey)) {
            return null;
        }

        return [
            'key' => $licenseKey,
            'generation' => $this->validator->getLicenseGeneration($licenseKey),
            'compatibility' => $this->repository->getLicenseCompatibility($this->packageName)
        ];
    }

    public function isActivated(): bool
    {
        return $this->activator->isActivated();
    }

    public function activateFromRequest(): array
    {
        return $this->activator->activateFromRequest();
    }
}
