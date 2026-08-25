<?php

declare(strict_types = 1);

use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;

/**
 * Pins the wire shape the Panel's `DeepLStrategy` reads. Both tiers otherwise
 * mock this payload, so a renamed key would leave every suite green. The key
 * names come from `contract.json`, shared with `contract.test.ts`.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TranslateBatchRouteTest extends ApiRouteTestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function contract(): array
    {
        return json_decode(file_get_contents(__DIR__ . '/fixtures/contract.json'), true);
    }

    /**
     * @param list<string> $texts
     */
    private function callTranslateBatchRoute(array $texts, Closure $strategy): mixed
    {
        $app = new App([
            'options' => [
                'johannschopplich.content-translator' => ['strategy' => $strategy]
            ],
            'languages' => [
                ['code' => 'en', 'default' => true, 'name' => 'English'],
                ['code' => 'de', 'name' => 'Deutsch']
            ],
            'request' => [
                'method' => 'POST',
                'body' => ['texts' => $texts, 'targetLanguage' => 'de']
            ]
        ]);

        return $this->callRoute($app, '__content-translator__/translate-batch');
    }

    #[Test]
    public function answers_for_every_text_in_input_order(): void
    {
        $response = $this->callTranslateBatchRoute(
            ['Hello', 'World'],
            fn (string $text): string => $text . ' (de)'
        );

        $this->assertSame(['Hello (de)', 'World (de)'], $response['texts']);
        $this->assertSame([], $response['rejections']);
    }

    #[Test]
    public function names_the_index_and_reason_of_a_rejected_text(): void
    {
        $response = $this->callTranslateBatchRoute(
            ['Read <c0/> now', 'World'],
            fn (string $text): string => str_contains($text, '<c0/>') ? 'Lies jetzt' : $text . ' (de)'
        );

        $this->assertSame(
            [['index' => 0, 'reason' => 'placeholder mismatch']],
            $response['rejections']
        );
    }

    #[Test]
    public function keeps_the_source_text_of_a_rejected_index(): void
    {
        $response = $this->callTranslateBatchRoute(
            ['Read <c0/> now'],
            fn (string $text): string => 'Lies jetzt'
        );

        $this->assertSame(['Read <c0/> now'], $response['texts']);
    }

    #[Test]
    public function sends_only_the_keys_the_contract_names(): void
    {
        $shape = self::contract()['batchRouteResponse'];

        $response = $this->callTranslateBatchRoute(
            ['Read <c0/> now'],
            fn (string $text): string => 'Lies jetzt'
        );

        $this->assertSame($shape['keys'], array_keys($response));
        $this->assertSame($shape['rejectionKeys'], array_keys($response['rejections'][0]));
    }
}
