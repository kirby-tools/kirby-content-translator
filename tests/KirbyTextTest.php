<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\KirbyText;
use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class KirbyTextTest extends TestCase
{
    protected function tearDown(): void
    {
        App::destroy();
    }

    private function appWithKirbyTagConfig(bool $debug = true): App
    {
        return new App([
            'options' => [
                'debug' => $debug,
                'johannschopplich.content-translator' => [
                    'translateFn' => fn (string $text, string $toLanguageCode): string => "[$toLanguageCode]$text",
                ],
            ],
            'tags' => [
                'link' => [
                    'attr' => ['text', 'title'],
                    'html' => fn ($tag) => '<a href="' . $tag->link . '">' . ($tag->text ?? $tag->link) . '</a>',
                ],
                'email' => [
                    'attr' => ['text'],
                    'html' => fn ($tag) => '<a href="mailto:' . $tag->email . '">' . ($tag->text ?? $tag->email) . '</a>',
                ],
            ],
        ]);
    }

    /** @return array<string, array{0: string}> */
    public static function blankInputs(): array
    {
        return [
            'empty string' => [''],
            'spaces only' => ['   '],
        ];
    }

    #[Test]
    #[DataProvider('blankInputs')]
    public function returns_empty_string_for_blank_input(string $text): void
    {
        $this->appWithKirbyTagConfig();
        $this->assertSame('', KirbyText::translateText($text, 'de'));
    }

    #[Test]
    public function translates_plain_text_without_tags(): void
    {
        $this->appWithKirbyTagConfig();
        $this->assertSame('[de]Hello world', KirbyText::translateText('Hello world', 'de', null, []));
    }

    #[Test]
    public function protects_kirby_tags_when_kirby_tag_config_is_empty(): void
    {
        $this->appWithKirbyTagConfig();
        $result = KirbyText::translateText(
            'Visit (link: https://example.com text: our website)!',
            'de',
            null,
            []
        );

        $this->assertStringContainsString('[de]Visit', $result);
        $this->assertStringNotContainsString('[de]our website', $result);
    }

    #[Test]
    public function translates_configured_tag_attributes(): void
    {
        $this->appWithKirbyTagConfig();
        $result = KirbyText::translateText(
            'Visit (link: https://example.com text: our website title: Click here)!',
            'de',
            null,
            ['link' => ['text', 'title']]
        );

        $this->assertStringContainsString('[de]Visit', $result);
        $this->assertStringContainsString('[de]our website', $result);
        $this->assertStringContainsString('[de]Click here', $result);
        $this->assertStringContainsString('https://example.com', $result);
    }

    #[Test]
    public function translates_only_specified_attributes(): void
    {
        $this->appWithKirbyTagConfig();
        $result = KirbyText::translateText(
            'Visit (link: https://example.com text: website title: Click)!',
            'de',
            null,
            ['link' => ['text']]
        );

        $this->assertStringContainsString('[de]website', $result);
        $this->assertStringContainsString('title: Click', $result);
        $this->assertStringNotContainsString('[de]Click', $result);
    }

    #[Test]
    public function handles_multiple_different_tags(): void
    {
        $this->appWithKirbyTagConfig();
        $result = KirbyText::translateText(
            'Visit (link: https://example.com text: website) or (email: test@example.com text: email us)',
            'de',
            null,
            ['link' => ['text'], 'email' => ['text']]
        );

        $this->assertStringContainsString('[de]website', $result);
        $this->assertStringContainsString('[de]email us', $result);
        $this->assertStringContainsString('https://example.com', $result);
        $this->assertStringContainsString('test@example.com', $result);
    }

    #[Test]
    public function handles_nested_parentheses(): void
    {
        $this->appWithKirbyTagConfig();
        $result = KirbyText::translateText(
            'Visit (link: https://example.com text: our (awesome) site)!',
            'de',
            null,
            ['link' => ['text']]
        );

        $this->assertStringContainsString('[de]our (awesome) site', $result);
    }

    #[Test]
    public function protects_unconfigured_tags(): void
    {
        $this->appWithKirbyTagConfig();
        $result = KirbyText::translateText(
            'Visit (link: https://example.com text: site) or (email: test@example.com text: email)',
            'de',
            null,
            ['link' => ['text']]
        );

        $this->assertStringContainsString('[de]site', $result);
        $this->assertStringNotContainsString('[de]email', $result);
    }

    #[Test]
    public function handles_malformed_tag_when_debug_disabled(): void
    {
        $this->appWithKirbyTagConfig(debug: false);
        $result = KirbyText::translateText(
            'Visit (link: https://example.com and more text',
            'de',
            null,
            ['link' => ['text']]
        );

        $this->assertIsString($result);
        $this->assertStringContainsString('[de]Visit', $result);
    }
}
