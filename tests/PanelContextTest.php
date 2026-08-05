<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\PanelContext;
use JohannSchopplich\ContentTranslator\Translation\ExecutionOptions;
use JohannSchopplich\ContentTranslator\Translation\Strategy;
use JohannSchopplich\Copilot\AI\Client as CopilotClient;
use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PanelContextTest extends TestCase
{
    protected function tearDown(): void
    {
        App::destroy();
    }

    #[Test]
    public function omits_options_the_panel_never_reads(): void
    {
        new App([
            'options' => [
                'johannschopplich.content-translator' => [
                    'coverage' => ['pages' => fn () => []],
                    'title' => true,
                ],
            ],
        ]);

        $config = PanelContext::config();

        $this->assertArrayNotHasKey('coverage', $config);
        // Registered by the plugin itself, so it reaches the Panel unasked for.
        $this->assertArrayNotHasKey('cache', $config);
        $this->assertTrue($config['title']);
    }

    #[Test]
    public function reports_the_effective_strategy_instead_of_the_configured_object(): void
    {
        $strategy = new class () implements Strategy {
            public string $apiKey = 'super-secret';

            public function execute(array $units, ExecutionOptions $options): array
            {
                return [];
            }
        };

        new App([
            'options' => [
                'johannschopplich.content-translator' => ['strategy' => $strategy],
            ],
        ]);

        $config = PanelContext::config();

        $this->assertSame('custom', $config['strategy']);
        $this->assertStringNotContainsString('super-secret', json_encode($config));
    }

    #[Test]
    public function reduces_the_deepl_options_to_an_api_key_flag(): void
    {
        new App([
            'options' => [
                'johannschopplich.content-translator' => [
                    'DeepL' => [
                        'apiKey' => 'deepl-key',
                        'targetLanguageOverrides' => ['cn' => 'ZH-HANS'],
                    ],
                ],
            ],
        ]);

        $config = PanelContext::config();

        $this->assertSame(['apiKey' => true], $config['DeepL']);
    }

    #[Test]
    public function carries_the_ai_system_prompt_the_strategy_would_use(): void
    {
        if (!class_exists(CopilotClient::class)) {
            $this->markTestSkipped('kirby-copilot is not installed in this dev tree');
        }

        new App([
            'options' => [
                'johannschopplich.content-translator' => [
                    'ai' => ['systemPrompt' => 'Translate like a lawyer.'],
                ],
            ],
        ]);

        $this->assertSame(
            ['systemPrompt' => 'Translate like a lawyer.'],
            PanelContext::config()['ai'],
        );
    }
}
