<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing\Http;

use Kirby\Cms\App;
use Kirby\Exception\LogicException;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;

final class KirbyHttpClient implements HttpClientInterface
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

            // The status code becomes the exception key, which is what
            // `LicensePanel::activationFailure()` reports as `details.cause`.
            // TODO: Drop K4 compat in v1 – use named arguments once Kirby 5 is the floor.
            throw new LogicException([
                'fallback' => $message,
                'key' => (string)$response->code(),
                'translate' => false
            ]);
        }

        return $response->json();
    }
}
