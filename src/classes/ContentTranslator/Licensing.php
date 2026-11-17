<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator;

use KirbyTools\Licensing\Licenses;
use KirbyTools\Licensing\LicenseUtils;

final class Licensing
{
    public const PACKAGE_NAME = 'johannschopplich/kirby-content-translator';

    public static function status(): string
    {
        return Licenses::read(self::PACKAGE_NAME)->getStatus();
    }

    public static function dialog(): string
    {
        return LicenseUtils::toActivationDialog(self::PACKAGE_NAME);
    }
}
