<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\DeepL;
use Kirby\Cms\App;
use Kirby\Exception\AuthException;
use Kirby\Exception\LogicException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DeepLTest extends TestCase
{
    protected function setUp(): void
    {
        new App([
            'languages' => [
                [
                    'code' => 'en',
                    'name' => 'English',
                    'default' => true,
                    'locale' => 'en_US'
                ],
                [
                    'code' => 'de',
                    'name' => 'Deutsch',
                    'locale' => 'de_DE'
                ]
            ],
            'options' => [
                'debug' => true,
                'johannschopplich.content-translator' => [
                    'DeepL.apiKey' => 'test-key:fx'
                ]
            ]
        ]);
    }

    protected function tearDown(): void
    {
        App::destroy();
    }

    /**
     * Creates a partial mock of DeepL that intercepts the HTTP boundary.
     * Records all calls to `request()` and returns fake translated responses.
     */
    private function createMockDeepL(array &$capturedRequests = []): DeepL
    {
        $mock = $this->getMockBuilder(DeepL::class)
            ->onlyMethods(['request'])
            ->getMock();

        $mock->expects($this->atLeastOnce())
            ->method('request')
            ->willReturnCallback(function (array $texts, string $targetLanguage, string|null $sourceLanguage, array $requestOptions) use (&$capturedRequests) {
                $capturedRequests[] = [
                    'texts' => $texts,
                    'targetLanguage' => $targetLanguage,
                    'sourceLanguage' => $sourceLanguage,
                    'requestOptions' => $requestOptions,
                ];

                return new class ($texts) {
                    public function __construct(private array $texts)
                    {
                    }
                    public function json(): array
                    {
                        return [
                            'translations' => array_map(
                                fn (string $text) => ['text' => "[translated]$text"],
                                $this->texts
                            )
                        ];
                    }
                };
            });

        return $mock;
    }

    // Constructor & singleton
    // -----------------------------------------------------------------------

    #[Test]
    public function constructor_throws_exception_without_api_key(): void
    {
        new App([
            'options' => [
                'johannschopplich.content-translator' => []
            ]
        ]);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Missing DeepL API key');

        new DeepL();
    }

    #[Test]
    public function instance_returns_singleton(): void
    {
        $instance1 = DeepL::instance();
        $instance2 = DeepL::instance();

        $this->assertSame($instance1, $instance2);
    }

    // `translateMany` – basic behavior
    // -----------------------------------------------------------------------

    #[Test]
    public function translate_many_with_empty_array(): void
    {
        $deepL = DeepL::instance();
        $result = $deepL->translateMany([], 'de');

        $this->assertSame([], $result);
    }

    #[Test]
    public function translate_many_throws_exception_for_unsupported_target_language(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('not supported by the DeepL API');

        $deepL = DeepL::instance();
        $deepL->translateMany(['Hello'], 'invalid');
    }

    #[Test]
    public function translate_delegates_to_translate_many(): void
    {
        $mockDeepL = $this->getMockBuilder(DeepL::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['translateMany'])
            ->getMock();

        $mockDeepL->expects($this->once())
            ->method('translateMany')
            ->with(['Hello World'], 'de', null)
            ->willReturn(['Hallo Welt']);

        $result = $mockDeepL->translate('Hello World', 'de');

        $this->assertSame('Hallo Welt', $result);
    }

    // `translateMany` – language resolution
    // -----------------------------------------------------------------------

    #[Test]
    public function translate_many_resolves_regional_target_language(): void
    {
        new App([
            'languages' => [
                ['code' => 'en', 'name' => 'English', 'default' => true, 'locale' => 'en_GB'],
                ['code' => 'pt', 'name' => 'Portuguese', 'locale' => 'pt_BR']
            ],
            'options' => [
                'johannschopplich.content-translator' => [
                    'DeepL.apiKey' => 'test-key:fx'
                ]
            ]
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
        $requests = [];
        $deepL = $this->createMockDeepL($requests);

        // Valid source language is uppercased
        $deepL->translateMany(['Hello'], 'de', 'en');
        $this->assertSame('EN', $requests[0]['sourceLanguage']);

        // Unsupported source language is silently dropped
        $deepL->translateMany(['Hello'], 'de', 'invalid');
        $this->assertNull($requests[1]['sourceLanguage']);
    }

    // `translateMany` – request options
    // -----------------------------------------------------------------------

    #[Test]
    public function translate_many_forces_html_tag_handling_for_no_translate_spans(): void
    {
        new App([
            'languages' => [
                ['code' => 'en', 'name' => 'English', 'default' => true],
                ['code' => 'de', 'name' => 'Deutsch']
            ],
            'options' => [
                'johannschopplich.content-translator' => [
                    'DeepL.apiKey' => 'test-key:fx',
                    'DeepL.requestOptions' => [
                        'tag_handling' => 'xml'
                    ]
                ]
            ]
        ]);

        $requests = [];
        $deepL = $this->createMockDeepL($requests);

        // Without <span translate="no">, keeps custom tag_handling
        $deepL->translateMany(['Normal text'], 'de');
        $this->assertSame('xml', $requests[0]['requestOptions']['tag_handling']);

        // With <span translate="no">, forces tag_handling to 'html'
        $deepL->translateMany(['Keep <span translate="no">Brand</span> unchanged'], 'de');
        $this->assertSame('html', $requests[1]['requestOptions']['tag_handling']);
    }

    // `translateMany` – batching & chunking
    // -----------------------------------------------------------------------

    #[Test]
    public function translate_many_batches_texts_in_single_request(): void
    {
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
        $requests = [];
        $deepL = $this->createMockDeepL($requests);

        $texts = array_fill(0, 60, 'Hello');
        $results = $deepL->translateMany($texts, 'de');

        $this->assertCount(2, $requests);
        $this->assertCount(50, $requests[0]['texts']);
        $this->assertCount(10, $requests[1]['texts']);
        $this->assertCount(60, $results);
    }
}
