<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator;

use Closure;
use JohannSchopplich\ContentTranslator\Translation\TranslationLanguage;
use Kirby\Cms\App;
use Kirby\Exception\AuthException;
use Kirby\Exception\LogicException;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;

final class DeepL
{
    /** @deprecated v4 Will be removed. Use `DeepLLanguages::SUPPORTED_SOURCE_CODES`. */
    public const SUPPORTED_SOURCE_LANGUAGES = DeepLLanguages::SUPPORTED_SOURCE_CODES;
    /** @deprecated v4 Will be removed. Use `DeepLLanguages::SUPPORTED_TARGET_CODES`. */
    public const SUPPORTED_TARGET_LANGUAGES = DeepLLanguages::SUPPORTED_TARGET_CODES;
    public const API_URL_FREE = 'https://api-free.deepl.com';
    public const API_URL_PRO = 'https://api.deepl.com';

    private const MAX_RETRIES = 5;
    private const INITIAL_RETRY_DELAY_MS = 500;
    private const MAX_RETRY_DELAY_MS = 8000;

    /**
     * Markup that must survive translation: an HTML tag or comment. `<cN/>`
     * KirbyTag placeholders are tag-shaped, so the same pattern catches them.
     */
    private const MARKUP_PATTERN = '!</?[a-z][^>]*>|<\!--!i';

    /**
     * Options DeepL only reads alongside `tag_handling`. It rejects
     * `splitting_tags`, `non_splitting_tags` and `ignore_tags` outright without
     * it, so `buildRequestOptions` removes the whole set from a plain-text
     * request rather than just `tag_handling` itself.
     */
    private const TAG_HANDLING_OPTIONS = [
        'tag_handling',
        'tag_handling_version',
        'outline_detection',
        'splitting_tags',
        'non_splitting_tags',
        'ignore_tags'
    ];

    /** @see https://developers.deepl.com/docs/api-reference/translate */
    private readonly array $requestOptions;
    /** A configured `tag_handling` applies to every text and turns the per-text markup detection off */
    private readonly bool $hasConfiguredTagHandling;
    /** @var array<string, string> Target codes by Kirby language code */
    private readonly array $targetLanguageOverrides;
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

        $requestOptions = $kirby->option('johannschopplich.content-translator.DeepL.requestOptions', []);

        $this->apiKey = $apiKey;
        $this->hasConfiguredTagHandling = array_key_exists('tag_handling', $requestOptions);
        $this->requestOptions = A::merge(
            [
                // Default for markup-bearing text, such as the Writer field;
                // `buildRequestOptions` removes it for text witphout markup
                'tag_handling' => 'html',
                // HTML tag handling implies `split_sentences=nonewlines`, which
                // breaks markdown; `1` restores splitting on punctuation and
                // newlines, and is the DeepL default without tag handling
                'split_sentences' => '1'
            ],
            $requestOptions
        );
        $this->targetLanguageOverrides = $kirby->option('johannschopplich.content-translator.DeepL.targetLanguageOverrides', []);
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public function translate(string $text, string|TranslationLanguage $targetLanguage, string|TranslationLanguage|null $sourceLanguage = null): string
    {
        $result = $this->translateMany([$text], $targetLanguage, $sourceLanguage);
        return $result[0];
    }

    /**
     * @param array<int, string> $texts
     * @return array<int, string> One entry per input text, in input order
     */
    public function translateMany(array $texts, string|TranslationLanguage $targetLanguage, string|TranslationLanguage|null $sourceLanguage = null): array
    {
        if ($texts === []) {
            return [];
        }

        [$sourceLanguage, $targetLanguage] = $this->resolveLanguages($sourceLanguage, $targetLanguage);

        $texts = array_values($texts);

        if ($this->hasConfiguredTagHandling) {
            return $this->translateGroup($texts, $targetLanguage, $sourceLanguage, true);
        }

        // Splitting before chunking costs one extra request in total, whereas
        // splitting inside each chunk would cost one extra per chunk
        [$markupTexts, $plainTexts] = self::partitionByMarkup($texts);

        // Base for the merge, because each group only fills the indexes it owns
        return array_replace(
            $texts,
            $this->translateGroup($markupTexts, $targetLanguage, $sourceLanguage, true),
            $this->translateGroup($plainTexts, $targetLanguage, $sourceLanguage, false)
        );
    }

    /**
     * Each text keeps its original index, so the caller can splice translations
     * back into the input order.
     *
     * @param array<int, string> $texts
     * @return array{0: array<int, string>, 1: array<int, string>} [markup, plain]
     */
    private static function partitionByMarkup(array $texts): array
    {
        $markupTexts = [];
        $plainTexts = [];

        foreach ($texts as $index => $text) {
            if (preg_match(self::MARKUP_PATTERN, $text) === 1) {
                $markupTexts[$index] = $text;
            } else {
                $plainTexts[$index] = $text;
            }
        }

        return [$markupTexts, $plainTexts];
    }

    /**
     * @param array<int, string> $texts
     * @return array<int, string> Translations under the indexes of their sources
     */
    private function translateGroup(
        array $texts,
        string $targetLanguage,
        string|null $sourceLanguage,
        bool $shouldHandleTags
    ): array {
        $translations = [];

        // 50 texts per request is the DeepL API limit
        foreach (array_chunk($texts, 50, preserve_keys: true) as $chunk) {
            $response = $this->request(
                array_values($chunk),
                $targetLanguage,
                $sourceLanguage,
                $this->buildRequestOptions($chunk, $shouldHandleTags)
            );
            $responseTranslations = $response->json()['translations'] ?? [];

            // DeepL answers a batch one to one, so a mismatch means a truncated
            // or rewritten response, where matching translations back to their
            // texts by position would misalign them
            if (count($responseTranslations) !== count($chunk)) {
                throw new LogicException(
                    'DeepL returned ' . count($responseTranslations) .
                    ' translations for ' . count($chunk) . ' texts.'
                );
            }

            $indexes = array_keys($chunk);

            foreach ($responseTranslations as $position => $translation) {
                $translations[$indexes[$position]] = $translation['text'];
            }
        }

        return $translations;
    }

    /**
     * @return array{0: string|null, 1: string} [sourceLanguage, targetLanguage]
     */
    private function resolveLanguages(string|TranslationLanguage|null $sourceLanguage, string|TranslationLanguage $targetLanguage): array
    {
        $target = $this->asTranslationLanguage($targetLanguage);
        $source = $sourceLanguage === null || $sourceLanguage === ''
            ? null
            : $this->asTranslationLanguage($sourceLanguage);

        return [
            $source === null ? null : DeepLLanguages::resolveSource(
                $source->code,
                $source->locale,
                $this->targetLanguageOverrides
            ),
            DeepLLanguages::resolveTarget(
                $target->code,
                $target->locale,
                $this->targetLanguageOverrides
            ),
        ];
    }

    /**
     * Only a Kirby language carries the locale that tells `DE-CH` from `DE-DE`,
     * so a bare code has to be looked up. An unregistered one still resolves,
     * from the code alone.
     */
    private function asTranslationLanguage(string|TranslationLanguage $language): TranslationLanguage
    {
        return $language instanceof TranslationLanguage
            ? $language
            : TranslationLanguage::fromAnyCode($language);
    }

    /**
     * @param array<string> $texts
     */
    private function buildRequestOptions(array $texts, bool $shouldHandleTags): array
    {
        $options = $this->requestOptions;

        // Tag handling makes DeepL escape `<`, `>` and `'` in its output, which
        // corrupts text that has no markup to protect. No tag handling at all is
        // the DeepL default, which is exactly what plain text needs.
        // @see https://developers.deepl.com/docs/xml-and-html-handling/html
        if (!$shouldHandleTags) {
            $options = array_diff_key($options, array_flip(self::TAG_HANDLING_OPTIONS));
        }

        // `translate="no"` is only honoured under HTML tag handling, so a text
        // carrying one forces `html`, whatever the user configured
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
                    'data' => json_encode(
                        self::buildPayload($texts, $targetLanguage, $sourceLanguage, $requestOptions)
                    )
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

    /**
     * @param array<string> $texts
     * @param array<string, mixed> $requestOptions
     * @return array<string, mixed>
     */
    private static function buildPayload(array $texts, string $targetLanguage, string|null $sourceLanguage, array $requestOptions): array
    {
        $payload = $requestOptions;

        // Assigned rather than merged, because a merge lets a user-supplied
        // `text` survive next to the texts being translated and turn the JSON
        // array into an object DeepL rejects
        $payload['text'] = $texts;
        $payload['target_lang'] = $targetLanguage;

        // A configured `source_lang` must not stand in for one that failed to
        // resolve, or the text goes out labelled as an unrelated language
        if ($sourceLanguage === null) {
            unset($payload['source_lang']);
        } else {
            $payload['source_lang'] = $sourceLanguage;
        }

        return $payload;
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
}
