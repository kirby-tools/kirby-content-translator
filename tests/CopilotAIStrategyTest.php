<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\Translation\Exception\TranslationException;
use JohannSchopplich\ContentTranslator\Translation\ExecutionOptions;
use JohannSchopplich\ContentTranslator\Translation\Strategies\CopilotAIStrategy;
use JohannSchopplich\ContentTranslator\Translation\TranslationLanguage;
use JohannSchopplich\ContentTranslator\Translation\TranslationMode;
use JohannSchopplich\ContentTranslator\Translation\TranslationUnit;
use JohannSchopplich\Copilot\AI\Client;
use JohannSchopplich\Copilot\AI\ProviderName;
use JohannSchopplich\Copilot\AI\Providers\Provider;
use JohannSchopplich\Copilot\AI\Resolver;
use Kirby\Cms\App;
use Kirby\Exception\AuthException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CopilotAIStrategyTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Client::class)) {
            $this->markTestSkipped('kirby-copilot is not installed in this dev tree');
        }

        Client::reset();
    }

    protected function tearDown(): void
    {
        App::destroy();
    }

    /**
     * @param array<int, array<string, mixed>> $responses
     * @param list<array{messages: list<array{role: string, content: string}>, schema: array<string, mixed>}> $captured
     */
    private function client(array $responses, array &$captured = []): Client
    {
        $provider = new class ($responses, $captured) implements Provider {
            /**
             * @param list<array<string, mixed>> $responses
             */
            public function __construct(
                private array $responses,
                private array &$captured,
            ) {
            }

            public function generateObject(array $messages, array $schema): array
            {
                $this->captured[] = ['messages' => $messages, 'schema' => $schema];
                if ($this->responses === []) {
                    throw new RuntimeException('no more responses queued');
                }
                return array_shift($this->responses);
            }
        };

        return new Client(
            resolver: new Resolver(defaultProvider: ProviderName::OpenAI, providers: []),
            providerOverride: $provider,
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
    public function prefers_constructor_system_prompt_over_config(): void
    {
        new App([
            'options' => [
                'johannschopplich.content-translator' => ['ai' => ['systemPrompt' => 'from config']],
            ],
        ]);

        $captured = [];
        $client = $this->client([['translations' => ['Hallo']]], $captured);
        $strategy = new CopilotAIStrategy(client: $client, systemPrompt: 'from ctor');

        $strategy->execute(
            units: [new TranslationUnit('Hello', TranslationMode::Batch, 'a')],
            options: self::options(),
        );

        $this->assertSame('from ctor', $captured[0]['messages'][0]['content']);
    }

    #[Test]
    public function overrides_default_system_prompt_with_config(): void
    {
        new App([
            'options' => [
                'johannschopplich.content-translator' => ['ai' => ['systemPrompt' => 'from config']],
            ],
        ]);

        $captured = [];
        $client = $this->client([['translations' => ['Hallo']]], $captured);
        $strategy = new CopilotAIStrategy(client: $client);

        $strategy->execute(
            units: [new TranslationUnit('Hello', TranslationMode::Batch, 'a')],
            options: self::options(),
        );

        $this->assertSame('from config', $captured[0]['messages'][0]['content']);
    }

    #[Test]
    public function applies_default_system_prompt_when_neither_constructor_nor_config_provides_one(): void
    {
        new App();

        $captured = [];
        $client = $this->client([['translations' => ['Hallo']]], $captured);
        $strategy = new CopilotAIStrategy(client: $client);

        $strategy->execute(
            units: [new TranslationUnit('Hello', TranslationMode::Batch, 'a')],
            options: self::options(),
        );

        $this->assertStringContainsString('professional translator', $captured[0]['messages'][0]['content']);
    }

    #[Test]
    public function returns_translations_in_input_order(): void
    {
        new App();
        $captured = [];
        $client = $this->client([['translations' => ['Hallo', 'Welt']]], $captured);
        $strategy = new CopilotAIStrategy(client: $client);

        $result = $strategy->execute(
            units: [
                new TranslationUnit('Hello', TranslationMode::Batch, 'a'),
                new TranslationUnit('World', TranslationMode::Batch, 'b'),
            ],
            options: self::options(),
        );

        $this->assertSame(['Hallo', 'Welt'], $result);
    }

    #[Test]
    public function chunks_input_when_unit_count_exceeds_batch_size(): void
    {
        new App();
        $captured = [];
        $client = $this->client(
            [
                ['translations' => array_fill(0, 50, 'X')],
                ['translations' => ['X']],
            ],
            $captured,
        );
        $strategy = new CopilotAIStrategy(client: $client);

        $units = [];
        for ($i = 0; $i < 51; $i++) {
            $units[] = new TranslationUnit('t' . $i, TranslationMode::Batch, 'k' . $i);
        }

        $result = $strategy->execute($units, options: self::options());

        $this->assertCount(51, $result);
        $this->assertCount(2, $captured);
    }

    #[Test]
    public function chunks_input_when_total_byte_size_exceeds_batch_limit(): void
    {
        new App();
        $captured = [];
        $client = $this->client(
            [
                ['translations' => ['A']],
                ['translations' => ['B']],
            ],
            $captured,
        );
        $strategy = new CopilotAIStrategy(client: $client);

        $largeText = str_repeat('x', 60_000);
        $result = $strategy->execute(
            units: [
                new TranslationUnit($largeText, TranslationMode::Batch, 'a'),
                new TranslationUnit($largeText, TranslationMode::Batch, 'b'),
            ],
            options: self::options(),
        );

        $this->assertSame(['A', 'B'], $result);
        $this->assertCount(2, $captured);
    }

    #[Test]
    public function keeps_source_text_when_translation_drops_a_placeholder(): void
    {
        new App();
        $captured = [];
        $client = $this->client(
            [['translations' => ['Click here', 'Hallo']]],
            $captured,
        );
        $strategy = new CopilotAIStrategy(client: $client);

        $result = $strategy->execute(
            units: [
                new TranslationUnit('Click <c0/> now', TranslationMode::Batch, 'body'),
                new TranslationUnit('Hello', TranslationMode::Batch, 'title'),
            ],
            options: self::options(),
        );

        $this->assertSame(['Click <c0/> now', 'Hallo'], $result);
    }

    #[Test]
    public function fires_translate_warning_hook_on_placeholder_mismatch(): void
    {
        $warnings = [];
        new App([
            'hooks' => [
                'content-translator.translate:warning' => function ($unit, $reason, $previous) use (&$warnings) {
                    $warnings[] = [
                        'fieldKey' => $unit->fieldKey,
                        'reason' => $reason,
                        'previous' => $previous,
                    ];
                },
            ],
        ]);

        $captured = [];
        $client = $this->client(
            [['translations' => ['Click here', 'Hallo']]],
            $captured,
        );
        $strategy = new CopilotAIStrategy(client: $client);

        $strategy->execute(
            units: [
                new TranslationUnit('Click <c0/> now', TranslationMode::Batch, 'body'),
                new TranslationUnit('Hello', TranslationMode::Batch, 'title'),
            ],
            options: self::options(),
        );

        $this->assertSame([
            ['fieldKey' => 'body', 'reason' => 'placeholder count mismatch', 'previous' => null],
        ], $warnings);
    }

    #[Test]
    public function fires_translate_warning_hook_on_upstream_failure(): void
    {
        $warnings = [];
        new App([
            'hooks' => [
                'content-translator.translate:warning' => function ($unit, $reason, $previous) use (&$warnings) {
                    $warnings[] = [
                        'fieldKey' => $unit->fieldKey,
                        'reason' => $reason,
                        'previousMessage' => $previous?->getMessage(),
                    ];
                },
            ],
        ]);

        $captured = [];
        $client = $this->client([['translations' => array_fill(0, 50, 'X')]], $captured);
        $strategy = new CopilotAIStrategy(client: $client);

        $units = [];
        for ($i = 0; $i < 51; $i++) {
            $units[] = new TranslationUnit('t' . $i, TranslationMode::Batch, 'k' . $i);
        }

        $strategy->execute($units, options: self::options());

        $this->assertCount(1, $warnings);
        $this->assertSame('k50', $warnings[0]['fieldKey']);
        $this->assertNotEmpty($warnings[0]['previousMessage']);
    }

    #[Test]
    public function throws_auth_exception_when_provider_lacks_api_key(): void
    {
        new App([
            'options' => [
                'johannschopplich.copilot' => ['provider' => 'openai'],
            ],
        ]);

        $strategy = new CopilotAIStrategy(client: new Client());

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Missing API key in "johannschopplich.copilot.providers.openai.apiKey"');

        $strategy->execute(
            units: [new TranslationUnit('Hello', TranslationMode::Batch, 'a')],
            options: self::options(),
        );
    }

    #[Test]
    public function throws_when_no_units_can_be_translated(): void
    {
        new App();
        $captured = [];
        $client = $this->client([], $captured);
        $strategy = new CopilotAIStrategy(client: $client);

        $this->expectException(TranslationException::class);
        $this->expectExceptionMessageMatches('/copilot-ai strategy failed/');

        $strategy->execute(
            units: [
                new TranslationUnit('A', TranslationMode::Batch, 'a'),
                new TranslationUnit('B', TranslationMode::Batch, 'b'),
            ],
            options: self::options(),
        );
    }

    #[Test]
    public function keeps_source_for_failed_units_when_others_succeed(): void
    {
        new App();
        $captured = [];
        $client = $this->client([['translations' => array_fill(0, 50, 'X')]], $captured);
        $strategy = new CopilotAIStrategy(client: $client);

        $units = [];
        for ($i = 0; $i < 51; $i++) {
            $units[] = new TranslationUnit('t' . $i, TranslationMode::Batch, 'k' . $i);
        }

        $result = $strategy->execute($units, options: self::options());

        $firstChunk = array_slice($result, 0, 50);
        $secondChunk = array_slice($result, 50);

        $this->assertSame(array_fill(0, 50, 'X'), $firstChunk);
        $this->assertSame(['t50'], $secondChunk);
    }
}
