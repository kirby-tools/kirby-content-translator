<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator;

use Closure;
use Kirby\Cms\App;
use Kirby\Exception\AuthException;
use Kirby\Exception\LogicException;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;

final class DeepL
{
    public const SUPPORTED_SOURCE_LANGUAGES = ['ACE', 'AF', 'AN', 'AR', 'AS', 'AY', 'AZ', 'BA', 'BE', 'BG', 'BHO', 'BN', 'BR', 'BS', 'CA', 'CEB', 'CKB', 'CS', 'CY', 'DA', 'DE', 'EL', 'EN', 'EO', 'ES', 'ET', 'EU', 'FA', 'FI', 'FR', 'GA', 'GL', 'GN', 'GOM', 'GU', 'HA', 'HE', 'HI', 'HR', 'HT', 'HU', 'HY', 'ID', 'IG', 'IS', 'IT', 'JA', 'JV', 'KA', 'KK', 'KMR', 'KO', 'KY', 'LA', 'LB', 'LMO', 'LN', 'LT', 'LV', 'MAI', 'MG', 'MI', 'MK', 'ML', 'MN', 'MR', 'MS', 'MT', 'MY', 'NB', 'NE', 'NL', 'OC', 'OM', 'PA', 'PAG', 'PAM', 'PL', 'PRS', 'PS', 'PT', 'QU', 'RO', 'RU', 'SA', 'SCN', 'SK', 'SL', 'SQ', 'SR', 'ST', 'SU', 'SV', 'SW', 'TA', 'TE', 'TG', 'TH', 'TK', 'TL', 'TN', 'TR', 'TS', 'TT', 'UK', 'UR', 'UZ', 'VI', 'WO', 'XH', 'YI', 'YUE', 'ZH', 'ZU'];
    public const API_URL_FREE = 'https://api-free.deepl.com';
    public const API_URL_PRO = 'https://api.deepl.com';

    private const MAX_RETRIES = 5;
    private const INITIAL_RETRY_DELAY_MS = 500;
    private const MAX_RETRY_DELAY_MS = 8000;

    /** @see https://developers.deepl.com/docs/api-reference/translate */
    private readonly array $requestOptions;
    /** @var array<string,string> Target codes by Kirby language code */
    private readonly array $targetLanguages;
    private readonly string|null $apiKey;
    private static DeepL|null $instance = null;

    public function __construct(
        private readonly Closure|null $remote = null,
        private readonly Closure|null $delay = null,
    ) {
        $kirby = App::instance();
        $apiKey = $kirby->option('johannschopplich.content-translator.DeepL.apiKey');

        if (!is_string($apiKey) || $apiKey === '') {
            throw new AuthException('Missing DeepL API key');
        }

        $this->apiKey = $apiKey;
        $this->requestOptions = A::merge(
            [
                // Enable HTML tag handling by default for the Writer field
                'tag_handling' => 'html',
                // HTML tag handling implies `split_sentences=nonewlines`, which
                // breaks markdown; `1` restores splitting on punctuation and newlines
                'split_sentences' => '1'
            ],
            $kirby->option('johannschopplich.content-translator.DeepL.requestOptions', [])
        );
        $this->targetLanguages = $kirby->option('johannschopplich.content-translator.DeepL.targetLanguages', []);
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public function translate(string $text, string $targetLanguage, string|null $sourceLanguage = null): string
    {
        $result = $this->translateMany([$text], $targetLanguage, $sourceLanguage);
        return $result[0];
    }

    /**
     * @param array<int,string> $texts
     * @return array<int,string>
     */
    public function translateMany(array $texts, string $targetLanguage, string|null $sourceLanguage = null): array
    {
        if ($texts === []) {
            return [];
        }

        [$sourceLanguage, $targetLanguage] = $this->validateLanguages($sourceLanguage, $targetLanguage);

        $results = [];

        // 50 texts per request is the DeepL API limit
        $chunks = array_chunk($texts, 50);

        foreach ($chunks as $chunk) {
            $requestOptions = $this->buildRequestOptions($chunk);

            $response = $this->request($chunk, $targetLanguage, $sourceLanguage, $requestOptions);
            $data = $response->json();

            foreach ($data['translations'] as $translation) {
                $results[] = $translation['text'];
            }
        }

        return $results;
    }

    /**
     * @return array{0: string|null, 1: string} [sourceLanguage, targetLanguage]
     */
    private function validateLanguages(string|null $sourceLanguage, string $targetLanguage): array
    {
        // An unsupported source language is dropped rather than rejected, because
        // DeepL detects it on its own; only the target has to be right
        if (!empty($sourceLanguage)) {
            $sourceLanguage = strtoupper($sourceLanguage);
            if (!in_array($sourceLanguage, self::SUPPORTED_SOURCE_LANGUAGES, true)) {
                $sourceLanguage = null;
            }
        }

        return [$sourceLanguage, $this->resolveTargetLanguage($targetLanguage)];
    }

    /**
     * @param array<string> $texts
     */
    private function buildRequestOptions(array $texts): array
    {
        $options = $this->requestOptions;

        // `translate="no"` is only honoured under HTML tag handling, so it has to
        // override whatever the user configured
        foreach ($texts as $text) {
            if (str_contains($text, '<span translate="no">')) {
                $options['tag_handling'] = 'html';
                break;
            }
        }

        return $options;
    }

    /**
     * @param array<string> $texts
     * @see https://support.deepl.com/hc/en-us/articles/9773964275868-DeepL-API-error-messages
     */
    private function request(array $texts, string $targetLanguage, string|null $sourceLanguage, array $requestOptions): mixed
    {
        $remote = $this->remote ?? static fn (string $url, array $options): Remote
            => Remote::request($url, $options);

        $response = $this->withRetry(
            fn () => $remote(
                $this->resolveApiUrl() . '/v2/translate',
                [
                    'method' => 'POST',
                    'headers' => [
                        'Authorization' => 'DeepL-Auth-Key ' . $this->apiKey,
                        'Content-Type' => 'application/json'
                    ],
                    // Merged last so user request options cannot silently replace the
                    // texts or the languages. `MERGE_REPLACE` is required because the
                    // default mode appends array values, which would prepend a
                    // user-supplied `text` to the texts being translated. An unresolved
                    // source language is filtered out rather than merged as null, which
                    // leaves a configured `source_lang` standing.
                    'data' => json_encode(A::merge(
                        $requestOptions,
                        array_filter([
                            'text' => $texts,
                            'source_lang' => $sourceLanguage,
                            'target_lang' => $targetLanguage,
                        ], static fn (mixed $value): bool => $value !== null),
                        A::MERGE_REPLACE
                    ))
                ]
            ),
            count($texts)
        );

        match ($response->code()) {
            400 => throw new LogicException('Bad request to DeepL API. Please check your parameters: ' . $response->content()),
            403 => throw new AuthException('Authorization failed. Have you set the correct DeepL API key? See https://kirby.tools/docs/content-translator/getting-started/installation for more information.'),
            404 => throw new LogicException('DeepL API endpoint not found. Please check the API URL.'),
            413 => throw new LogicException('DeepL API request size limit exceeded.'),
            429, 529 => throw new LogicException('Too many requests to the DeepL API. Please wait and resend your request.'),
            456 => throw new LogicException('DeepL API quota exceeded. The character limit has been reached.'),
            500 => throw new LogicException('DeepL API internal server error. Please try again later.'),
            503 => throw new LogicException('DeepL API service temporarily unavailable. Please try again later.'),
            504 => throw new LogicException('DeepL API gateway timeout. Please try again later.'),
            200 => null,
            default => throw new LogicException('DeepL API request failed: ' . $response->content()),
        };

        return $response;
    }

    private function withRetry(Closure $callback, int $batchSize = 1): mixed
    {
        $attempt = 0;
        while (true) {
            $response = $callback();
            $statusCode = $response->code();

            if (!in_array($statusCode, [429, 500, 503, 504, 529], true)) {
                return $response;
            }

            if ($attempt >= self::MAX_RETRIES) {
                $textPlural = $batchSize === 1 ? 'text' : 'texts';
                throw new LogicException(
                    "DeepL API error {$statusCode}. Maximum retry attempts reached after " . self::MAX_RETRIES .
                    " attempts (batch size: {$batchSize} {$textPlural})."
                );
            }

            // Exponential backoff with jitter
            $exponentDelay = (int)(self::INITIAL_RETRY_DELAY_MS * (2 ** $attempt));
            if ($exponentDelay > self::MAX_RETRY_DELAY_MS) {
                $exponentDelay = self::MAX_RETRY_DELAY_MS;
            }

            // Floor the jitter so a retry never fires immediately
            $minBackoff = (int)(self::INITIAL_RETRY_DELAY_MS / 2);

            $delay = $this->delay ?? static function (int $minMs, int $maxMs): void {
                usleep(random_int($minMs, $maxMs) * 1000);
            };
            $delay($minBackoff, $exponentDelay);

            $attempt++;
        }
    }

    private function resolveApiUrl(): string
    {
        $hasFreeAccount = str_ends_with($this->apiKey, ':fx');
        return $hasFreeAccount ? self::API_URL_FREE : self::API_URL_PRO;
    }

    private function resolveTargetLanguage(string $code): string
    {
        $locale = App::instance()->languages()->find($code)?->locale(LC_ALL);

        return DeepLTargetLanguage::resolve(
            $code,
            is_string($locale) ? $locale : null,
            $this->targetLanguages
        );
    }
}
