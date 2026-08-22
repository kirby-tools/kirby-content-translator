<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing;

use Kirby\Plugin\License as KirbyLicense;
use Kirby\Plugin\LicenseStatus as KirbyLicenseStatus;
use Kirby\Plugin\Plugin;

/**
 * @link      https://kirby.tools
 * @copyright Johann Schopplich
 * @license   AGPL-3.0
 */
final class PluginLicense extends KirbyLicense
{
    public const LICENSE_NAME = 'Kirby Tools Plugin License';
    public const LICENSE_URL = 'https://kirby.tools/license';

    public function __construct(
        Plugin $plugin,
        private readonly string $packageName
    ) {
        $licenses = Licenses::read($packageName);
        $status = $this->toKirbyStatus($licenses->getStatusEnum());

        parent::__construct(
            plugin: $plugin,
            name: self::LICENSE_NAME,
            link: self::LICENSE_URL,
            status: $status
        );
    }

    private function toKirbyStatus(LicenseStatus $customStatus): KirbyLicenseStatus
    {
        $dialogPrefix = LicenseUtils::toPackageSlug($this->packageName);
        $label = LicensePanel::statusLabel($customStatus);

        return match ($customStatus) {
            LicenseStatus::Active => new KirbyLicenseStatus(
                value: LicenseStatus::Active->value,
                label: $label,
                icon: 'check',
                theme: 'positive',
                dialog: "{$dialogPrefix}/license"
            ),
            LicenseStatus::Inactive => new KirbyLicenseStatus(
                value: 'missing',
                label: $label,
                icon: 'key',
                theme: 'love',
                dialog: "{$dialogPrefix}/activate"
            ),
            LicenseStatus::Invalid => new KirbyLicenseStatus(
                value: LicenseStatus::Invalid->value,
                label: $label,
                icon: 'alert',
                theme: 'negative',
                dialog: "{$dialogPrefix}/activate"
            ),
            LicenseStatus::Incompatible => new KirbyLicenseStatus(
                value: LicenseStatus::Incompatible->value,
                label: $label,
                icon: 'alert',
                theme: 'negative',
                dialog: "{$dialogPrefix}/license"
            ),
            LicenseStatus::Upgradeable => new KirbyLicenseStatus(
                value: LicenseStatus::Upgradeable->value,
                label: $label,
                icon: 'refresh',
                theme: 'notice',
                dialog: "{$dialogPrefix}/license"
            )
        };
    }
}
