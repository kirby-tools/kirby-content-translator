<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\DeepL;
use JohannSchopplich\ContentTranslator\Translation\Exception\TranslationException;
use JohannSchopplich\ContentTranslator\Translation\ExecutionOptions;
use JohannSchopplich\ContentTranslator\Translation\Strategies\DeepLStrategy;
use JohannSchopplich\ContentTranslator\Translation\TranslationLanguage;
use JohannSchopplich\ContentTranslator\Translation\TranslationMode;
use JohannSchopplich\ContentTranslator\Translation\TranslationUnit;
use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DeepLStrategyTest extends TestCase
{
    protected function tearDown(): void
    {
        App::destroy();
    }

    private function appWithDeepLConfig(array $hooks = []): App
    {
        return new App([
            'languages' => [
                ['code' => 'en', 'name' => 'English', 'default' => true, 'locale' => 'en_US'],
                ['code' => 'de', 'name' => 'Deutsch', 'locale' => 'de_DE'],
            ],
            'options' => [
                'debug' => true,
                'johannschopplich.content-translator' => ['DeepL.apiKey' => 'test-key:fx'],
            ],
            'hooks' => $hooks,
        ]);
    }

    /**
     * @param array<int,array{texts: array<string>, ...}> $capturedRequests
     */
    private function createMockDeepL(array &$capturedRequests = []): DeepL
    {
        return new DeepL(
            remote: function (string $url, array $options) use (&$capturedRequests): object {
                $body = json_decode($options['data'], associative: true);
                $capturedRequests[] = ['texts' => $body['text']];

                return new class ($body['text']) {
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
                                fn (string $text) => ['text' => "[de]$text"],
                                $this->texts,
                            ),
                        ];
                    }
                };
            },
        );
    }

    private static function options(): ExecutionOptions
    {
        return new ExecutionOptions(
            targetLanguage: new TranslationLanguage('de', 'Deutsch'),
            sourceLanguage: new TranslationLanguage('en', 'English'),
        );
    }

    #[Test]
    public function batch_mode_units_translate_together(): void
    {
        $this->appWithDeepLConfig();
        $captured = [];
        $strategy = new DeepLStrategy(deepL: $this->createMockDeepL($captured));

        $result = $strategy->execute(
            units: [
                new TranslationUnit('Hello', TranslationMode::Batch, 'a'),
                new TranslationUnit('World', TranslationMode::Batch, 'b'),
            ],
            options: self::options(),
        );

        $this->assertSame(['[de]Hello', '[de]World'], $result);
        $this->assertCount(1, $captured);
        $this->assertSame(['Hello', 'World'], $captured[0]['texts']);
    }

    #[Test]
    public function single_mode_units_translate_independently(): void
    {
        $this->appWithDeepLConfig();
        $captured = [];
        $strategy = new DeepLStrategy(deepL: $this->createMockDeepL($captured));

        $result = $strategy->execute(
            units: [
                new TranslationUnit('Batch1', TranslationMode::Batch, 'a'),
                new TranslationUnit('Cell1', TranslationMode::Single, 'b'),
                new TranslationUnit('Batch2', TranslationMode::Batch, 'c'),
                new TranslationUnit('Cell2', TranslationMode::Single, 'd'),
            ],
            options: self::options(),
        );

        $this->assertSame(['[de]Batch1', '[de]Cell1', '[de]Batch2', '[de]Cell2'], $result);
        $this->assertCount(3, $captured);
        $this->assertSame(['Batch1', 'Batch2'], $captured[0]['texts']);
        $this->assertSame(['Cell1'], $captured[1]['texts']);
        $this->assertSame(['Cell2'], $captured[2]['texts']);
    }

    private function deepLFailingOnCall(int $failingCall): DeepL
    {
        $callIndex = 0;
        return new DeepL(
            remote: function (string $url, array $options) use (&$callIndex, $failingCall): object {
                $body = json_decode($options['data'], associative: true);
                $callIndex++;
                if ($callIndex === $failingCall) {
                    throw new RuntimeException('upstream timeout');
                }
                return new class ($body['text']) {
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
                        return ['translations' => array_map(fn (string $t) => ['text' => "[de]$t"], $this->texts)];
                    }
                };
            },
        );
    }

    /**
     * @return array<string, array{0: int, 1: list<TranslationUnit>, 2: list<string>}>
     */
    public static function partialFailureCases(): array
    {
        return [
            'batch call fails, single call succeeds' => [
                1,
                [
                    new TranslationUnit('Batch1', TranslationMode::Batch, 'a'),
                    new TranslationUnit('Batch2', TranslationMode::Batch, 'b'),
                    new TranslationUnit('Cell', TranslationMode::Single, 'c'),
                ],
                ['Batch1', 'Batch2', '[de]Cell'],
            ],
            'batch call succeeds, single call fails' => [
                2,
                [
                    new TranslationUnit('Batch', TranslationMode::Batch, 'a'),
                    new TranslationUnit('Single', TranslationMode::Single, 'b'),
                ],
                ['[de]Batch', 'Single'],
            ],
        ];
    }

    /**
     * @param list<TranslationUnit> $units
     * @param list<string> $expected
     */
    #[Test]
    #[DataProvider('partialFailureCases')]
    public function keeps_source_text_for_units_in_a_failing_call(int $failingCall, array $units, array $expected): void
    {
        $this->appWithDeepLConfig();
        $strategy = new DeepLStrategy(deepL: $this->deepLFailingOnCall($failingCall));

        $this->assertSame($expected, $strategy->execute($units, options: self::options()));
    }

    #[Test]
    public function fires_translate_warning_hook_per_unit_when_a_call_fails(): void
    {
        $captured = [];
        $this->appWithDeepLConfig([
            'content-translator.translate:warning' => function ($unit, $reason, $previous) use (&$captured) {
                $captured[] = [
                    'fieldKey' => $unit->fieldKey,
                    'reason' => $reason,
                    'previousMessage' => $previous?->getMessage(),
                ];
            },
        ]);

        $strategy = new DeepLStrategy(deepL: $this->deepLFailingOnCall(1));

        $strategy->execute(
            units: [
                new TranslationUnit('Batch1', TranslationMode::Batch, 'a'),
                new TranslationUnit('Batch2', TranslationMode::Batch, 'b'),
                new TranslationUnit('Cell', TranslationMode::Single, 'c'),
            ],
            options: self::options(),
        );

        $this->assertSame([
            ['fieldKey' => 'a', 'reason' => 'upstream timeout', 'previousMessage' => 'upstream timeout'],
            ['fieldKey' => 'b', 'reason' => 'upstream timeout', 'previousMessage' => 'upstream timeout'],
        ], $captured);
    }

    #[Test]
    public function throws_when_no_units_can_be_translated(): void
    {
        $this->appWithDeepLConfig();

        $deepL = new DeepL(
            remote: function (): never {
                throw new RuntimeException('upstream is down');
            },
        );

        $strategy = new DeepLStrategy(deepL: $deepL);

        $this->expectException(TranslationException::class);
        $this->expectExceptionMessageMatches('/deepl strategy failed/i');

        $strategy->execute(
            units: [
                new TranslationUnit('A', TranslationMode::Single, 'a'),
                new TranslationUnit('B', TranslationMode::Single, 'b'),
            ],
            options: self::options(),
        );
    }
}
