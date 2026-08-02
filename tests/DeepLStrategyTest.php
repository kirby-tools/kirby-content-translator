<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\DeepL;
use JohannSchopplich\ContentTranslator\Translation\Exception\TranslationException;
use JohannSchopplich\ContentTranslator\Translation\ExecutionOptions;
use JohannSchopplich\ContentTranslator\Translation\Strategies\DeepLStrategy;
use JohannSchopplich\ContentTranslator\Translation\TranslationLanguage;
use JohannSchopplich\ContentTranslator\Translation\TranslationUnit;
use Kirby\Cms\App;
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
    public function translates_all_units_in_one_batch_call(): void
    {
        $this->appWithDeepLConfig();
        $captured = [];
        $strategy = new DeepLStrategy(deepL: $this->createMockDeepL($captured));

        $result = $strategy->execute(
            units: [
                new TranslationUnit('Hello', 'a'),
                new TranslationUnit('World', 'b'),
            ],
            options: self::options(),
        );

        $this->assertSame(['[de]Hello', '[de]World'], $result);
        $this->assertCount(1, $captured);
        $this->assertSame(['Hello', 'World'], $captured[0]['texts']);
    }

    #[Test]
    public function fires_translate_warning_hook_per_unit_when_the_call_fails(): void
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

        $deepL = new DeepL(
            remote: function (): never {
                throw new RuntimeException('upstream timeout');
            },
        );

        $strategy = new DeepLStrategy(deepL: $deepL);

        try {
            $strategy->execute(
                units: [
                    new TranslationUnit('Batch1', 'a'),
                    new TranslationUnit('Batch2', 'b'),
                ],
                options: self::options(),
            );
            $this->fail('Expected TranslationException');
        } catch (TranslationException) {
        }

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
                new TranslationUnit('A', 'a'),
                new TranslationUnit('B', 'b'),
            ],
            options: self::options(),
        );
    }
}
