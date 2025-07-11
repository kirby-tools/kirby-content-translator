<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing\Http;

use Kirby\Cms\App;
use Kirby\Exception\LogicException;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;

/**
 * HTTP client implementation using Kirby's `Remote` class.
 */
class KirbyHttpClient implements HttpClientInterface
{
    public function request(string $url, array $options = []): array
    {
        $response = new Remote($url, A::merge([
            'headers' => [
                'X-App-Url' => App::instance()->url()
            ]
        ], $options));

        if ($response->code() < 200 || $response->code() >= 300) {
            $message = $response->json()['message'] ?? 'Request failed';
            throw new LogicException($message, (string)$response->code());
        }

        return $response->json();
    }
}
