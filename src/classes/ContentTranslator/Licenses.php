<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator;

use Kirby\Cms\App;
use Kirby\Data\Json;
use Kirby\Exception\LogicException;
use Kirby\Http\Remote;
use Kirby\Toolkit\Str;
use Throwable;

class Licenses
{
    public const PACKAGE_NAME = 'johannschopplich/kirby-content-translator';
    private const LICENSE_FILE = '.kirby-tools-licenses';
    private const LICENSE_PATTERN = '!^KT\d-\w+-\w+$!';
    private const API_URL = 'https://repo.kirby.tools/api';

    public function __construct(
        private array $licenses,
    ) {
    }

    public static function read(): static
    {
        try {
            $licenses = Json::read(App::instance()->root('config') . '/' . static::LICENSE_FILE);
        } catch (Throwable) {
            $licenses = [];
        }

        $instance = new static($licenses);

        // Run migration for private Composer repository
        // $instance->migration();

        return $instance;
    }

    public function register(string $email, string|int $orderId): void
    {
        $response = $this->request('licenses', [
            'method' => 'POST',
            'data' => [
                'email' => $email,
                'orderId' => $orderId
            ]
        ]);

        ['packageName' => $packageName, 'licenseKey' => $key] = $response;

        if ($packageName !== static::PACKAGE_NAME) {
            throw new LogicException('License key not valid for this plugin');
        }

        $this->update($packageName, $key);
    }

    public function update(string $packageName, string $licenseKey): void
    {
        $this->licenses[$packageName] = $licenseKey;
        Json::write(App::instance()->root('config') . '/' . static::LICENSE_FILE, $this->licenses);
    }

    public function isRegistered(string $packageName): bool
    {
        $licenseKey = $this->licenses[$packageName] ?? null;
        return $licenseKey !== null && $this->isValid($licenseKey);
    }

    public function isValid(string $licenseKey): bool
    {
        return preg_match(static::LICENSE_PATTERN, $licenseKey) === 1;
    }

    private function migration(): void
    {
        $authFile = App::instance()->root('config') . '/auth.json';

        try {
            $auth = Json::read($authFile);
            $collection = $auth['bearer']['repo.kirby.tools'] ?? null;

            if (empty($collection)) {
                return;
            }

            $licenseKeys = array_values($this->licenses);

            // Get package name for licenses and update them
            foreach (Str::split($collection, ',') as $licenseKey) {
                if (!$this->isValid($licenseKey)) {
                    continue;
                }

                if (in_array($licenseKey, $licenseKeys)) {
                    continue;
                }

                $response = $this->request('licenses/' . $licenseKey . '/package');
                $this->update($response['packageName'], $licenseKey);
            }
        } catch (Throwable) {
            // Ignore
        }
    }

    private function request(string $path, array $options = []): array
    {
        $response = new Remote(static::API_URL . '/' . $path, $options);

        if ($response->code() !== 200) {
            $message = $response->json()['message'] ?? 'Request failed';
            throw new LogicException($message, $response->code());
        }

        return $response->json();
    }
}
