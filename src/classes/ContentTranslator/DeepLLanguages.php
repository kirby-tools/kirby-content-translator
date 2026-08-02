<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator;

use Kirby\Exception\LogicException;

/**
 * Resolves a Kirby language to a DeepL language code.
 *
 * Both lists are transcribed from a live
 * `GET /v3/languages?resource=translate_text`, filtered on `usable_as_source`
 * and `usable_as_target`. `include=beta` is deliberately absent: a beta
 * language may not be translatable yet through `/v2/translate`, which is the
 * endpoint these codes are sent to. `DeepLLanguagesLiveTest` pins the lists to
 * that call.
 *
 * @see https://developers.deepl.com/docs/languages/using-the-languages-api
 */
final class DeepLLanguages
{
    public const SUPPORTED_SOURCE_CODES = ['ACE', 'AF', 'AN', 'AR', 'AS', 'AY', 'AZ', 'BA', 'BE', 'BG', 'BHO', 'BN', 'BR', 'BS', 'CA', 'CEB', 'CKB', 'CS', 'CY', 'DA', 'DE', 'EL', 'EN', 'EO', 'ES', 'ET', 'EU', 'FA', 'FI', 'FR', 'GA', 'GL', 'GN', 'GOM', 'GU', 'HA', 'HE', 'HI', 'HR', 'HT', 'HU', 'HY', 'ID', 'IG', 'IS', 'IT', 'JA', 'JV', 'KA', 'KK', 'KMR', 'KO', 'KY', 'LA', 'LB', 'LMO', 'LN', 'LT', 'LV', 'MAI', 'MG', 'MI', 'MK', 'ML', 'MN', 'MR', 'MS', 'MT', 'MY', 'NB', 'NE', 'NL', 'OC', 'OM', 'PA', 'PAG', 'PAM', 'PL', 'PRS', 'PS', 'PT', 'QU', 'RO', 'RU', 'SA', 'SCN', 'SK', 'SL', 'SQ', 'SR', 'ST', 'SU', 'SV', 'SW', 'TA', 'TE', 'TG', 'TH', 'TK', 'TL', 'TN', 'TR', 'TS', 'TT', 'UK', 'UR', 'UZ', 'VI', 'WO', 'XH', 'YI', 'YUE', 'ZH', 'ZU'];

    /**
     * `EN` and `PT` are targets here even though DeepL's own client throws on
     * both, calling them deprecated. The languages API reports
     * `usable_as_target: true` for each, so the client is stricter than the
     * service – do not drop them to match the SDK.
     */
    public const SUPPORTED_TARGET_CODES = ['ACE', 'AF', 'AN', 'AR', 'AS', 'AY', 'AZ', 'BA', 'BE', 'BG', 'BHO', 'BN', 'BR', 'BS', 'CA', 'CEB', 'CKB', 'CS', 'CY', 'DA', 'DE', 'DE-CH', 'DE-DE', 'EL', 'EN', 'EN-GB', 'EN-US', 'EO', 'ES', 'ES-419', 'ET', 'EU', 'FA', 'FI', 'FR', 'FR-CA', 'FR-FR', 'GA', 'GL', 'GN', 'GOM', 'GU', 'HA', 'HE', 'HI', 'HR', 'HT', 'HU', 'HY', 'ID', 'IG', 'IS', 'IT', 'JA', 'JV', 'KA', 'KK', 'KMR', 'KO', 'KY', 'LA', 'LB', 'LMO', 'LN', 'LT', 'LV', 'MAI', 'MG', 'MI', 'MK', 'ML', 'MN', 'MR', 'MS', 'MT', 'MY', 'NB', 'NE', 'NL', 'OC', 'OM', 'PA', 'PAG', 'PAM', 'PL', 'PRS', 'PS', 'PT', 'PT-BR', 'PT-PT', 'QU', 'RO', 'RU', 'SA', 'SCN', 'SK', 'SL', 'SQ', 'SR', 'ST', 'SU', 'SV', 'SW', 'TA', 'TE', 'TG', 'TH', 'TK', 'TL', 'TN', 'TR', 'TS', 'TT', 'UK', 'UR', 'UZ', 'VI', 'WO', 'XH', 'YI', 'YUE', 'ZH', 'ZH-HANS', 'ZH-HANT', 'ZU'];

    /**
     * Kirby codes naming a language DeepL spells differently. Only codes with a
     * single reading belong here: `no` could be `NB` or `NN` and `cn` could be
     * either Chinese script, so guessing for those would be worse than throwing.
     */
    private const BASE_CODE_ALIASES = [
        'GR' => 'EL',
        'JP' => 'JA',
    ];

    /**
     * Languages DeepL splits along a line no locale draws – by script for
     * Chinese, by a UN M.49 region group for Spanish – so their region subtag
     * has to be mapped instead of appended. Each entry names the regions that
     * keep `code`; every other region takes `fallback`.
     */
    private const REGION_GROUPS = [
        // The Traditional-script regions among the `zh` locales systems ship
        'ZH' => ['regions' => ['TW', 'HK', 'MO'], 'code' => 'ZH-HANT', 'fallback' => 'ZH-HANS'],
        // `ES-419` covers all of Latin America, which no locale spells, so
        // European Spanish is the exception and everything else the rule
        'ES' => ['regions' => ['ES', 'GQ'], 'code' => 'ES', 'fallback' => 'ES-419'],
    ];

    /**
     * @param array<string,string> $targetLanguageOverrides Target codes by Kirby language code
     * @throws LogicException When neither the code nor the locale names a supported target.
     */
    public static function resolveTarget(string $languageCode, string|null $locale = null, array $targetLanguageOverrides = []): string
    {
        // An override is the user asserting they know better than
        // `SUPPORTED_TARGET_CODES`, the only escape hatch when DeepL ships a
        // code before this release does. Checking it against the constant would
        // defeat that, so only its case is normalised.
        if (isset($targetLanguageOverrides[$languageCode])) {
            return strtoupper($targetLanguageOverrides[$languageCode]);
        }

        $codeTarget = self::fromLanguageTag($languageCode);

        // The code identifies the language: it names the content file and the
        // Panel switch. A locale only formats dates and numbers, and servers
        // routinely carry a neighbouring one because the exact locale is not
        // installed – so it may sharpen a bare code, never overrule a specific
        // one. Without this, `de-ch` on a `de_DE.UTF-8` box translates to
        // Germany's German.
        if ($codeTarget !== null && str_contains($codeTarget, '-')) {
            return $codeTarget;
        }

        // Kirby neither validates nor normalises locales, so a language-less
        // system locale such as `C` leaves the code as the only usable signal
        $targetCode = ($locale === null ? null : self::fromLanguageTag($locale)) ?? $codeTarget;

        if ($targetCode === null) {
            throw new LogicException(
                'Cannot resolve a DeepL target language for Kirby language "' . $languageCode . '"' .
                ($locale === null ? '' : ' (locale "' . $locale . '")') .
                '. Set a `locale` for it in the Kirby language setup, or a supported code via the ' .
                '`johannschopplich.content-translator.DeepL.targetLanguageOverrides` option.'
            );
        }

        return $targetCode;
    }

    /**
     * Resolves a Kirby language to a DeepL source code, `null` for auto-detection.
     *
     * @param array<string,string> $targetLanguageOverrides Target codes by Kirby language code
     */
    public static function resolveSource(string $languageCode, string|null $locale = null, array $targetLanguageOverrides = []): string|null
    {
        try {
            $targetCode = self::resolveTarget($languageCode, $locale, $targetLanguageOverrides);
        } catch (LogicException) {
            // Auto-detection is a usable answer for a source; only the target
            // has to be right
            return null;
        }

        // DeepL answers every regional variant in `source_lang` with a 400, so
        // the resolved target is stripped back to the code it varies
        [$baseCode] = explode('-', $targetCode, 2);

        return in_array($baseCode, self::SUPPORTED_SOURCE_CODES, true) ? $baseCode : null;
    }

    /**
     * Narrows a language tag to the most specific supported target code.
     */
    private static function fromLanguageTag(string $tag): string|null
    {
        $subtags = self::splitSubtags($tag);
        $baseCode = array_shift($subtags);
        $baseCode = self::BASE_CODE_ALIASES[$baseCode] ?? $baseCode;

        foreach ($subtags as $subtag) {
            $regionSpecificCode = $baseCode . '-' . $subtag;

            if (in_array($regionSpecificCode, self::SUPPORTED_TARGET_CODES, true)) {
                return $regionSpecificCode;
            }

            // BCP 47 canonicalises an extlang away from its macrolanguage, so
            // `zh-yue` names Cantonese rather than a variant of Chinese. Only a
            // three-letter subtag can be one; regions are two letters or three digits.
            if (strlen($subtag) === 3 && ctype_alpha($subtag) && in_array($subtag, self::SUPPORTED_TARGET_CODES, true)) {
                return $subtag;
            }
        }

        $regionGroup = self::REGION_GROUPS[$baseCode] ?? null;

        if ($regionGroup !== null && $subtags !== []) {
            return array_intersect($regionGroup['regions'], $subtags) === []
                ? $regionGroup['fallback']
                : $regionGroup['code'];
        }

        return in_array($baseCode, self::SUPPORTED_TARGET_CODES, true) ? $baseCode : null;
    }

    /**
     * Splits a language tag into its uppercased subtags, tolerating both the
     * POSIX (`zh_Hant_TW.UTF-8`) and BCP 47 (`zh-Hant-TW`) spellings, since
     * Kirby validates neither and reports the bare code when no locale is set.
     *
     * @return list<string>
     */
    private static function splitSubtags(string $tag): array
    {
        // `.charset` and `@modifier` both terminate the subtag list, so folding one
        // into the other lets a single split drop whichever the locale carries
        [$languageTag] = explode('.', str_replace('@', '.', $tag), 2);

        return explode('-', strtoupper(strtr($languageTag, '_', '-')));
    }
}
