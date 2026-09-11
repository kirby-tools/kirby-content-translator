<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\KirbyText;
use JohannSchopplich\ContentTranslator\Translation\Strategies\CopilotAIStrategy;
use JohannSchopplich\ContentTranslator\Translation\UntranslatableText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Shared with `contract.test.ts` – a one-sided edit fails here first.
 */
final class ContractTest extends TestCase
{
    use ContractFixture;

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function untranslatableCases(): iterable
    {
        foreach (self::contract()['untranslatableCases'] as $case) {
            yield var_export($case['text'], true) => [$case['text'], $case['isUntranslatable']];
        }
    }

    #[Test]
    #[DataProvider('untranslatableCases')]
    public function evaluates_untranslatable_text_per_contract(string $text, bool $isUntranslatable): void
    {
        $this->assertSame($isUntranslatable, UntranslatableText::matches($text));
    }

    #[Test]
    public function emits_placeholders_in_the_contract_format(): void
    {
        $placeholder = self::contract()['placeholder'];
        ['unitTexts' => $unitTexts] = KirbyText::split('(link: /a)');

        $this->assertSame(
            str_replace('{n}', (string)$placeholder['indexBase'], $placeholder['format']),
            $unitTexts[0]
        );
        $this->assertSame(1, preg_match(KirbyText::PLACEHOLDER_PATTERN, $unitTexts[0]));
    }

    #[Test]
    public function caps_ai_batches_at_the_contract_limits(): void
    {
        $batching = self::contract()['batching'];

        $this->assertSame($batching['maxBatchSize'], CopilotAIStrategy::MAX_BATCH_SIZE);
        $this->assertSame($batching['maxSizePerBatch'], CopilotAIStrategy::MAX_BYTES_PER_BATCH);
    }
}
