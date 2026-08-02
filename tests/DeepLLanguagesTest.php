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
            'language outside the common European set' => ['sw', 'sw_KE', 'SW'],
            'Swiss German variant' => ['de', 'de_CH', 'DE-CH'],
            'German variant' => ['de', 'de_DE', 'DE-DE'],
            'Canadian French variant' => ['fr', 'fr_CA', 'FR-CA'],
            'Latin American region' => ['es', 'es_MX', 'ES-419'],
            'European Spanish region' => ['es', 'es_ES', 'ES'],
            'Latin American region without locale' => ['es-mx', null, 'ES-419'],
            'unqualified Spanish stays generic' => ['es', 'es', 'ES'],
            'alias for a code DeepL spells differently' => ['gr', null, 'EL'],
            'alias resolves as a source too' => ['jp', null, 'JA'],
            // A server without `de_CH` installed carries `de_DE` for date
            // formatting; that must not redirect the translation
            'specific code outranks a conflicting locale' => ['de-ch', 'de_DE.UTF-8', 'DE-CH'],
            'specific code outranks a conflicting locale (English)' => ['en-gb', 'en_US.UTF-8', 'EN-GB'],
            'bare code still defers to the locale' => ['de', 'de_CH.UTF-8', 'DE-CH'],
        ];
    }

    #[Test]
    #[DataProvider('targetLanguageResolutions')]
    public function resolves_target_language(string $code, string|null $locale, string $expected): void
    {
        $this->assertSame($expected, DeepLLanguages::resolveTarget($code, $locale));
    }

    /** @return array<string, array{0: string, 1: string|null, 2: string|null}> */
    public static function sourceLanguageResolutions(): array
    {
        return [
            'regional variant narrows to its base code' => ['zh-tw', 'zh_TW', 'ZH'],
            'Traditional Chinese locale narrows to Chinese' => ['zh', 'zh_TW', 'ZH'],
            'Swiss German narrows to German' => ['de-ch', 'de_CH.UTF-8', 'DE'],
            'British English narrows to English' => ['en', 'en_GB.UTF-8', 'EN'],
            'Latin American Spanish narrows to Spanish' => ['es', 'es_MX', 'ES'],
            'Brazilian Portuguese narrows to Portuguese' => ['pt', 'pt_BR', 'PT'],
            'extlang is a source of its own' => ['zh-yue', null, 'YUE'],
            'plain code passes through' => ['de', 'de_DE.UTF-8', 'DE'],
            'unresolvable code falls back to auto-detection' => ['cn', null, null],
        ];
    }

    #[Test]
    #[DataProvider('sourceLanguageResolutions')]
    public function resolves_source_language(string $code, string|null $locale, string|null $expected): void
    {
        $this->assertSame($expected, DeepLLanguages::resolveSource($code, $locale));
    }

    #[Test]
    public function no_source_code_is_a_regional_variant(): void
    {
        // `resolveSource` returns a listed base code verbatim, so a variant in
        // the constant would reach `source_lang` after all
        $this->assertSame([], array_values(array_filter(
            DeepLLanguages::SUPPORTED_SOURCE_CODES,
            static fn (string $code): bool => str_contains($code, '-')
        )));
    }

    #[Test]
    public function every_source_code_is_also_a_target_code(): void
    {
        // `resolveSource` narrows a resolved target to its base code, so a
        // source outside the target list would be unreachable
        $this->assertSame([], array_values(array_diff(
            DeepLLanguages::SUPPORTED_SOURCE_CODES,
            DeepLLanguages::SUPPORTED_TARGET_CODES
        )));
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

    #[Test]
    public function override_is_case_normalised(): void
    {
        $this->assertSame('ZH-HANS', DeepLLanguages::resolveTarget('cn', null, ['cn' => 'zh-hans']));
    }

    #[Test]
    public function override_reaches_the_source_language_too(): void
    {
        // Configuring a language once should tell the client what it is in both
        // directions, not just where it translates to
        $this->assertSame('ZH', DeepLLanguages::resolveSource('cn', null, ['cn' => 'ZH-HANS']));
    }
}
