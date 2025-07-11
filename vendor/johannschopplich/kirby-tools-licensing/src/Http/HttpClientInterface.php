<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing\Http;

interface HttpClientInterface
{
    /**
     * @throws \Exception when the curl request failed
     */
    public function request(string $url, array $options = []): array;
}
