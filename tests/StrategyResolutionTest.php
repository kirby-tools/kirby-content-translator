<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\Translation\ExecutionOptions;
use JohannSchopplich\ContentTranslator\Translation\Strategy;
use JohannSchopplich\ContentTranslator\Translator;
use JohannSchopplich\Copilot\AI\Client as CopilotClient;
use Kirby\Cms\App;
use Kirby\Exception\AuthException;
use Kirby\Exception\LogicException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class StrategyResolutionTest extends TestCase
{
    protected function tearDown(): void
    {
        App::destroy();
    }

    #[Test]
    public function method_param_strategy_wins_over_translate_fn_config(): void
    {
        new App([
            'options' => [
                'johannschopplich.content-translator' => [
                    'translateFn' => fn (string $text, string $lang) => "[fromConfig]$text",
                ],
            ],
        ]);

        $override = new class () implements Strategy {
            public function execute(array $units, ExecutionOptions $options): array
            {
                return array_map(fn ($unit) => '[fromOverride]' . $unit->text, $units);
            }
        };

        $this->assertSame('[fromOverride]hi', Translator::translateText('hi', 'de', null, $override));
    }

    #[Test]
    public function strategy_config_string_deepl_selects_deepl_strategy(): void
    {
        new App([
            'options' => [
                'johannschopplich.content-translator' => ['strategy' => 'deepl'],
            ],
        ]);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessageMatches('/Missing DeepL API key/i');

        Translator::translateText('hi', 'de');
    }

    #[Test]
    public function strategy_config_string_ai_selects_copilot_strategy(): void
    {
        if (!class_exists(CopilotClient::class)) {
            $this->markTestSkipped('kirby-copilot is not installed in this dev tree');
        }

        new App([
            'options' => [
                'johannschopplich.content-translator' => ['strategy' => 'ai'],
                'johannschopplich.copilot' => ['provider' => 'openai'],
            ],
        ]);
        CopilotClient::reset();

        $this->expectException(AuthException::class);
        $this->expectExceptionMessageMatches('/copilot\.providers\.openai\.apiKey/');

        Translator::translateText('hi', 'de');
    }

    #[Test]
    public function strategy_config_closure_resolves_to_callable_strategy(): void
    {
        new App([
            'options' => [
                'johannschopplich.content-translator' => [
                    'strategy' => fn (string $text, string $lang): string => "[$lang]$text",
                ],
            ],
        ]);

        $this->assertSame('[de]hi', Translator::translateText('hi', 'de'));
    }

    #[Test]
    public function strategy_config_strategy_instance_resolves_to_itself(): void
    {
        $configuredStrategy = new class () implements Strategy {
            public function execute(array $units, ExecutionOptions $options): array
            {
                return array_map(fn ($u) => '[fromInstance]' . $u->text, $units);
            }
        };

        new App([
            'options' => [
                'johannschopplich.content-translator' => ['strategy' => $configuredStrategy],
            ],
        ]);

        $this->assertSame('[fromInstance]hi', Translator::translateText('hi', 'de'));
    }

    #[Test]
    public function strategy_config_string_takes_precedence_over_translate_fn(): void
    {
        new App([
            'options' => [
                'johannschopplich.content-translator' => [
                    'strategy' => fn (string $text, string $lang): string => "[fromStrategy]$text",
                    'translateFn' => fn (string $text, string $lang): string => "[fromTranslateFn]$text",
                ],
            ],
        ]);

        $this->assertSame('[fromStrategy]hi', Translator::translateText('hi', 'de'));
    }

    #[Test]
    public function unknown_strategy_string_throws_logic_exception(): void
    {
        new App([
            'options' => [
                'johannschopplich.content-translator' => ['strategy' => 'banana'],
            ],
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unknown strategy "banana"');

        Translator::translateText('hi', 'de');
    }
}
