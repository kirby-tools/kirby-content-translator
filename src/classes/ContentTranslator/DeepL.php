<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator;

use Kirby\Cms\App;
use Kirby\Exception\AuthException;
use Kirby\Exception\LogicException;
use Kirby\Http\Remote;

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

    private string|null $apiKey;
    private static DeepL|null $instance;

    public function __construct()
    {
        $kirby = App::instance();
        $authKey = $kirby->option('johannschopplich.content-translator.DeepL.apiKey');

        if (empty($authKey)) {
            throw new AuthException('Missing DeepL API key');
        }

        $this->apiKey = $authKey;
    }

    public function instance(): DeepL
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
            'data' => json_encode([
                'text' => [$text],
                'source_lang' => $sourceLanguage,
                'target_lang' => strtoupper($targetLanguage)
            ])
        ]);

        $data = $response->json();

        if ($response->code() !== 200 || !isset($data['translations'][0]['text'])) {
            throw new LogicException('Failed to translate text');
        }

        return $data['translations'][0]['text'];
    }

    private function resolveApiUrl(): string
    {
        $hasFreeAccount = str_ends_with($this->apiKey, ':fx');
        return $hasFreeAccount ? static::API_URL_FREE : static::API_URL_PRO;
    }
}
