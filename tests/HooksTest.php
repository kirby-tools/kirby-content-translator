<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\Translation\ExecutionOptions;
use JohannSchopplich\ContentTranslator\Translation\Strategy;
use JohannSchopplich\ContentTranslator\Translator;
use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class HooksTest extends TestCase
{
    protected function tearDown(): void
    {
        App::destroy();
    }

    private static function pluginOptions(): array
    {
        return [
            'johannschopplich.content-translator' => [
                'translateFn' => fn (string $text, string $lang) => "[$lang]$text",
            ],
        ];
    }

    private function appWithHomePage(array $hooks = [], string $homeText = 'Hello'): App
    {
        return new App([
            'languages' => [
                ['code' => 'en', 'name' => 'English', 'default' => true],
                ['code' => 'de', 'name' => 'Deutsch'],
            ],
            'blueprints' => [
                'pages/default' => [
                    'fields' => ['text' => ['type' => 'text', 'translate' => true]],
                ],
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'home',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => ['text' => $homeText]],
                        ],
                    ],
                ],
            ],
            'hooks' => $hooks,
            'options' => self::pluginOptions(),
        ]);
    }

    #[Test]
    public function applies_both_before_and_after_hooks(): void
    {
        $app = $this->appWithHomePage(
            hooks: [
                'content-translator.translate:before' => fn ($text) => trim($text) . '!',
                'content-translator.translate:after' => fn ($text) => strtoupper($text),
            ],
            homeText: 'hello',
        );

        $translator = new Translator($app->page('home'));
        $translator->translateContent('en', 'de');

        $this->assertSame('[DE]HELLO!', $translator->model()->content('en')->get('text')->value());
    }

    #[Test]
    public function hooks_receive_full_payload(): void
    {
        $beforeParams = [];
        $afterParams = [];

        $app = $this->appWithHomePage([
            'content-translator.translate:before' => function ($text, $targetLanguage, $sourceLanguage, $type) use (&$beforeParams) {
                $beforeParams = compact('text', 'targetLanguage', 'sourceLanguage', 'type');
                return $text;
            },
            'content-translator.translate:after' => function ($text, $originalText, $targetLanguage, $sourceLanguage, $type) use (&$afterParams) {
                $afterParams = compact('text', 'originalText', 'targetLanguage', 'sourceLanguage', 'type');
                return $text;
            },
        ]);

        $translator = new Translator($app->page('home'));
        $translator->translateContent('en', 'de', 'en');

        $expectedBefore = [
            'text' => 'Hello',
            'targetLanguage' => 'de',
            'sourceLanguage' => 'en',
            'type' => 'text',
        ];
        foreach ($expectedBefore as $key => $value) {
            $this->assertSame($value, $beforeParams[$key]);
        }

        $expectedAfter = [
            'text' => '[de]Hello',
            'originalText' => 'Hello',
            'targetLanguage' => 'de',
            'sourceLanguage' => 'en',
            'type' => 'text',
        ];
        foreach ($expectedAfter as $key => $value) {
            $this->assertSame($value, $afterParams[$key]);
        }
    }

    #[Test]
    public function after_hook_receives_additive_unit_options_and_original_text_payload(): void
    {
        $captured = [];

        $app = $this->appWithHomePage([
            'content-translator.translate:after' => function ($text, $originalText, $unit, $options) use (&$captured) {
                $captured = [
                    'text' => $text,
                    'originalText' => $originalText,
                    'unitText' => $unit->text,
                    'unitMode' => $unit->mode->value,
                    'targetCode' => $options->targetLanguage->code,
                    'sourceCode' => $options->sourceLanguage?->code,
                ];
                return $text;
            },
        ]);

        $translator = new Translator($app->page('home'));
        $translator->translateContent('en', 'de', 'en');

        $this->assertSame([
            'text' => '[de]Hello',
            'originalText' => 'Hello',
            'unitText' => 'Hello',
            'unitMode' => 'batch',
            'targetCode' => 'de',
            'sourceCode' => 'en',
        ], $captured);
    }

    #[Test]
    public function before_hook_receives_additive_unit_and_options_payload(): void
    {
        $captured = [];

        $app = $this->appWithHomePage([
            'content-translator.translate:before' => function ($text, $unit, $options) use (&$captured) {
                $captured = [
                    'text' => $text,
                    'unitText' => $unit->text,
                    'unitMode' => $unit->mode->value,
                    'targetCode' => $options->targetLanguage->code,
                    'sourceCode' => $options->sourceLanguage?->code,
                ];
                return $text;
            },
        ]);

        $translator = new Translator($app->page('home'));
        $translator->translateContent('en', 'de', 'en');

        $this->assertSame([
            'text' => 'Hello',
            'unitText' => 'Hello',
            'unitMode' => 'batch',
            'targetCode' => 'de',
            'sourceCode' => 'en',
        ], $captured);
    }

    #[Test]
    public function warning_hook_receives_unit_reason_and_previous(): void
    {
        $warnings = [];
        new App([
            'hooks' => [
                'content-translator.translate:warning' => function ($unit, $reason, $previous) use (&$warnings) {
                    $warnings[] = compact('unit', 'reason', 'previous');
                },
            ],
        ]);

        $strategy = new class () implements Strategy {
            public function execute(array $units, ExecutionOptions $options): array
            {
                App::instance()->trigger('content-translator.translate:warning', [
                    'unit' => $units[0],
                    'reason' => 'simulated failure',
                    'previous' => null,
                ]);
                return array_map(fn ($u) => $u->text, $units);
            }
        };

        Translator::translateText('Hello', 'de', null, $strategy);

        $this->assertCount(1, $warnings);
        $this->assertSame('Hello', $warnings[0]['unit']->text);
        $this->assertSame('simulated failure', $warnings[0]['reason']);
        $this->assertNull($warnings[0]['previous']);
    }

    #[Test]
    public function static_translate_text_invokes_hooks(): void
    {
        $hookCalled = false;

        new App([
            'hooks' => [
                'content-translator.translate:before' => function ($text) use (&$hookCalled) {
                    $hookCalled = true;
                    return $text . ' modified';
                },
            ],
            'options' => self::pluginOptions(),
        ]);

        $result = Translator::translateText('Hello', 'de');

        $this->assertTrue($hookCalled);
        $this->assertSame('[de]Hello modified', $result);
    }
}
