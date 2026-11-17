<?php

declare(strict_types = 1);

namespace KirbyTools\Licensing\Http;

use Exception;
use Kirby\Cms\App;
use Kirby\Exception\Exception as KirbyException;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;
use KirbyTools\Licensing\ActivationException;
use KirbyTools\Licensing\ActivationOutcome;

final class KirbyHttpClient implements HttpClientInterface
{
    public function request(string $url, array $options = []): array
    {
        try {
            $response = new Remote($url, A::merge([
                'headers' => [
                    'X-App-Url' => App::instance()->url()
                ]
            ], $options));
        } catch (KirbyException $e) {
            // A misconfigured `Remote` is a bug, not an unreachable server.
            throw $e;
        } catch (Exception $e) {
            // cURL could not complete the request, so there is no status.
            throw new ActivationException(ActivationOutcome::RequestFailed, ['status' => null], $e);
        }

        $status = $response->code();

        // The licensing API answers 401 for unknown credentials and license
        // keys alike; the status code is the contract, the body is not read.
        if ($status === 401) {
            throw new ActivationException(ActivationOutcome::InvalidCredentials);
        }

        if ($status === null || $status < 200 || $status >= 300) {
            throw new ActivationException(ActivationOutcome::RequestFailed, ['status' => $status]);
        }

        return $response->json();
    }
}
