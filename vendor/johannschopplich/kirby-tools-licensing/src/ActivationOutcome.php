<?php

declare(strict_types = 1);

namespace KirbyTools\Licensing;

/**
 * Why a license activation failed. The value doubles as the suffix of the
 * `kirby-tools.license.error.*` translation key.
 *
 * @link      https://kirby.tools
 * @copyright Johann Schopplich
 * @license   AGPL-3.0
 */
enum ActivationOutcome: string
{
    case InvalidCredentials = 'invalidCredentials';
    case AlreadyActivated = 'alreadyActivated';
    case WrongPlugin = 'invalid';
    case Upgradeable = 'upgradeable';
    case Incompatible = 'incompatible';
    case MissingParameters = 'missingParameters';
    case RequestFailed = 'requestFailed';
}
