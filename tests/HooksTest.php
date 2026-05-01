<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\Translator;
use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class HooksTest extends TestCase
{
    protected function tearDown(): void
    {
        App::destroy();
    }

    private static function pluginOptions(): array
    {
        return [
            'johannschopplich.content-translator' => [
                'translateFn' => fn (string $text, string $lang) => "[$lang]$text",
            ],
        ];
    }

    private function appWithHomePage(array $hooks = [], string $homeText = 'Hello'): App
    {
        return new App([
            'languages' => [
                ['code' => 'en', 'name' => 'English', 'default' => true],
                ['code' => 'de', 'name' => 'Deutsch'],
            ],
            'blueprints' => [
                'pages/default' => [
                    'fields' => ['text' => ['type' => 'text', 'translate' => true]],
                ],
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'home',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => ['text' => $homeText]],
                        ],
                    ],
                ],
            ],
            'hooks' => $hooks,
            'options' => self::pluginOptions(),
        ]);
    }

    #[Test]
    public function before_hook_modifies_text_before_translation(): void
    {
        $app = $this->appWithHomePage([
            'content-translator.translate:before' => fn ($text) => strtoupper($text),
        ]);

        $translator = new Translator($app->page('home'));
        $translator->translateContent('en', 'de');

        $this->assertSame('[de]HELLO', $translator->model()->content('en')->get('text')->value());
    }

    #[Test]
    public function after_hook_modifies_translated_text(): void
    {
        $app = $this->appWithHomePage([
            'content-translator.translate:after' => fn ($text) => $text . ' (translated)',
        ]);

        $translator = new Translator($app->page('home'));
        $translator->translateContent('en', 'de');

        $this->assertSame('[de]Hello (translated)', $translator->model()->content('en')->get('text')->value());
    }

    #[Test]
    public function applies_both_before_and_after_hooks(): void
    {
        $app = $this->appWithHomePage(
            hooks: [
                'content-translator.translate:before' => fn ($text) => trim($text) . '!',
                'content-translator.translate:after' => fn ($text) => strtoupper($text),
            ],
            homeText: 'hello',
        );

        $translator = new Translator($app->page('home'));
        $translator->translateContent('en', 'de');

        $this->assertSame('[DE]HELLO!', $translator->model()->content('en')->get('text')->value());
    }

    #[Test]
    public function hooks_receive_full_payload(): void
    {
        $beforeParams = [];
        $afterParams = [];

        $app = $this->appWithHomePage([
            'content-translator.translate:before' => function ($text, $targetLanguage, $sourceLanguage, $type) use (&$beforeParams) {
                $beforeParams = compact('text', 'targetLanguage', 'sourceLanguage', 'type');
                return $text;
            },
            'content-translator.translate:after' => function ($text, $originalText, $targetLanguage, $sourceLanguage, $type) use (&$afterParams) {
                $afterParams = compact('text', 'originalText', 'targetLanguage', 'sourceLanguage', 'type');
                return $text;
            },
        ]);

        $translator = new Translator($app->page('home'));
        $translator->translateContent('en', 'de', 'en');

        $this->assertSame([
            'text' => 'Hello',
            'targetLanguage' => 'de',
            'sourceLanguage' => 'en',
            'type' => 'text',
        ], $beforeParams);

        $this->assertSame([
            'text' => '[de]Hello',
            'originalText' => 'Hello',
            'targetLanguage' => 'de',
            'sourceLanguage' => 'en',
            'type' => 'text',
        ], $afterParams);
    }

    #[Test]
    public function static_translate_text_invokes_hooks(): void
    {
        $hookCalled = false;

        new App([
            'hooks' => [
                'content-translator.translate:before' => function ($text) use (&$hookCalled) {
                    $hookCalled = true;
                    return $text . ' modified';
                },
            ],
            'options' => self::pluginOptions(),
        ]);

        $result = Translator::translateText('Hello', 'de');

        $this->assertTrue($hookCalled);
        $this->assertSame('[de]Hello modified', $result);
    }
}
