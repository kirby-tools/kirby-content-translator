<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\KirbyText;
use Kirby\Exception\LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class KirbyTextSplitTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function conformanceCases(): iterable
    {
        foreach (glob(__DIR__ . '/fixtures/kirby-text/*.json') as $path) {
            if (basename($path) === 'schema.json') {
                continue;
            }

            yield basename($path, '.json') => [json_decode(file_get_contents($path), true)];
        }
    }

    /**
     * Shared with `kirby-text.test.ts` – drift fails here first.
     *
     * @param array<string, mixed> $case
     */
    #[Test]
    #[DataProvider('conformanceCases')]
    public function conforms_to_shared_corpus(array $case): void
    {
        ['unitTexts' => $unitTexts, 'restore' => $restore] = KirbyText::split($case['input'], $case['kirbyTags']);

        $this->assertSame($case['expectedUnitTexts'], $unitTexts);
        $this->assertSame($case['expectedPlaceholderCount'], preg_match_all(KirbyText::PLACEHOLDER_PATTERN, $unitTexts[0]));
        $this->assertSame($case['expectedRestore'], $restore($case['restoredWith']));
    }

    #[Test]
    public function restore_throws_when_the_translation_count_does_not_match_the_unit_texts(): void
    {
        $text = '(link: /a text: site)';
        ['unitTexts' => $unitTexts, 'restore' => $restore] = KirbyText::split($text, ['link' => ['text']]);

        $this->assertCount(2, $unitTexts);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected 2 translations, got 1');

        $restore([$unitTexts[0]]);
    }
}
