<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing;

use Kirby\Plugin\License as KirbyLicense;
use Kirby\Plugin\LicenseStatus as KirbyLicenseStatus;
use Kirby\Plugin\Plugin;
use Kirby\Toolkit\I18n;

/**
 * Integrates the custom license system for Kirby Tools plugins with Kirby's plugin license system.
 *
 * @link      https://kirby.tools
 * @copyright Johann Schopplich
 * @license   AGPL-3.0
 */
class PluginLicense extends KirbyLicense
{
    public const LICENSE_NAME = 'Kirby Tools Plugin License';
    public const LICENSE_URL = 'https://kirby.tools/license';

    public function __construct(
        Plugin $plugin,
        protected string $packageName
    ) {
        $licenses = Licenses::read($packageName);
        $status = $this->toKirbyStatus($licenses->getStatus());

        parent::__construct(
            plugin: $plugin,
            name: static::LICENSE_NAME,
            link: static::LICENSE_URL,
            status: $status
        );
    }

    protected function toKirbyStatus(string $customStatus): KirbyLicenseStatus
    {
        $dialogPrefix = PluginLicenseExtensions::toPackageSlug($this->packageName);

        return match ($customStatus) {
            LicenseStatus::ACTIVE => new KirbyLicenseStatus(
                value: LicenseStatus::ACTIVE,
                label: I18n::translate('kirby-tools.license.status.active'),
                icon: 'check',
                theme: 'positive',
                dialog: "{$dialogPrefix}/license"
            ),
            LicenseStatus::INACTIVE => new KirbyLicenseStatus(
                value: 'missing',
                label: I18n::translate('kirby-tools.license.status.inactive'),
                icon: 'key',
                theme: 'love',
                dialog: "{$dialogPrefix}/activate"
            ),
            LicenseStatus::INVALID => new KirbyLicenseStatus(
                value: LicenseStatus::INVALID,
                label: I18n::translate('kirby-tools.license.status.invalid'),
                icon: 'alert',
                theme: 'negative',
                dialog: "{$dialogPrefix}/activate"
            ),
            LicenseStatus::INCOMPATIBLE => new KirbyLicenseStatus(
                value: LicenseStatus::INCOMPATIBLE,
                label: I18n::translate('kirby-tools.license.status.incompatible'),
                icon: 'alert',
                theme: 'negative',
                dialog: "{$dialogPrefix}/license"
            ),
            LicenseStatus::UPGRADEABLE => new KirbyLicenseStatus(
                value: LicenseStatus::UPGRADEABLE,
                label: I18n::translate('kirby-tools.license.status.upgradeable'),
                icon: 'refresh',
                theme: 'notice',
                dialog: "{$dialogPrefix}/license"
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
