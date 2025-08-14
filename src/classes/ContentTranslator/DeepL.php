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
    public const SUPPORTED_SOURCE_LANGUAGES = ['AR', 'BG', 'CS', 'DA', 'DE', 'EL', 'EN', 'ES', 'ET', 'FI', 'FR', 'HU', 'ID', 'IT', 'JA', 'KO', 'LT', 'LV', 'NB', 'NL', 'PL', 'PT', 'RO', 'RU', 'SK', 'SL', 'SV', 'TR', 'UK', 'ZH'];
    public const SUPPORTED_TARGET_LANGUAGES = ['AR', 'BG', 'CS', 'DA', 'DE', 'EL', 'EN', 'EN-GB', 'EN-US', 'ES', 'ET', 'FI', 'FR', 'HU', 'ID', 'IT', 'JA', 'KO', 'LT', 'LV', 'NB', 'NL', 'PL', 'PT', 'PT-BR', 'RO', 'RU', 'SK', 'SL', 'SV', 'TR', 'UK', 'ZH', 'ZH-HANS', 'ZH-HANT'];
    public const API_URL_FREE = 'https://api-free.deepl.com';
    public const API_URL_PRO = 'https://api.deepl.com';
    private const MAX_RETRIES = 3;
    private const INITIAL_RETRY_DELAY_MS = 1000;

    /** @see https://developers.deepl.com/docs/api-reference/translate */
    private array $requestOptions = [
        // Enable HTML tag handling by default for the Writer field
        'tag_handling' => 'html',
        // HTML tag handling sets `split_sentences=nonewlines`, which breaks
        // markdown content. Therefore, we need to set it back to `1` to
        // split sentences on punctuation and on newlines.
        'split_sentences' => '1'
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

            if (!in_array($sourceLanguage, self::SUPPORTED_SOURCE_LANGUAGES, true)) {
                $sourceLanguage = null;
            }
        }

        $targetLanguage = $this->resolveLanguageCode($targetLanguage);

        if (!in_array($targetLanguage, self::SUPPORTED_TARGET_LANGUAGES, true)) {
            throw new LogicException('The target language "' . $targetLanguage . '" is not supported by the DeepL API.');
        }

        // If a paragraph with the attribute `translate="no"` is present,
        // force HTML tag handling (if not enabled already)
        if (str_contains($text, '<span translate="no">')) {
            $this->requestOptions['tag_handling'] = 'html';
        }

        $response = $this->withRetry(function () use ($text, $sourceLanguage, $targetLanguage) {
            return Remote::request($this->resolveApiUrl() . '/v2/translate', [
                'method' => 'POST',
                'headers' => [
                    'Authorization' => 'DeepL-Auth-Key ' . $this->apiKey,
                    'Content-Type' => 'application/json'
                ],
                'data' => json_encode(A::merge(
                    [
                        'text' => [$text],
                        'source_lang' => $sourceLanguage,
                        'target_lang' => $targetLanguage,
                    ],
                    $this->requestOptions
                ))
            ]);
        });

        // See error message guide: https://support.deepl.com/hc/en-us/articles/9773964275868-DeepL-API-error-messages
        match ($response->code()) {
            400 => throw new LogicException('Bad request to DeepL API. Please check your parameters: ' . $response->content()),
            403 => throw new LogicException('Authorization failed. Have you set the correct DeepL API key? See https://kirby.tools/docs/content-translator/getting-started/installation for more information.'),
            404 => throw new LogicException('DeepL API endpoint not found. Please check the API URL.'),
            413 => throw new LogicException('DeepL API request size limit exceeded.'),
            429, 529 => throw new LogicException('Too many requests to the DeepL API. Please wait and resend your request.'),
            456 => throw new LogicException('DeepL API quota exceeded. The character limit has been reached.'),
            500 => throw new LogicException('DeepL API internal server error. Please try again later.'),
            503 => throw new LogicException('DeepL API service temporarily unavailable. Please try again later.'),
            504 => throw new LogicException('DeepL API gateway timeout. Please try again later.'),
            200 => null, // Do nothing for successful requests
            default => throw new LogicException('DeepL API request failed: ' . $response->content()),
        };

        $data = $response->json();
        return $data['translations'][0]['text'];
    }

    private function withRetry(callable $callback): mixed
    {
        $retries = 0;
        $delay = self::INITIAL_RETRY_DELAY_MS;

        while (true) {
            $response = $callback();

            // Handle retryable errors from the DeepL API
            $statusCode = $response->code();
            if (in_array($statusCode, [429, 500, 503, 504, 529], true)) {
                if ($retries >= self::MAX_RETRIES) {
                    throw new LogicException('DeepL API error ' . $statusCode . '. Maximum retry attempts reached.');
                }

                // Calculate sleep time with exponential backoff
                $sleepTime = (int)($delay * (1.5 ** $retries) / 1000);

                sleep($sleepTime);

                $retries++;
                continue;
            }

            return $response;
        }
    }

    private function resolveApiUrl(): string
    {
        $hasFreeAccount = str_ends_with($this->apiKey, ':fx');
        return $hasFreeAccount ? self::API_URL_FREE : self::API_URL_PRO;
    }

    private function resolveLanguageCode(string $code): string
    {
        $kirby = App::instance();
        $language = $kirby->languages()->findBy('code', $code);
        $fullLocale = $language?->locale(LC_ALL);

        if ($fullLocale) {
            $fullLocale = preg_replace('/\.utf-?8$/i', '', $fullLocale);

            // Get the base language and region if available
            if (str_contains($fullLocale, '_')) {
                [$baseCode, $regionCode] = array_map('strtoupper', explode('_', $fullLocale));

                // Create region-specific code in DeepL format (e.g., EN-GB)
                $regionSpecificCode = $baseCode . '-' . $regionCode;

                // Only use region-specific code if it's a supported target language
                if (in_array($regionSpecificCode, self::SUPPORTED_TARGET_LANGUAGES, true)) {
                    return $regionSpecificCode;
                }

                // If region-specific code is not supported, fall back to base language
                if (in_array($baseCode, self::SUPPORTED_TARGET_LANGUAGES, true)) {
                    return $baseCode;
                }
            }
        }

        return strtoupper($code);
    }
}
