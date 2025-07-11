<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing;

use Kirby\Plugin\License as KirbyLicense;
use Kirby\Plugin\LicenseStatus as KirbyLicenseStatus;
use Kirby\Plugin\Plugin;

/**
 * Integrates the custom license system for Kirby Tools plugins with Kirby's plugin license system.
 *
 * @link      https://kirby.tools
 * @copyright Johann Schopplich
 * @license   AGPL-3.0
 */
class PluginLicense extends KirbyLicense
{
    public const LICENSE_NAME = 'Kirby Tools License';
    public const LICENSE_URL = 'https://kirby.tools/license';

    public function __construct(
        Plugin $plugin,
        protected string $packageName
    ) {
        $licenses = Licenses::read($packageName);
        $status = $this->mapToKirbyStatus($licenses->getStatus());

        parent::__construct(
            plugin: $plugin,
            name: static::LICENSE_NAME,
            link: static::LICENSE_URL,
            status: $status
        );
    }

    protected function mapToKirbyStatus(string $customStatus): KirbyLicenseStatus
    {
        return match ($customStatus) {
            'active' => new KirbyLicenseStatus(
                value: 'active',
                label: 'Licensed',
                icon: 'check',
                theme: 'positive'
            ),
            'inactive' => new KirbyLicenseStatus(
                value: 'missing',
                label: 'Please buy a license',
                icon: 'key',
                theme: 'love'
            ),
            'invalid' => new KirbyLicenseStatus(
                value: 'invalid',
                label: 'Invalid license',
                icon: 'alert',
                theme: 'negative'
            ),
            'incompatible' => new KirbyLicenseStatus(
                value: 'incompatible',
                label: 'Incompatible license',
                icon: 'alert',
                theme: 'negative'
            ),
            'upgradeable' => new KirbyLicenseStatus(
                value: 'upgradeable',
                label: 'License upgrade available',
                icon: 'refresh',
                theme: 'notice'
            ),
            default => new KirbyLicenseStatus(
                value: 'unknown',
                label: 'Unknown license status',
                icon: 'question',
                theme: 'passive'
            )
        };
    }
}
