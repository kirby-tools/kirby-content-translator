<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing;

/**
 * License status enum for Kirby Tools plugins.
 *
 * @link      https://kirby.tools
 * @copyright Johann Schopplich
 * @license   AGPL-3.0
 */
enum LicenseStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Invalid = 'invalid';
    case Incompatible = 'incompatible';
    case Upgradeable = 'upgradeable';
}
