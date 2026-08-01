<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\DeepLLanguages;
use Kirby\Exception\LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DeepLLanguagesTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string|null, 2: string}> */
    public static function targetLanguageResolutions(): array
    {
        return [
            'region-specific target' => ['en', 'en_GB', 'EN-GB'],
            'unsupported region falls back to base' => ['it', 'it_IT', 'IT'],
            'charset suffix is ignored' => ['pt', 'pt_BR.UTF-8', 'PT-BR'],
            'numeric region subtag' => ['es-419', null, 'ES-419'],
            'extlang outranks its macrolanguage' => ['zh-yue', null, 'YUE'],
            'traditional region' => ['zh', 'zh_TW', 'ZH-HANT'],
            'traditional region (Hong Kong)' => ['zh', 'zh_HK', 'ZH-HANT'],
            'traditional region (Macau)' => ['zh', 'zh_MO', 'ZH-HANT'],
            'simplified region' => ['zh', 'zh_CN', 'ZH-HANS'],
            'explicit script outranks region' => ['zh', 'zh_Hant_TW', 'ZH-HANT'],
            'script without region' => ['zh', 'zh_Hans', 'ZH-HANS'],
            'unqualified Chinese stays generic' => ['zh', 'zh', 'ZH'],
            'hyphenated code without locale' => ['zh-tw', null, 'ZH-HANT'],
            'language-less system locale falls back to the code' => ['de', 'C', 'DE'],
            'unqualified English stays generic' => ['en', 'en', 'EN'],
            'language added in the 2026 expansion' => ['sw', 'sw_KE', 'SW'],
            'Swiss German variant' => ['de', 'de_CH', 'DE-CH'],
            'German variant' => ['de', 'de_DE', 'DE-DE'],
            'Canadian French variant' => ['fr', 'fr_CA', 'FR-CA'],
        ];
    }

    #[Test]
    #[DataProvider('targetLanguageResolutions')]
    public function resolves_target_language(string $code, string|null $locale, string $expected): void
    {
        $this->assertSame($expected, DeepLLanguages::resolveTarget($code, $locale));
    }

    #[Test]
    public function source_codes_are_the_target_codes_minus_the_target_only_variants(): void
    {
        // Both lists are transcribed by hand from the languages API. Pinning them
        // to each other is what keeps a target-only code out of `source_lang`,
        // which DeepL rejects outright.
        $this->assertSame(
            DeepLLanguages::SUPPORTED_SOURCE_CODES,
            array_values(array_filter(
                DeepLLanguages::SUPPORTED_TARGET_CODES,
                static fn (string $code): bool => !str_contains($code, '-')
            )),
        );
    }

    #[Test]
    public function throws_naming_the_locale_and_the_override_option(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/language "cn" \(locale "cn_CN"\).+DeepL\.targetLanguageOverrides/');

        DeepLLanguages::resolveTarget('cn', 'cn_CN');
    }

    #[Test]
    public function override_outranks_locale_and_skips_the_supported_codes(): void
    {
        // Deliberately not a code DeepL ships, since the option exists to carry
        // a language the constant does not know yet
        $this->assertSame(
            'ZH-FUTURE',
            DeepLLanguages::resolveTarget('zh', 'zh_CN', ['zh' => 'ZH-FUTURE']),
        );
    }
}
