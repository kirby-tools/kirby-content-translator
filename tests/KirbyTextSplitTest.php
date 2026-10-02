<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\KirbyText;
use Kirby\Cms\App;
use Kirby\Exception\LogicException;
use Kirby\Text\KirbyTag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class KirbyTextSplitTest extends TestCase
{
    protected function setUp(): void
    {
        new App();
    }

    protected function tearDown(): void
    {
        App::destroy();
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function conformanceCases(): iterable
    {
        foreach (glob(__DIR__ . '/fixtures/kirby-text/*.json') as $path) {
            yield basename($path, '.json') => [json_decode(file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR)];
        }
    }

    /**
     * Shared with `kirby-text.test.ts` – drift fails here first.
     *
     * @param array<string, mixed> $case
     */
    #[Test]
    #[DataProvider('conformanceCases')]
    public function splits_and_restores(array $case): void
    {
        KirbyTag::$types = array_fill_keys($case['kirbyTagTypes'], []);
        KirbyTag::$aliases = [];

        ['unitTexts' => $unitTexts, 'restore' => $restore] = KirbyText::split($case['sourceText'], $case['kirbyTags']);

        $this->assertSame($case['unitTexts'], $unitTexts);
        $this->assertSame($case['restoredText'], $restore($case['translatedUnitTexts']));
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
