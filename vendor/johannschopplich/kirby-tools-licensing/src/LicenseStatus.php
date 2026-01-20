<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing;

/**
 * License status constants for Kirby Tools plugins.
 *
 * @link      https://kirby.tools
 * @copyright Johann Schopplich
 * @license   AGPL-3.0
 */
final class LicenseStatus
{
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const INVALID = 'invalid';
    public const INCOMPATIBLE = 'incompatible';
    public const UPGRADEABLE = 'upgradeable';

    public const ALL = [
        self::ACTIVE,
        self::INACTIVE,
        self::INVALID,
        self::INCOMPATIBLE,
        self::UPGRADEABLE,
    ];
}
