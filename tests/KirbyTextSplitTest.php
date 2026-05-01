<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\KirbyText;
use Kirby\Exception\LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class KirbyTextSplitTest extends TestCase
{
    #[Test]
    public function round_trips_plain_text_without_tags(): void
    {
        $text = 'Hello world';
        ['fragments' => $fragments, 'restore' => $restore] = KirbyText::split($text);

        $this->assertSame([$text], $fragments);
        $this->assertSame($text, $restore($fragments));
    }

    #[Test]
    public function protects_tags_verbatim_when_config_is_empty(): void
    {
        $text = 'Hello (link: /a text: world)!';
        ['fragments' => $fragments, 'restore' => $restore] = KirbyText::split($text);

        $this->assertCount(1, $fragments);
        $this->assertStringNotContainsString('link', $fragments[0]);
        $this->assertStringNotContainsString('world', $fragments[0]);

        $translated = [str_replace('Hello', 'Hallo', $fragments[0])];
        $this->assertSame('Hallo (link: /a text: world)!', $restore($translated));
    }

    #[Test]
    public function handles_nested_parentheses_inside_attr_value(): void
    {
        $text = '(link: /a text: our (awesome) site)';
        ['fragments' => $fragments, 'restore' => $restore] = KirbyText::split($text, ['link' => ['text']]);

        $this->assertContains('our (awesome) site', $fragments);
        $this->assertSame($text, $restore($fragments));
    }

    #[Test]
    public function does_not_split_url_schemes_as_attribute_boundaries(): void
    {
        $text = '(link: https://example.com text: visit us)';
        ['fragments' => $fragments, 'restore' => $restore] = KirbyText::split($text, ['link' => ['text']]);

        $this->assertContains('visit us', $fragments);
        $this->assertNotContains('https://example.com', $fragments);
        $this->assertSame($text, $restore($fragments));
    }

    #[Test]
    public function leaves_unclosed_tag_untouched_in_prose(): void
    {
        $text = 'Visit (link: /a text: incomplete';
        ['fragments' => $fragments, 'restore' => $restore] = KirbyText::split($text, ['link' => ['text']]);

        $this->assertSame([$text], $fragments);
        $this->assertSame($text, $restore($fragments));
    }

    #[Test]
    public function applies_independent_attr_translations_across_multiple_tags(): void
    {
        $text = 'Visit (link: /a text: site) or (email: x@y.com text: email us)';
        ['fragments' => $fragments, 'restore' => $restore] = KirbyText::split($text, [
            'link' => ['text'],
            'email' => ['text'],
        ]);

        $this->assertSame(['site', 'email us'], array_slice($fragments, 1));

        $translated = $fragments;
        $translated[1] = 'Seite';
        $translated[2] = 'schreib uns';

        $this->assertSame(
            'Visit (link: /a text: Seite) or (email: x@y.com text: schreib uns)',
            $restore($translated)
        );
    }

    #[Test]
    public function preserves_untranslated_attrs_when_only_one_attr_value_is_translated(): void
    {
        $text = '(image: hero.jpg alt: Sunset caption: Mountain view)';
        ['fragments' => $fragments, 'restore' => $restore] = KirbyText::split($text, ['image' => ['alt']]);

        $translated = $fragments;
        $translated[1] = 'Sonnenuntergang';

        $this->assertSame(
            '(image: hero.jpg alt: Sonnenuntergang caption: Mountain view)',
            $restore($translated)
        );
    }

    #[Test]
    public function restore_throws_when_translated_array_length_does_not_match_fragments(): void
    {
        $text = '(link: /a text: site)';
        ['fragments' => $fragments, 'restore' => $restore] = KirbyText::split($text, ['link' => ['text']]);

        $this->assertCount(2, $fragments);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected 2 translated fragments, got 1');

        $restore([$fragments[0]]);
    }

    #[Test]
    public function restore_drops_out_of_range_placeholder_indices_in_translated_prose(): void
    {
        $text = 'Hello (link: /a text: world)!';
        ['fragments' => $fragments, 'restore' => $restore] = KirbyText::split($text);

        $translated = [str_replace('Hello', 'Hallo <c9/>', $fragments[0])];

        $this->assertSame('Hallo  (link: /a text: world)!', $restore($translated));
    }

    #[Test]
    public function preserves_utf8_prose_and_attr_values(): void
    {
        $text = 'Привет (link: /a text: 世界) — café';
        ['fragments' => $fragments, 'restore' => $restore] = KirbyText::split($text, ['link' => ['text']]);

        $this->assertContains('世界', $fragments);
        $this->assertSame($text, $restore($fragments));

        $translated = $fragments;
        $translated[1] = '世界 (translated)';

        $this->assertSame(
            'Привет (link: /a text: 世界 (translated)) — café',
            $restore($translated)
        );
    }
}
