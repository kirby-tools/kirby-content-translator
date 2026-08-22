<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing\Http;

interface HttpClientInterface
{
    /**
     * @throws \Exception When the request fails or the response status is outside the 2xx range
     */
    public function request(string $url, array $options = []): array;
}
