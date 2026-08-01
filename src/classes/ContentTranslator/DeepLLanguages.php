<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator;

use Kirby\Exception\LogicException;

/**
 * Resolves a Kirby language to a DeepL language code.
 *
 * Both lists are transcribed from a live
 * `GET /v3/languages?resource=translate_text&include=beta`, filtered on
 * `usable_as_source` and `usable_as_target`. Refreshing them means repeating
 * that call — DeepL grew its list from 34 to 125 codes within a year, so a
 * stale constant is the likeliest way for this class to go wrong.
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
     * service — do not drop them to match the SDK.
     */
    public const SUPPORTED_TARGET_CODES = ['ACE', 'AF', 'AN', 'AR', 'AS', 'AY', 'AZ', 'BA', 'BE', 'BG', 'BHO', 'BN', 'BR', 'BS', 'CA', 'CEB', 'CKB', 'CS', 'CY', 'DA', 'DE', 'DE-CH', 'DE-DE', 'EL', 'EN', 'EN-GB', 'EN-US', 'EO', 'ES', 'ES-419', 'ET', 'EU', 'FA', 'FI', 'FR', 'FR-CA', 'FR-FR', 'GA', 'GL', 'GN', 'GOM', 'GU', 'HA', 'HE', 'HI', 'HR', 'HT', 'HU', 'HY', 'ID', 'IG', 'IS', 'IT', 'JA', 'JV', 'KA', 'KK', 'KMR', 'KO', 'KY', 'LA', 'LB', 'LMO', 'LN', 'LT', 'LV', 'MAI', 'MG', 'MI', 'MK', 'ML', 'MN', 'MR', 'MS', 'MT', 'MY', 'NB', 'NE', 'NL', 'OC', 'OM', 'PA', 'PAG', 'PAM', 'PL', 'PRS', 'PS', 'PT', 'PT-BR', 'PT-PT', 'QU', 'RO', 'RU', 'SA', 'SCN', 'SK', 'SL', 'SQ', 'SR', 'ST', 'SU', 'SV', 'SW', 'TA', 'TE', 'TG', 'TH', 'TK', 'TL', 'TN', 'TR', 'TS', 'TT', 'UK', 'UR', 'UZ', 'VI', 'WO', 'XH', 'YI', 'YUE', 'ZH', 'ZH-HANS', 'ZH-HANT', 'ZU'];

    /**
     * @param array<string,string> $targetLanguageOverrides Target codes by Kirby language code
     * @throws LogicException When neither the locale nor the code names a supported target.
     */
    public static function resolveTarget(string $languageCode, string|null $locale = null, array $targetLanguageOverrides = []): string
    {
        // An override is the user asserting they know better than
        // `SUPPORTED_TARGET_CODES`, the only escape hatch when DeepL ships a
        // code before this release does
        if (isset($targetLanguageOverrides[$languageCode])) {
            return $targetLanguageOverrides[$languageCode];
        }

        // Kirby neither validates nor normalises locales, so a language-less
        // system locale such as `C` leaves the code as the only usable signal
        $targetCode = ($locale === null ? null : self::fromLanguageTag($locale))
            ?? self::fromLanguageTag($languageCode);

        if ($targetCode === null) {
            throw new LogicException(
                'Cannot resolve a DeepL target language for Kirby language "' . $languageCode . '"' .
                ($locale === null ? '' : ' (locale "' . $locale . '")') .
                '. Set a supported code for it via the ' .
                '`johannschopplich.content-translator.DeepL.targetLanguageOverrides` option.'
            );
        }

        return $targetCode;
    }

    /**
     * Narrows a language tag to the most specific supported target code.
     */
    private static function fromLanguageTag(string $tag): string|null
    {
        $subtags = self::splitSubtags($tag);
        $baseCode = array_shift($subtags);

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

        // DeepL selects Chinese by script rather than region, so a locale naming
        // only a region has to be turned into one. These three are the
        // Traditional-script regions among the `zh` locales systems ship.
        if ($baseCode === 'ZH' && $subtags !== []) {
            return array_intersect(['TW', 'HK', 'MO'], $subtags) === [] ? 'ZH-HANS' : 'ZH-HANT';
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
