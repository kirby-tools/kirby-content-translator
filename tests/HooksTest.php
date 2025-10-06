<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\DeepL;
use JohannSchopplich\ContentTranslator\Translator;
use Kirby\Cms\App;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class HooksTest extends TestCase
{
    public function testBeforeHookModifiesText(): void
    {
        $app = new App([
            'languages' => [
                ['code' => 'en', 'name' => 'English', 'default' => true],
                ['code' => 'de', 'name' => 'Deutsch']
            ],
            'blueprints' => [
                'pages/default' => [
                    'fields' => ['text' => ['type' => 'text', 'translate' => true]]
                ]
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'home',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => ['text' => 'Hello']]
                        ]
                    ]
                ]
            ],
            'hooks' => [
                'content-translator.translate:before' => fn ($text) => strtoupper($text)
            ],
            'options' => [
                'johannschopplich.content-translator' => [
                    'translateFn' => fn ($text, $lang) => "[$lang]$text"
                ]
            ]
        ]);

        $page = $app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $this->assertSame('[de]HELLO', $translator->model()->content('en')->get('text')->value());
    }

    public function testAfterHookModifiesTranslatedText(): void
    {
        $app = new App([
            'languages' => [
                ['code' => 'en', 'name' => 'English', 'default' => true],
                ['code' => 'de', 'name' => 'Deutsch']
            ],
            'blueprints' => [
                'pages/default' => [
                    'fields' => ['text' => ['type' => 'text', 'translate' => true]]
                ]
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'home',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => ['text' => 'Hello']]
                        ]
                    ]
                ]
            ],
            'hooks' => [
                'content-translator.translate:after' => fn ($text) => $text . ' (translated)'
            ],
            'options' => [
                'johannschopplich.content-translator' => [
                    'translateFn' => fn ($text, $lang) => "[$lang]$text"
                ]
            ]
        ]);

        $page = $app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $this->assertSame('[de]Hello (translated)', $translator->model()->content('en')->get('text')->value());
    }

    public function testBeforeAndAfterHooksTogether(): void
    {
        $app = new App([
            'languages' => [
                ['code' => 'en', 'name' => 'English', 'default' => true],
                ['code' => 'de', 'name' => 'Deutsch']
            ],
            'blueprints' => [
                'pages/default' => [
                    'fields' => ['text' => ['type' => 'text', 'translate' => true]]
                ]
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'home',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => ['text' => 'hello']]
                        ]
                    ]
                ]
            ],
            'hooks' => [
                'content-translator.translate:before' => fn ($text) => trim($text) . '!',
                'content-translator.translate:after' => fn ($text) => strtoupper($text)
            ],
            'options' => [
                'johannschopplich.content-translator' => [
                    'translateFn' => fn ($text, $lang) => "[$lang]$text"
                ]
            ]
        ]);

        $page = $app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de');

        $this->assertSame('[DE]HELLO!', $translator->model()->content('en')->get('text')->value());
    }

    public function testHooksReceiveCorrectParameters(): void
    {
        $beforeParams = [];
        $afterParams = [];

        $app = new App([
            'languages' => [
                ['code' => 'en', 'name' => 'English', 'default' => true],
                ['code' => 'de', 'name' => 'Deutsch']
            ],
            'blueprints' => [
                'pages/default' => [
                    'fields' => ['text' => ['type' => 'text', 'translate' => true]]
                ]
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'home',
                        'template' => 'default',
                        'translations' => [
                            ['code' => 'en', 'content' => ['text' => 'Hello']]
                        ]
                    ]
                ]
            ],
            'hooks' => [
                'content-translator.translate:before' => function ($text, $targetLanguage, $sourceLanguage, $type) use (&$beforeParams) {
                    $beforeParams = compact('text', 'targetLanguage', 'sourceLanguage', 'type');
                    return $text;
                },
                'content-translator.translate:after' => function ($text, $originalText, $targetLanguage, $sourceLanguage, $type) use (&$afterParams) {
                    $afterParams = compact('text', 'originalText', 'targetLanguage', 'sourceLanguage', 'type');
                    return $text;
                }
            ],
            'options' => [
                'johannschopplich.content-translator' => [
                    'translateFn' => fn ($text, $lang) => "[{$lang}]{$text}"
                ]
            ]
        ]);

        $page = $app->page('home');
        $translator = new Translator($page);
        $translator->translateContent('en', 'de', 'en');

        $this->assertSame('Hello', $beforeParams['text']);
        $this->assertSame('de', $beforeParams['targetLanguage']);
        $this->assertSame('en', $beforeParams['sourceLanguage']);
        $this->assertSame('text', $beforeParams['type']);

        $this->assertSame('[de]Hello', $afterParams['text']);
        $this->assertSame('Hello', $afterParams['originalText']);
        $this->assertSame('de', $afterParams['targetLanguage']);
        $this->assertSame('en', $afterParams['sourceLanguage']);
    }

    public function testHooksWorkWithStaticTranslateTextMethod(): void
    {
        $hookCalled = false;

        new App([
            'hooks' => [
                'content-translator.translate:before' => function ($text) use (&$hookCalled) {
                    $hookCalled = true;
                    return $text . ' modified';
                }
            ],
            'options' => [
                'johannschopplich.content-translator' => [
                    'translateFn' => fn ($text, $lang) => "[{$lang}]{$text}"
                ]
            ]
        ]);

        $result = Translator::translateText('Hello', 'de');

        $this->assertTrue($hookCalled);
        $this->assertSame('[de]Hello modified', $result);
    }

}
