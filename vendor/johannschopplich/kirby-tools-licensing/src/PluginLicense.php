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
        $status = $this->toKirbyStatus($licenses->getStatusEnum());

        parent::__construct(
            plugin: $plugin,
            name: static::LICENSE_NAME,
            link: static::LICENSE_URL,
            status: $status
        );
    }

    protected function toKirbyStatus(LicenseStatus $customStatus): KirbyLicenseStatus
    {
        $dialogPrefix = LicenseUtils::toPackageSlug($this->packageName);

        return match ($customStatus) {
            LicenseStatus::Active => new KirbyLicenseStatus(
                value: LicenseStatus::Active->value,
                label: I18n::translate('kirby-tools.license.status.active'),
                icon: 'check',
                theme: 'positive',
                dialog: "{$dialogPrefix}/license"
            ),
            LicenseStatus::Inactive => new KirbyLicenseStatus(
                value: 'missing',
                label: I18n::translate('kirby-tools.license.status.inactive'),
                icon: 'key',
                theme: 'love',
                dialog: "{$dialogPrefix}/activate"
            ),
            LicenseStatus::Invalid => new KirbyLicenseStatus(
                value: LicenseStatus::Invalid->value,
                label: I18n::translate('kirby-tools.license.status.invalid'),
                icon: 'alert',
                theme: 'negative',
                dialog: "{$dialogPrefix}/activate"
            ),
            LicenseStatus::Incompatible => new KirbyLicenseStatus(
                value: LicenseStatus::Incompatible->value,
                label: I18n::translate('kirby-tools.license.status.incompatible'),
                icon: 'alert',
                theme: 'negative',
                dialog: "{$dialogPrefix}/license"
            ),
            LicenseStatus::Upgradeable => new KirbyLicenseStatus(
                value: LicenseStatus::Upgradeable->value,
                label: I18n::translate('kirby-tools.license.status.upgradeable'),
                icon: 'refresh',
                theme: 'notice',
                dialog: "{$dialogPrefix}/license"
            )
        };
    }
}
