<?php

declare(strict_types = 1);

namespace KirbyTools\Licensing\Http;

interface HttpClientInterface
{
    /**
     * @throws \KirbyTools\Licensing\ActivationException When the request fails or the response status is outside the 2xx range
     */
    public function request(string $url, array $options = []): array;
}
