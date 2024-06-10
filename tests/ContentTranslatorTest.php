<?php

declare(strict_types = 1);

require_once __DIR__ . '/../vendor/autoload.php';

use JohannSchopplich\ContentTranslator\Translator;
use Kirby\Cms\App;
use PHPUnit\Framework\TestCase;

final class ContentTranslatorTest extends TestCase
{
    protected function tearDown(): void
    {
        restore_error_handler();
        restore_exception_handler();
    }

    public function app($language = null): App
    {
        $app = new App([
            'languages' => [
                [
                    'code' => 'en',
                    'name' => 'English',
                    'default' => true
                ],
                [
                    'code' => 'de',
                    'name' => 'Deutsch'
                ]
            ],
            'blueprints' => [
                'pages/default' => [
                    'fields' => [
                        'title' => [
                            'type' => 'text'
                        ],
                        'untranslated' => [
                            'type' => 'text'
                        ]
                    ]
                ]
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'home',
                        'translations' => [
                            [
                                'code' => 'en',
                                'content' => [
                                    'title' => 'Home',
                                    'untranslated' => 'Untranslated'
                                ]
                            ],
                            [
                                'code' => 'de',
                                'slug' => 'start',
                                'content' => [
                                    'title' => 'Startseite'
                                ]
                            ],
                        ],
                    ]
                ],
            ],
            'options' => [
                'debug' => true,
                'johannschopplich.content-translator' => [
                    'translateFn' => function (string $text, string $targetLanguageCode, string|null $sourceLanguageCode): string {
                        return md5($targetLanguageCode . $text);
                    }
                ]
            ]
        ]);

        if ($language !== null) {
            $app->setCurrentLanguage($language);
            $app->setCurrentTranslation($language);
        }

        return $app;
    }

    public function testTranslateTextUsesProvidedTranslateFunction(): void
    {
        $this->app('en');
        $translatedText = Translator::translateText('hello', 'de');
        $this->assertSame(md5('de' . 'hello'), $translatedText);
    }

    public function testSynchronizeContent(): void
    {
        $page = $this->app('de')->page('home')->clone();

        $translator = new Translator($page);
        $translator->synchronizeContent('en', 'de');
        $page = $translator->model();

        $this->assertSame('Untranslated', $page->content('de')->get('untranslated')->value());
    }

    public function testTranslateContent(): void
    {
        $page = $this->app('en')->page('home')->clone();

        $translator = new Translator($page);
        $translator->translateContent('en', 'de');
        $page = $translator->model();

        $this->assertSame(md5('de' . 'Untranslated'), $page->content('de')->get('untranslated')->value());
    }

    public function testTranslateTitle(): void
    {
        $page = $this->app('en')->page('home')->clone();

        $translator = new Translator($page);
        $translator->translateTitle('en', 'de');
        $page = $translator->model();

        $this->assertSame(md5('de' . 'Home'), $page->title()->value());
    }
}
