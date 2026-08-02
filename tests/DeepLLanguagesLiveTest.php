<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\DeepLLanguages;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pins the transcribed language constants to the live DeepL languages API.
 *
 * Excluded from the default run, so it needs asking for:
 *
 *     DEEPL_API_KEY=… vendor/bin/phpunit --group network
 */
#[Group('network')]
final class DeepLLanguagesLiveTest extends TestCase
{
    #[Test]
    public function constants_match_the_languages_api(): void
    {
        ['source' => $sourceCodes, 'target' => $targetCodes] = $this->fetchSupportedCodes();

        sort($sourceCodes);
        sort($targetCodes);

        $supportedSourceCodes = DeepLLanguages::SUPPORTED_SOURCE_CODES;
        $supportedTargetCodes = DeepLLanguages::SUPPORTED_TARGET_CODES;
        sort($supportedSourceCodes);
        sort($supportedTargetCodes);

        $this->assertSame($sourceCodes, $supportedSourceCodes);
        $this->assertSame($targetCodes, $supportedTargetCodes);
    }
    /** @return array{source: list<string>, target: list<string>} */
    private function fetchSupportedCodes(): array
    {
        $apiKey = getenv('DEEPL_API_KEY') ?: null;

        if ($apiKey === null) {
            $this->markTestSkipped('Set `DEEPL_API_KEY` to check the constants against the live API.');
        }

        $host = str_ends_with($apiKey, ':fx') ? 'https://api-free.deepl.com' : 'https://api.deepl.com';

        // `include=beta` is absent for the reason `DeepLLanguages` documents
        $response = file_get_contents(
            $host . '/v3/languages?resource=translate_text',
            context: stream_context_create([
                'http' => [
                    'header' => 'Authorization: DeepL-Auth-Key ' . $apiKey,
                    'ignore_errors' => true,
                ],
            ])
        );

        $this->assertIsString($response, 'The DeepL languages API could not be reached.');

        $languages = json_decode($response, associative: true);

        $this->assertIsArray($languages, 'Unexpected response from the DeepL languages API: ' . $response);

        $codesFor = static fn (string $flag): array => array_values(array_map(
            static fn (array $language): string => strtoupper($language['lang']),
            array_filter($languages, static fn (array $language): bool => $language[$flag] ?? false)
        ));

        return [
            'source' => $codesFor('usable_as_source'),
            'target' => $codesFor('usable_as_target'),
        ];
    }
}
