<?php

declare(strict_types = 1);

namespace KirbyTools\Licensing;

use Kirby\Exception\LogicException;
use Throwable;

/**
 * Carries the coded outcome of a failed activation. The message is for logs
 * only; `LicensePanel::activationFailure()` translates the outcome.
 *
 * @link      https://kirby.tools
 * @copyright Johann Schopplich
 * @license   AGPL-3.0
 */
final class ActivationException extends LogicException
{
    public function __construct(
        public readonly ActivationOutcome $outcome,
        array $details = [],
        Throwable|null $previous = null
    ) {
        parent::__construct(
            message: 'License activation failed: ' . $outcome->value,
            details: $details,
            previous: $previous
        );
    }
}
