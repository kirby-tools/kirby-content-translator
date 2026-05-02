<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\Translation\ExecutionOptions;
use JohannSchopplich\ContentTranslator\Translation\Strategies\CallableStrategy;
use JohannSchopplich\ContentTranslator\Translation\TranslationLanguage;
use JohannSchopplich\ContentTranslator\Translation\TranslationUnit;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CallableStrategyTest extends TestCase
{
    #[Test]
    public function iterates_units_and_invokes_closure_with_text_and_language_codes(): void
    {
        $calls = [];
        $strategy = new CallableStrategy(
            function (string $text, string $targetLanguage, string|null $sourceLanguage) use (&$calls): string {
                $calls[] = [$text, $targetLanguage, $sourceLanguage];
                return strtoupper($text);
            },
        );

        $result = $strategy->execute(
            units: [
                new TranslationUnit('hello'),
                new TranslationUnit('world'),
            ],
            options: new ExecutionOptions(
                targetLanguage: new TranslationLanguage(code: 'de', name: 'Deutsch'),
                sourceLanguage: new TranslationLanguage(code: 'en', name: 'English'),
            ),
        );

        $this->assertSame(['HELLO', 'WORLD'], $result);
        $this->assertSame([
            ['hello', 'de', 'en'],
            ['world', 'de', 'en'],
        ], $calls);
    }

    #[Test]
    public function passes_null_source_language_through_unchanged(): void
    {
        $captured = null;
        $strategy = new CallableStrategy(
            function (string $text, string $targetLanguage, string|null $sourceLanguage) use (&$captured): string {
                $captured = $sourceLanguage;
                return $text;
            },
        );

        $strategy->execute(
            units: [new TranslationUnit('hi')],
            options: new ExecutionOptions(
                targetLanguage: new TranslationLanguage(code: 'de', name: 'Deutsch'),
            ),
        );

        $this->assertNull($captured);
    }

    #[Test]
    public function returns_empty_list_for_empty_units(): void
    {
        $strategy = new CallableStrategy(
            static fn (): string => throw new RuntimeException('closure should not be called'),
        );

        $result = $strategy->execute(
            units: [],
            options: new ExecutionOptions(
                targetLanguage: new TranslationLanguage(code: 'de', name: 'Deutsch'),
            ),
        );

        $this->assertSame([], $result);
    }

    #[Test]
    public function propagates_exceptions_from_the_closure(): void
    {
        $strategy = new CallableStrategy(
            static fn (): string => throw new RuntimeException('upstream is down'),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('upstream is down');

        $strategy->execute(
            units: [new TranslationUnit('hi')],
            options: new ExecutionOptions(
                targetLanguage: new TranslationLanguage(code: 'de', name: 'Deutsch'),
            ),
        );
    }
}
