<?php

declare(strict_types = 1);

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
            yield var_export($case['sourceText'], true) => [$case['sourceText'], $case['isUntranslatable']];
        }
    }

    #[Test]
    #[DataProvider('untranslatableCases')]
    public function evaluates_untranslatable_text(string $sourceText, bool $isUntranslatable): void
    {
        $this->assertSame($isUntranslatable, UntranslatableText::matches($sourceText));
    }
}
