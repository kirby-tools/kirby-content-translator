<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\KirbyText;
use Kirby\Cms\App;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class KirbyTextTest extends TestCase
{
    protected function setUp(): void
    {
        new App([
            'options' => [
                'debug' => true,
                'johannschopplich.content-translator' => [
                    'translateFn' => function (string $text, string $toLanguageCode): string {
                        return "[{$toLanguageCode}]{$text}";
                    }
                ]
            ],
            'tags' => [
                'link' => [
                    'attr' => ['text', 'title'],
                    'html' => fn ($tag) => '<a href="' . $tag->link . '">' . ($tag->text ?? $tag->link) . '</a>'
                ],
                'email' => [
                    'attr' => ['text'],
                    'html' => fn ($tag) => '<a href="mailto:' . $tag->email . '">' . ($tag->text ?? $tag->email) . '</a>'
                ]
            ]
        ]);
    }

    protected function tearDown(): void
    {
        App::destroy();
    }

    public function testTranslateEmptyString(): void
    {
        $this->assertSame('', KirbyText::translateText('', 'de'));
        $this->assertSame('', KirbyText::translateText('   ', 'de'));
    }

    public function testTranslatePlainTextWithoutTags(): void
    {
        $result = KirbyText::translateText('Hello world', 'de', null, []);
        $this->assertSame('[de]Hello world', $result);
    }

    public function testProtectsKirbyTagsWhenNoConfigProvided(): void
    {
        $text = 'Visit (link: https://example.com text: our website)!';
        $result = KirbyText::translateText($text, 'de', null, []);

        $this->assertStringContainsString('[de]Visit', $result);
        $this->assertStringNotContainsString('[de]our website', $result);
    }

    public function testTranslatesConfiguredTagAttributes(): void
    {
        $text = 'Visit (link: https://example.com text: our website title: Click here)!';
        $result = KirbyText::translateText($text, 'de', null, [
            'link' => ['text', 'title']
        ]);

        $this->assertStringContainsString('[de]Visit', $result);
        $this->assertStringContainsString('[de]our website', $result);
        $this->assertStringContainsString('[de]Click here', $result);
        $this->assertStringContainsString('https://example.com', $result);
    }

    public function testTranslatesOnlySpecifiedAttributes(): void
    {
        $text = 'Visit (link: https://example.com text: website title: Click)!';
        $result = KirbyText::translateText($text, 'de', null, [
            'link' => ['text'] // Only text, not title
        ]);

        $this->assertStringContainsString('[de]website', $result);
        $this->assertStringContainsString('title: Click', $result);
        $this->assertStringNotContainsString('[de]Click', $result);
    }

    public function testHandlesMultipleDifferentTags(): void
    {
        $text = 'Visit (link: https://example.com text: website) or (email: test@example.com text: email us)';
        $result = KirbyText::translateText($text, 'de', null, [
            'link' => ['text'],
            'email' => ['text']
        ]);

        $this->assertStringContainsString('[de]website', $result);
        $this->assertStringContainsString('[de]email us', $result);
        $this->assertStringContainsString('https://example.com', $result);
        $this->assertStringContainsString('test@example.com', $result);
    }

    public function testHandlesNestedParentheses(): void
    {
        $text = 'Visit (link: https://example.com text: our (awesome) site)!';
        $result = KirbyText::translateText($text, 'de', null, [
            'link' => ['text']
        ]);

        $this->assertStringContainsString('[de]our (awesome) site', $result);
    }

    public function testProtectsUnconfiguredTags(): void
    {
        $text = 'Visit (link: https://example.com text: site) or (email: test@example.com text: email)';
        $result = KirbyText::translateText($text, 'de', null, [
            'link' => ['text']
            // email is not configured
        ]);

        $this->assertStringContainsString('[de]site', $result);
        $this->assertStringNotContainsString('[de]email', $result);
    }

    public function testHandlesMalformedTagWithDebugOff(): void
    {
        App::destroy();
        new App([
            'options' => [
                'debug' => false,
                'johannschopplich.content-translator' => [
                    'translateFn' => fn ($text, $lang) => "[{$lang}]{$text}"
                ]
            ]
        ]);

        $text = 'Visit (link: https://example.com and more text';
        $result = KirbyText::translateText($text, 'de', null, ['link' => ['text']]);

        $this->assertIsString($result);
        $this->assertStringContainsString('[de]Visit', $result);
    }
}
