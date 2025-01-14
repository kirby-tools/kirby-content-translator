<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator;

use Kirby\Cms\App;
use Kirby\Exception\AuthException;
use Kirby\Exception\LogicException;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;

final class DeepL
{
    public const SUPPORTED_SOURCE_LANGUAGES = [
        'AR',
        'BG',
        'CS',
        'DA',
        'DE',
        'EL',
        'EN',
        'ES',
        'ET',
        'FI',
        'FR',
        'HU',
        'ID',
        'IT',
        'JA',
        'KO',
        'LT',
        'LV',
        'NB',
        'NL',
        'PL',
        'PT',
        'RO',
        'RU',
        'SK',
        'SL',
        'SV',
        'TR',
        'UK',
        'ZH'
    ];
    public const API_URL_FREE = 'https://api-free.deepl.com';
    public const API_URL_PRO = 'https://api.deepl.com';

    /** @see https://developers.deepl.com/docs/api-reference/translate */
    private array $requestOptions = [
        // Enable HTML tag handling by default for the Writer field
        'tag_handling' => 'html',
        // Ignore `<deepl_ignore>` tags by default – this only works with the `tag_handling` option set to `xml`!
        // See: https://developers.deepl.com/docs/xml-and-html-handling/xml#ignored-tags
        // 'ignore_tags' => ['deepl_ignore']
    ];
    private string|null $apiKey;
    private static DeepL|null $instance;

    public function __construct()
    {
        $kirby = App::instance();
        $apiKey = $kirby->option('johannschopplich.content-translator.DeepL.apiKey');

        if (empty($apiKey)) {
            throw new AuthException('Missing DeepL API key');
        }

        $this->apiKey = $apiKey;
        $this->requestOptions = A::merge(
            $this->requestOptions,
            $kirby->option('johannschopplich.content-translator.deepl.requestOptions', [])
        );
    }

    public static function instance(): DeepL
    {
        return static::$instance ??= new static();
    }

    public function translate(string $text, string $targetLanguage, string|null $sourceLanguage = null): string
    {
        if (!empty($sourceLanguage)) {
            $sourceLanguage = strtoupper($sourceLanguage);

            if (!in_array($sourceLanguage, static::SUPPORTED_SOURCE_LANGUAGES, true)) {
                $sourceLanguage = null;
            }
        }

        $response = Remote::request($this->resolveApiUrl() . '/v2/translate', [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'DeepL-Auth-Key ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ],
            'data' => json_encode(A::merge(
                [
                    'text' => [$text],
                    'source_lang' => $sourceLanguage,
                    'target_lang' => strtoupper($targetLanguage),
                ],
                $this->requestOptions
            ))
        ]);

        // See error message guide: https://support.deepl.com/hc/en-us/articles/9773964275868-DeepL-API-error-messages
        match ($response->code()) {
            403 => throw new LogicException('Authorization failed. Have you set the correct DeepL API key? See https://kirby.tools/docs/content-translator#step-2-configure-deepl for more information.'),
            413 => throw new LogicException('DeepL API request size limit exceeded.'),
            429 => throw new LogicException('Too many requests to the DeepL API. Please wait and resend your request.'),
            456 => throw new LogicException('DeepL API quota exceeded. The character limit has been reached.'),
            200 => null, // Do nothing for successful requests
            default => throw new LogicException('DeepL API request failed: ' . $response->content()),
        };

        $data = $response->json();
        return $data['translations'][0]['text'];
    }

    private function resolveApiUrl(): string
    {
        $hasFreeAccount = str_ends_with($this->apiKey, ':fx');
        return $hasFreeAccount ? static::API_URL_FREE : static::API_URL_PRO;
    }
}
