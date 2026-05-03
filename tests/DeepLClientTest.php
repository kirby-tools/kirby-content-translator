<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\DeepL;
use Kirby\Cms\App;
use Kirby\Exception\AuthException;
use Kirby\Exception\LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DeepLClientTest extends TestCase
{
    protected function tearDown(): void
    {
        App::destroy();
    }

    private function appWithDeepLConfig(
        string|null $apiKey = 'test-key:fx',
        array|null $languages = null,
        array $requestOptions = [],
    ): App {
        $pluginOptions = [];
        if ($apiKey !== null) {
            $pluginOptions['DeepL.apiKey'] = $apiKey;
        }
        if (!empty($requestOptions)) {
            $pluginOptions['DeepL.requestOptions'] = $requestOptions;
        }

        return new App([
            'languages' => $languages ?? [
                ['code' => 'en', 'name' => 'English', 'default' => true, 'locale' => 'en_US'],
                ['code' => 'de', 'name' => 'Deutsch', 'locale' => 'de_DE'],
            ],
            'options' => [
                'debug' => true,
                'johannschopplich.content-translator' => $pluginOptions,
            ],
        ]);
    }

    private function createMockDeepL(array &$capturedRequests = []): DeepL
    {
        return new DeepL(
            remote: function (string $url, array $options) use (&$capturedRequests): object {
                $body = json_decode($options['data'], associative: true);

                $capturedRequests[] = [
                    'url' => $url,
                    'texts' => $body['text'],
                    'targetLanguage' => $body['target_lang'],
                    'sourceLanguage' => $body['source_lang'] ?? null,
                    'requestOptions' => array_diff_key(
                        $body,
                        ['text' => true, 'target_lang' => true, 'source_lang' => true],
                    ),
                ];

                return new class ($body['text']) {
                    /** @param array<string> $texts */
                    public function __construct(private array $texts)
                    {
                    }
                    public function code(): int
                    {
                        return 200;
                    }
                    public function content(): string
                    {
                        return '';
                    }
                    public function json(): array
                    {
                        return [
                            'translations' => array_map(
                                fn (string $text) => ['text' => "[translated]$text"],
                                $this->texts
                            ),
                        ];
                    }
                };
            }
        );
    }

    /**
     * @param list<int> $statusSequence Status codes returned on consecutive calls
     */
    private function createMockDeepLWithStatuses(array $statusSequence, int &$callCount = 0): DeepL
    {
        return new DeepL(
            remote: function () use ($statusSequence, &$callCount): object {
                $statusCode = $statusSequence[$callCount] ?? 200;
                $callCount++;

                return new class ($statusCode) {
                    public function __construct(private int $statusCode)
                    {
                    }
                    public function code(): int
                    {
                        return $this->statusCode;
                    }
                    public function content(): string
                    {
                        return $this->statusCode === 200 ? '' : 'mock error body';
                    }
                    public function json(): array
                    {
                        return ['translations' => [['text' => 'translated']]];
                    }
                };
            },
            delay: static fn () => null,
        );
    }

    #[Test]
    public function constructor_throws_when_api_key_is_missing(): void
    {
        $this->appWithDeepLConfig(apiKey: null);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Missing DeepL API key');

        new DeepL();
    }

    #[Test]
    public function translate_many_returns_empty_for_empty_input(): void
    {
        $this->appWithDeepLConfig();

        $this->assertSame([], DeepL::instance()->translateMany([], 'de'));
    }

    #[Test]
    public function translate_many_throws_for_unsupported_target_language(): void
    {
        $this->appWithDeepLConfig();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('not supported by the DeepL API');

        DeepL::instance()->translateMany(['Hello'], 'invalid');
    }

    #[Test]
    public function translate_many_resolves_regional_target_language(): void
    {
        $this->appWithDeepLConfig(languages: [
            ['code' => 'en', 'name' => 'English', 'default' => true, 'locale' => 'en_GB'],
            ['code' => 'pt', 'name' => 'Portuguese', 'locale' => 'pt_BR'],
        ]);

        $requests = [];
        $deepL = $this->createMockDeepL($requests);

        $deepL->translateMany(['Hello'], 'en');
        $this->assertSame('EN-GB', $requests[0]['targetLanguage']);

        $deepL->translateMany(['Hello'], 'pt');
        $this->assertSame('PT-BR', $requests[1]['targetLanguage']);
    }

    #[Test]
    public function translate_many_normalizes_source_language(): void
    {
        $this->appWithDeepLConfig();

        $requests = [];
        $deepL = $this->createMockDeepL($requests);

        $deepL->translateMany(['Hello'], 'de', 'en');
        $this->assertSame('EN', $requests[0]['sourceLanguage']);

        $deepL->translateMany(['Hello'], 'de', 'invalid');
        $this->assertNull($requests[1]['sourceLanguage']);
    }

    #[Test]
    public function translate_many_forces_html_tag_handling_for_no_translate_spans(): void
    {
        $this->appWithDeepLConfig(requestOptions: ['tag_handling' => 'xml']);

        $requests = [];
        $deepL = $this->createMockDeepL($requests);

        $deepL->translateMany(['Normal text'], 'de');
        $this->assertSame('xml', $requests[0]['requestOptions']['tag_handling']);

        $deepL->translateMany(['Keep <span translate="no">Brand</span> unchanged'], 'de');
        $this->assertSame('html', $requests[1]['requestOptions']['tag_handling']);
    }

    #[Test]
    public function translate_many_batches_texts_in_a_single_request(): void
    {
        $this->appWithDeepLConfig();

        $requests = [];
        $deepL = $this->createMockDeepL($requests);

        $results = $deepL->translateMany(['Hello', 'World', 'Foo'], 'de');

        $this->assertCount(1, $requests);
        $this->assertSame(['Hello', 'World', 'Foo'], $requests[0]['texts']);
        $this->assertCount(3, $results);
    }

    #[Test]
    public function translate_many_chunks_at_50_texts(): void
    {
        $this->appWithDeepLConfig();

        $requests = [];
        $deepL = $this->createMockDeepL($requests);

        $results = $deepL->translateMany(array_fill(0, 60, 'Hello'), 'de');

        $this->assertCount(2, $requests);
        $this->assertCount(50, $requests[0]['texts']);
        $this->assertCount(10, $requests[1]['texts']);
        $this->assertCount(60, $results);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function accountKeyEndpoints(): array
    {
        return [
            'free account key uses api-free host' => ['test-key:fx', DeepL::API_URL_FREE],
            'paid account key uses api host'      => ['test-key', DeepL::API_URL_PRO],
        ];
    }

    #[Test]
    #[DataProvider('accountKeyEndpoints')]
    public function routes_api_key_to_correct_account_endpoint(string $apiKey, string $expectedHost): void
    {
        $this->appWithDeepLConfig(apiKey: $apiKey);

        $requests = [];
        $deepL = $this->createMockDeepL($requests);

        $deepL->translateMany(['Hello'], 'de');

        $this->assertStringStartsWith($expectedHost, $requests[0]['url']);
    }

    /** @return array<string, array{0: int}> */
    public static function retriableStatusCodes(): array
    {
        return [
            'rate limit (429)'      => [429],
            'server error (500)'    => [500],
            'service unavailable'   => [503],
            'gateway timeout'       => [504],
            'overloaded (529)'      => [529],
        ];
    }

    #[Test]
    #[DataProvider('retriableStatusCodes')]
    public function retries_on_retriable_status_until_success(int $statusCode): void
    {
        $this->appWithDeepLConfig();

        $callCount = 0;
        $deepL = $this->createMockDeepLWithStatuses([$statusCode, 200], $callCount);

        $result = $deepL->translateMany(['Hello'], 'de');

        $this->assertSame(['translated'], $result);
        $this->assertSame(2, $callCount);
    }

    #[Test]
    public function throws_with_attempt_count_after_max_retries(): void
    {
        $this->appWithDeepLConfig();

        $callCount = 0;
        $deepL = $this->createMockDeepLWithStatuses(array_fill(0, 6, 429), $callCount);

        try {
            $deepL->translateMany(['Hello'], 'de');
            $this->fail('Expected LogicException');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Maximum retry attempts reached', $e->getMessage());
            $this->assertStringContainsString('5 attempts', $e->getMessage());
            $this->assertStringContainsString('batch size: 1', $e->getMessage());
            $this->assertSame(6, $callCount);
        }
    }

    /** @return array<string, array{0: int, 1: class-string<\Throwable>, 2: string}> */
    public static function terminalStatusCodes(): array
    {
        return [
            'bad request (400)'      => [400, LogicException::class, 'Bad request'],
            'forbidden (403)'        => [403, AuthException::class, 'Authorization failed'],
            'not found (404)'        => [404, LogicException::class, 'endpoint not found'],
            'payload too large (413)' => [413, LogicException::class, 'size limit exceeded'],
            'quota exceeded (456)'   => [456, LogicException::class, 'quota exceeded'],
            'unmapped status (418)'  => [418, LogicException::class, 'request failed'],
        ];
    }

    #[Test]
    #[DataProvider('terminalStatusCodes')]
    public function maps_terminal_status_to_named_exception(int $statusCode, string $exceptionClass, string $messageFragment): void
    {
        $this->appWithDeepLConfig();

        $callCount = 0;
        $deepL = $this->createMockDeepLWithStatuses([$statusCode], $callCount);

        $this->expectException($exceptionClass);
        $this->expectExceptionMessage($messageFragment);

        $deepL->translateMany(['Hello'], 'de');
    }
}
