<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\KirbyText;
use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers the deprecated v3 `KirbyText::translateText()` path only.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class KirbyTextLegacyTest extends TestCase
{
    protected function tearDown(): void
    {
        App::destroy();
    }

    #[Test]
    public function deprecated_translate_text_still_translates_a_configured_tag_attribute(): void
    {
        new App([
            'options' => [
                'debug' => true,
                'johannschopplich.content-translator' => [
                    'translateFn' => fn (string $text, string $toLanguageCode): string => "[$toLanguageCode]$text",
                ],
            ],
            'tags' => [
                'link' => [
                    'attr' => ['text'],
                    'html' => fn ($tag) => '<a href="' . $tag->link . '">' . ($tag->text ?? $tag->link) . '</a>',
                ],
            ],
        ]);

        $result = KirbyText::translateText(
            'Visit (link: https://example.com text: our website)!',
            'de',
            null,
            ['link' => ['text']],
        );

        $this->assertStringContainsString('[de]Visit', $result);
        $this->assertStringContainsString('[de]our website', $result);
        $this->assertStringContainsString('https://example.com', $result);
    }
}
