<?php

declare(strict_types = 1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;

/**
 * Pins the wire shape the Panel's `DeepLStrategy` reads. Both tiers otherwise
 * mock this payload, so a renamed key would leave every suite green. The worked
 * example is shared with `contract.test.ts`; the assertion lives here because
 * it needs the route booted.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TranslateUnitsRouteTest extends ApiRouteTestCase
{
    use ContractFixture;

    private const APP_PROPS = [
        'languages' => [
            ['code' => 'en', 'default' => true, 'name' => 'English'],
            ['code' => 'de', 'name' => 'Deutsch']
        ]
    ];

    /**
     * @param list<string> $texts
     */
    private function callTranslateUnitsRoute(array $texts, Closure $strategy): mixed
    {
        $app = self::bootApp([
            ...self::APP_PROPS,
            'options' => [
                'johannschopplich.content-translator' => ['strategy' => $strategy]
            ],
            'request' => [
                'method' => 'POST',
                'body' => ['texts' => $texts, 'targetLanguage' => 'de']
            ]
        ]);

        return $this->callRoute($app, '__content-translator__/translate-units', 'POST');
    }

    #[Test]
    public function answers_for_every_text_in_input_order(): void
    {
        $response = $this->callTranslateUnitsRoute(
            ['Hello', 'World'],
            fn (string $text): string => $text . ' (de)'
        );

        $this->assertSame(['Hello (de)', 'World (de)'], $response['texts']);
        $this->assertSame([], $response['rejections']);
    }

    #[Test]
    public function names_the_index_and_reason_of_a_rejected_text(): void
    {
        $response = $this->callTranslateUnitsRoute(
            ['Hello', 'World'],
            fn (string $text): string => $text === 'Hello' ? ' ' : $text . ' (de)'
        );

        $this->assertSame(
            [['index' => 0, 'reason' => 'empty translation']],
            $response['rejections']
        );
    }

    #[Test]
    public function sends_only_texts_and_rejections(): void
    {
        ['texts' => $texts, 'translations' => $translations, 'response' => $expectedResponse] = self::contract()['translateUnitsRouteCase'];

        $response = $this->callTranslateUnitsRoute(
            $texts,
            fn (string $text): string => array_combine($texts, $translations)[$text]
        );

        $this->assertSame($expectedResponse, $response);
    }
}
