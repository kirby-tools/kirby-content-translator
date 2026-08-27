<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\DeepL;
use JohannSchopplich\ContentTranslator\Translation\TranslationLanguage;
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
        array $targetLanguageOverrides = [],
    ): App {
        $pluginOptions = [];
        if ($apiKey !== null) {
            $pluginOptions['DeepL.apiKey'] = $apiKey;
        }
        if ($requestOptions !== []) {
            $pluginOptions['DeepL.requestOptions'] = $requestOptions;
        }
        if ($targetLanguageOverrides !== []) {
            $pluginOptions['DeepL.targetLanguageOverrides'] = $targetLanguageOverrides;
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
    public function throws_when_constructor_lacks_api_key(): void
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
        $this->expectExceptionMessage('Cannot resolve a DeepL target language');

        // Mocked so that a resolver that stops throwing fails the assertion
        // instead of reaching the live DeepL API.
        $this->createMockDeepL()->translateMany(['Hello'], 'invalid');
    }

    #[Test]
    public function translate_many_sends_the_resolved_target_language(): void
    {
        $this->appWithDeepLConfig(
            languages: [
                ['code' => 'en', 'name' => 'English', 'default' => true, 'locale' => 'en_GB'],
                ['code' => 'zh-tw', 'name' => '繁體中文'],
                ['code' => 'cn', 'name' => '简体中文'],
            ],
            targetLanguageOverrides: ['cn' => 'ZH-HANS'],
        );

        $requests = [];
        $deepL = $this->createMockDeepL($requests);

        foreach (['en', 'zh-tw', 'cn'] as $code) {
            $deepL->translateMany(['Hello'], $code);
        }

        $this->assertSame(
            ['EN-GB', 'ZH-HANT', 'ZH-HANS'],
            array_column($requests, 'targetLanguage'),
        );
    }

    #[Test]
    public function request_options_cannot_override_the_resolved_payload(): void
    {
        $this->appWithDeepLConfig(requestOptions: [
            'target_lang' => 'FR',
            'source_lang' => 'FR',
            'text' => ['Injected'],
        ]);

        $requests = [];
        $deepL = $this->createMockDeepL($requests);
        $deepL->translateMany(['Hello'], 'de', 'en');

        $this->assertSame('DE-DE', $requests[0]['targetLanguage']);
        $this->assertSame('EN', $requests[0]['sourceLanguage']);
        $this->assertSame(['Hello'], $requests[0]['texts']);
    }

    #[Test]
    public function request_options_cannot_stand_in_for_an_unresolved_source_language(): void
    {
        $this->appWithDeepLConfig(
            languages: [
                ['code' => 'en', 'name' => 'English', 'default' => true, 'locale' => 'en_US'],
                ['code' => 'de', 'name' => 'Deutsch', 'locale' => 'de_DE'],
                ['code' => 'cn', 'name' => '简体中文'],
            ],
            requestOptions: ['source_lang' => 'FR'],
        );

        $requests = [];
        $deepL = $this->createMockDeepL($requests);
        $deepL->translateMany(['你好'], 'de', 'cn');

        // Letting `FR` stand would hand DeepL Chinese text labeled as French.
        $this->assertNull($requests[0]['sourceLanguage']);
    }

    #[Test]
    public function a_passed_language_carries_its_own_locale(): void
    {
        // `de-ch` is deliberately absent from the registry: the locale has to
        // come from the passed language, not from a second lookup.
        $this->appWithDeepLConfig(languages: [
            ['code' => 'en', 'name' => 'English', 'default' => true, 'locale' => 'en_US'],
        ]);

        $requests = [];
        $deepL = $this->createMockDeepL($requests);
        $deepL->translateMany(
            ['Hello'],
            new TranslationLanguage(code: 'de-ch', name: 'Schweizerdeutsch', locale: 'de_DE.UTF-8'),
            new TranslationLanguage(code: 'en', name: 'English', locale: 'en_GB.UTF-8'),
        );

        $this->assertSame('DE-CH', $requests[0]['targetLanguage']);
        $this->assertSame('EN', $requests[0]['sourceLanguage']);
    }

    #[Test]
    public function request_options_cannot_smuggle_a_text_alongside_the_texts(): void
    {
        $this->appWithDeepLConfig(requestOptions: ['text' => ['a' => 'INJECTED']]);

        $requests = [];
        $deepL = $this->createMockDeepL($requests);
        $deepL->translateMany(['Hello'], 'de', 'en');

        $this->assertSame(['Hello'], $requests[0]['texts']);
    }

    #[Test]
    public function translate_many_normalizes_source_language(): void
    {
        $this->appWithDeepLConfig();

        $requests = [];
        $deepL = $this->createMockDeepL($requests);

        $deepL->translateMany(['Hello'], 'de', 'en');
        $this->assertSame('EN', $requests[0]['sourceLanguage']);

        // Degrades to auto-detect if `SUPPORTED_SOURCE_CODES` ever loses a language.
        $deepL->translateMany(['Hello'], 'de', 'sw');
        $this->assertSame('SW', $requests[1]['sourceLanguage']);

        $deepL->translateMany(['Hello'], 'de', 'invalid');
        $this->assertNull($requests[2]['sourceLanguage']);
    }

    #[Test]
    public function translate_many_omits_tag_handling_for_text_without_markup(): void
    {
        $this->appWithDeepLConfig();

        $requests = [];
        $deepL = $this->createMockDeepL($requests);

        $deepL->translateMany(["Don't miss it, 5 < 10", '<?php echo $page->title() ?>'], 'de');

        $this->assertCount(1, $requests);
        $this->assertArrayNotHasKey('tag_handling', $requests[0]['requestOptions']);
        $this->assertSame('1', $requests[0]['requestOptions']['split_sentences']);
    }

    /** @return array<string, array{0: string}> */
    public static function markupTexts(): array
    {
        return [
            'html tag' => ['<p>Hello <strong>world</strong></p>'],
            'self-closing tag' => ['Line one<br />Line two'],
            'html comment' => ['Teaser<!-- more -->Body'],
            'kirbytag placeholder inside html' => ['<p>Read the <c0/> for details</p>'],
        ];
    }

    #[Test]
    public function translate_many_omits_tag_handling_for_a_text_whose_only_markup_is_a_placeholder(): void
    {
        $this->appWithDeepLConfig();

        $requests = [];
        $deepL = $this->createMockDeepL($requests);

        $deepL->translateMany(['Read the <c0/> for details'], 'de');

        $this->assertArrayNotHasKey('tag_handling', $requests[0]['requestOptions']);
    }

    #[Test]
    #[DataProvider('markupTexts')]
    public function translate_many_keeps_tag_handling_for_text_with_markup(string $text): void
    {
        $this->appWithDeepLConfig();

        $requests = [];
        $deepL = $this->createMockDeepL($requests);

        $deepL->translateMany([$text], 'de');

        $this->assertSame('html', $requests[0]['requestOptions']['tag_handling']);
    }

    #[Test]
    public function translate_many_sends_markup_and_plain_text_as_separate_requests(): void
    {
        $this->appWithDeepLConfig();

        $requests = [];
        $deepL = $this->createMockDeepL($requests);

        $results = $deepL->translateMany(["Don't", '<p>Markup</p>', 'Plain'], 'de');

        $this->assertCount(2, $requests);
        $this->assertSame(['<p>Markup</p>'], $requests[0]['texts']);
        $this->assertSame('html', $requests[0]['requestOptions']['tag_handling']);
        $this->assertSame(["Don't", 'Plain'], $requests[1]['texts']);
        $this->assertArrayNotHasKey('tag_handling', $requests[1]['requestOptions']);

        // Translations return under the indexes of their sources, not in the
        // order the groups were requested.
        $this->assertSame(
            ["[translated]Don't", '[translated]<p>Markup</p>', '[translated]Plain'],
            $results
        );
    }

    #[Test]
    public function translate_many_sends_a_mixed_batch_in_one_request_when_tag_handling_is_configured(): void
    {
        $this->appWithDeepLConfig(requestOptions: ['tag_handling' => 'html']);

        $requests = [];
        $deepL = $this->createMockDeepL($requests);

        $results = $deepL->translateMany(['Plain', '<p>Markup</p>'], 'de');

        $this->assertCount(1, $requests);
        $this->assertSame('html', $requests[0]['requestOptions']['tag_handling']);
        $this->assertSame(['[translated]Plain', '[translated]<p>Markup</p>'], $results);
    }

    /** @return array<string, array{0: string, 1: mixed}> */
    public static function tagHandlingDependentOptions(): array
    {
        return [
            'ignore tags'          => ['ignore_tags', 'x'],
            'splitting tags'       => ['splitting_tags', 'p'],
            'non-splitting tags'   => ['non_splitting_tags', 'br'],
            'outline detection'    => ['outline_detection', false],
            'tag handling version' => ['tag_handling_version', 'v2'],
        ];
    }

    #[Test]
    #[DataProvider('tagHandlingDependentOptions')]
    public function translate_many_strips_tag_handling_dependent_options_from_a_plain_request(string $option, mixed $value): void
    {
        $this->appWithDeepLConfig(requestOptions: [$option => $value]);

        $requests = [];
        $deepL = $this->createMockDeepL($requests);

        $deepL->translateMany(['Plain', '<p>Markup</p>'], 'de');

        $this->assertCount(2, $requests);
        $this->assertSame('html', $requests[0]['requestOptions']['tag_handling']);
        $this->assertSame($value, $requests[0]['requestOptions'][$option]);
        // DeepL rejects several of these when no `tag_handling` accompanies them.
        $this->assertArrayNotHasKey('tag_handling', $requests[1]['requestOptions']);
        $this->assertArrayNotHasKey($option, $requests[1]['requestOptions']);
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
    public function translate_many_chunks_when_text_count_exceeds_per_request_limit(): void
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

    #[Test]
    public function translate_many_partitions_by_markup_before_chunking(): void
    {
        $this->appWithDeepLConfig();

        $texts = [];
        for ($index = 0; $index < 120; $index++) {
            $texts[] = $index % 2 === 0 ? "<p>Markup {$index}</p>" : "Plain {$index}";
        }

        $requests = [];
        $deepL = $this->createMockDeepL($requests);

        $results = $deepL->translateMany($texts, 'de');

        // Each group fills whole requests before the next one starts, so the
        // split costs one extra request in total rather than one per chunk.
        $this->assertSame([50, 10, 50, 10], array_map(
            static fn (array $request): int => count($request['texts']),
            $requests
        ));
        $this->assertSame(['html', 'html', null, null], array_map(
            static fn (array $request): string|null => $request['requestOptions']['tag_handling'] ?? null,
            $requests
        ));

        $this->assertSame(
            array_map(static fn (string $text): string => "[translated]{$text}", $texts),
            $results
        );
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function incompleteResponseBodies(): array
    {
        return [
            'short translations'   => [['translations' => [['text' => 'Hallo']]]],
            'missing translations' => [['message' => 'Something went wrong']],
        ];
    }

    #[Test]
    #[DataProvider('incompleteResponseBodies')]
    public function translate_many_throws_when_the_response_is_incomplete(array $body): void
    {
        $this->appWithDeepLConfig();

        $deepL = new DeepL(
            remote: static fn (): object => new class ($body) {
                public function __construct(private array $body)
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
                    return $this->body;
                }
            }
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('translations for 3 texts');

        $deepL->translateMany(['Hello', 'World', 'Foo'], 'de');
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
    public function retries_on_retriable_status_then_succeeds(int $statusCode): void
    {
        $this->appWithDeepLConfig();

        $callCount = 0;
        $deepL = $this->createMockDeepLWithStatuses([$statusCode, 200], $callCount);

        $result = $deepL->translateMany(['Hello'], 'de');

        $this->assertSame(['translated'], $result);
        $this->assertSame(2, $callCount);
    }

    #[Test]
    public function throws_with_attempt_count_after_exhausting_max_retries(): void
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
    public function throws_naming_the_failure_for_each_terminal_status(int $statusCode, string $exceptionClass, string $messageFragment): void
    {
        $this->appWithDeepLConfig();

        $callCount = 0;
        $deepL = $this->createMockDeepLWithStatuses([$statusCode], $callCount);

        $this->expectException($exceptionClass);
        $this->expectExceptionMessage($messageFragment);

        $deepL->translateMany(['Hello'], 'de');
    }
}
