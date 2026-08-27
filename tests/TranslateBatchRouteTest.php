<?php

declare(strict_types = 1);

use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;

/**
 * Pins the wire shape the Panel's `DeepLStrategy` reads. Both tiers otherwise
 * mock this payload, so a renamed key would leave every suite green. The key
 * names are shared with `contract.test.ts`; the assertion lives here because it
 * needs the route booted.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TranslateBatchRouteTest extends ApiRouteTestCase
{
    use ContractFixture;

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
            ['Hello', 'World'],
            fn (string $text): string => $text === 'Hello' ? ' ' : $text . ' (de)'
        );

        // The exact payload also pins that the placeholder keys stay out of a
        // rejection that has no indexes to report.
        $this->assertSame(
            [['index' => 0, 'reason' => 'empty translation']],
            $response['rejections']
        );
    }

    #[Test]
    public function sends_the_placeholder_indexes_of_a_placeholder_mismatch(): void
    {
        $response = $this->callTranslateBatchRoute(
            ['Read <c0/> now'],
            fn (string $text): string => 'Lies jetzt'
        );

        $this->assertSame(
            [['index' => 0, 'reason' => 'placeholder mismatch', 'expected' => [0], 'actual' => []]],
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
    public function sends_only_texts_and_rejections(): void
    {
        $shape = self::contract()['batchRouteResponse'];

        $response = $this->callTranslateBatchRoute(
            ['Read <c0/> now'],
            fn (string $text): string => 'Lies jetzt'
        );

        $this->assertSame($shape['keys'], array_keys($response));
        $this->assertSame(
            [...$shape['rejectionKeys'], ...$shape['optionalRejectionKeys']],
            array_keys($response['rejections'][0])
        );
    }
}
