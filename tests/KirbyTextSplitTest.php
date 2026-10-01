<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\KirbyText;
use Kirby\Cms\App;
use Kirby\Exception\LogicException;
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
        // `split()` recognizes a KirbyTag by its registered type; the shared
        // corpus also uses a `callout` tag, as a plugin would register it.
        new App(['tags' => ['callout' => ['html' => fn () => '']]]);
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function parentheticals(): iterable
    {
        yield 'times' => ['Open daily (10:00 - 18:00)'];
        yield 'label' => ['Opening hours (Note: closed on Sundays)'];
        yield 'label around a tag' => ['Opening hours (Note: see (link: /hours text: hours))'];
    }

    #[Test]
    #[DataProvider('parentheticals')]
    public function keeps_a_parenthetical_without_a_registered_kirby_tag_type_as_prose(string $text): void
    {
        ['unitTexts' => $unitTexts, 'restore' => $restore] = KirbyText::split($text);

        $this->assertStringStartsWith(strstr($text, '(', true) . '(', $unitTexts[0]);
        $this->assertSame($text, $restore($unitTexts));
    }

    #[Test]
    public function splits_a_kirby_tag_inside_a_parenthetical(): void
    {
        ['unitTexts' => $unitTexts] = KirbyText::split('Opening hours (Note: see (link: /hours text: hours))');

        $this->assertSame(['Opening hours (Note: see <c0/>)'], $unitTexts);
    }

    #[Test]
    public function splits_a_kirby_tag_a_plugin_registers(): void
    {
        new App(['tags' => ['quote' => ['html' => fn () => '']]]);

        ['unitTexts' => $unitTexts] = KirbyText::split('As she said: (quote: Less is more)');

        $this->assertSame(['As she said: <c0/>'], $unitTexts);
    }
}
