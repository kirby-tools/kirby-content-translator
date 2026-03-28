<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\DeepL;
use Kirby\Cms\App;
use Kirby\Exception\AuthException;
use Kirby\Exception\LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DeepLTest extends TestCase
{
    protected function setUp(): void
    {
        new App([
            'languages' => [
                [
                    'code' => 'en',
                    'name' => 'English',
                    'default' => true,
                    'locale' => 'en_US'
                ],
                [
                    'code' => 'de',
                    'name' => 'Deutsch',
                    'locale' => 'de_DE'
                ]
            ],
            'options' => [
                'debug' => true,
                'johannschopplich.content-translator' => [
                    'DeepL.apiKey' => 'test-key:fx'
                ]
            ]
        ]);
    }

    protected function tearDown(): void
    {
        App::destroy();
    }

    #[Test]
    public function constructor_throws_exception_without_api_key(): void
    {
        new App([
            'options' => [
                'johannschopplich.content-translator' => []
            ]
        ]);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Missing DeepL API key');

        new DeepL();
    }

    #[Test]
    public function instance_returns_singleton(): void
    {
        $instance1 = DeepL::instance();
        $instance2 = DeepL::instance();

        $this->assertSame($instance1, $instance2);
    }

    #[Test]
    public function translate_many_with_empty_array(): void
    {
        $deepL = DeepL::instance();
        $result = $deepL->translateMany([], 'de');

        $this->assertSame([], $result);
    }

    #[Test]
    public function translate_many_throws_exception_for_unsupported_target_language(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('not supported by the DeepL API');

        $deepL = DeepL::instance();
        $deepL->translateMany(['Hello'], 'invalid');
    }

    #[Test]
    public function validates_and_normalizes_languages(): void
    {
        // Test with EN-GB locale (should resolve to EN-GB)
        new App([
            'languages' => [
                [
                    'code' => 'en',
                    'name' => 'English',
                    'default' => true,
                    'locale' => 'en_GB'
                ]
            ],
            'options' => [
                'johannschopplich.content-translator' => [
                    'DeepL.apiKey' => 'test-key:fx'
                ]
            ]
        ]);

        $deepL = new DeepL();

        $reflection = new \ReflectionClass($deepL);
        $method = $reflection->getMethod('validateLanguages');
        $method->setAccessible(true);

        [$source, $target] = $method->invoke($deepL, 'en', 'en');

        $this->assertSame('EN', $source);
        $this->assertSame('EN-GB', $target);
    }

    #[Test]
    public function ignores_unsupported_source_language(): void
    {
        $deepL = DeepL::instance();

        $reflection = new \ReflectionClass($deepL);
        $method = $reflection->getMethod('validateLanguages');
        $method->setAccessible(true);

        [$source, $target] = $method->invoke($deepL, 'invalid', 'de');

        $this->assertNull($source);
        $this->assertSame('DE', $target);
    }

    #[Test]
    public function resolves_api_url_for_free_account(): void
    {
        new App([
            'options' => [
                'johannschopplich.content-translator' => [
                    'DeepL.apiKey' => 'test-key:fx'
                ]
            ]
        ]);

        $deepL = new DeepL();

        $reflection = new \ReflectionClass($deepL);
        $method = $reflection->getMethod('resolveApiUrl');
        $method->setAccessible(true);

        $url = $method->invoke($deepL);

        $this->assertSame('https://api-free.deepl.com', $url);
    }

    #[Test]
    public function resolves_api_url_for_pro_account(): void
    {
        new App([
            'options' => [
                'johannschopplich.content-translator' => [
                    'DeepL.apiKey' => 'test-pro-key'
                ]
            ]
        ]);

        $deepL = new DeepL();

        $reflection = new \ReflectionClass($deepL);
        $method = $reflection->getMethod('resolveApiUrl');
        $method->setAccessible(true);

        $url = $method->invoke($deepL);

        $this->assertSame('https://api.deepl.com', $url);
    }

    #[Test]
    public function builds_request_options_with_no_translate_tags(): void
    {
        $deepL = DeepL::instance();

        $reflection = new \ReflectionClass($deepL);
        $method = $reflection->getMethod('buildRequestOptions');
        $method->setAccessible(true);

        // Without <span translate="no">, should return base options
        $options = $method->invoke($deepL, ['Normal text without tags']);
        $this->assertSame('html', $options['tag_handling']);

        // With <span translate="no">, should ensure tag_handling is 'html'
        $options = $method->invoke($deepL, ['Keep <span translate="no">Brand</span> unchanged']);
        $this->assertSame('html', $options['tag_handling']);

        $requestOptions = $reflection->getProperty('requestOptions');
        $requestOptions->setAccessible(true);
        $originalOptions = $requestOptions->getValue($deepL);
        $this->assertSame('html', $originalOptions['tag_handling'], 'Original options should remain unchanged');
    }

    #[Test]
    public function translate_delegates_to_translate_many(): void
    {
        $mockDeepL = $this->getMockBuilder(DeepL::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['translateMany'])
            ->getMock();

        $mockDeepL->expects($this->once())
            ->method('translateMany')
            ->with(['Hello World'], 'de', null)
            ->willReturn(['Hallo Welt']);

        $result = $mockDeepL->translate('Hello World', 'de');

        $this->assertSame('Hallo Welt', $result);
    }

    #[Test]
    public function resolve_language_code_with_regional_locale(): void
    {
        new App([
            'languages' => [
                [
                    'code' => 'en',
                    'name' => 'English',
                    'default' => true,
                    'locale' => 'en_US'
                ],
                [
                    'code' => 'pt',
                    'name' => 'Portuguese',
                    'locale' => 'pt_BR'
                ]
            ],
            'options' => [
                'johannschopplich.content-translator' => [
                    'DeepL.apiKey' => 'test-key:fx'
                ]
            ]
        ]);

        $deepL = new DeepL();

        $reflection = new \ReflectionClass($deepL);
        $method = $reflection->getMethod('resolveLanguageCode');
        $method->setAccessible(true);

        // EN-US is supported
        $result = $method->invoke($deepL, 'en');
        $this->assertSame('EN-US', $result);

        // PT-BR is supported
        $result = $method->invoke($deepL, 'pt');
        $this->assertSame('PT-BR', $result);
    }

    #[Test]
    public function supports_all_languages(): void
    {
        $deepL = DeepL::instance();
        $supportedTargetLanguages = ['DE', 'EN', 'FR', 'ES', 'IT', 'JA', 'NL', 'PL', 'PT', 'RU', 'ZH'];

        foreach ($supportedTargetLanguages as $lang) {
            try {
                // Just validate - won't make actual HTTP call since array is empty
                $deepL->translateMany([], $lang);
                $this->assertTrue(true);
            } catch (LogicException $e) {
                $this->fail("Language {$lang} should be supported but threw exception: " . $e->getMessage());
            }
        }
    }
}
