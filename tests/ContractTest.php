<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\KirbyText;
use JohannSchopplich\ContentTranslator\Translation\Strategies\CopilotAIStrategy;
use JohannSchopplich\ContentTranslator\Translation\TextFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Shared with `contract.test.ts` – a one-sided edit fails here first.
 */
final class ContractTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function contract(): array
    {
        return json_decode(file_get_contents(__DIR__ . '/fixtures/contract.json'), true);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function skipCases(): iterable
    {
        foreach (self::contract()['skipCases'] as $case) {
            yield var_export($case['text'], true) => [$case['text'], $case['skip']];
        }
    }

    #[Test]
    #[DataProvider('skipCases')]
    public function evaluates_skip_predicate_per_contract(string $text, bool $skip): void
    {
        $this->assertSame($skip, TextFilter::shouldSkip($text));
    }

    #[Test]
    public function emits_placeholders_in_the_contract_format(): void
    {
        $placeholder = self::contract()['placeholder'];
        ['fragments' => $fragments] = KirbyText::split('(link: /a)');

        $this->assertSame(
            str_replace('{n}', (string)$placeholder['indexBase'], $placeholder['format']),
            $fragments[0]
        );
        $this->assertSame(1, preg_match(KirbyText::PLACEHOLDER_PATTERN, $fragments[0]));
    }

    #[Test]
    public function caps_ai_batches_at_the_contract_limits(): void
    {
        $batching = self::contract()['batching'];

        $this->assertSame($batching['maxBatchSize'], CopilotAIStrategy::MAX_BATCH_SIZE);
        $this->assertSame($batching['maxSizePerBatch'], CopilotAIStrategy::MAX_BYTES_PER_BATCH);
    }
}
