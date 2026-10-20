<?php

declare(strict_types = 1);

use Kirby\Cms\App;
use Kirby\Filesystem\Dir;
use Kirby\Toolkit\Str;
use PHPUnit\Framework\TestCase;

abstract class ApiRouteTestCase extends TestCase
{
    protected const PLUGIN_OPTION_KEY = 'johannschopplich.content-translator';

    protected function setUp(): void
    {
        Dir::make(static::indexRoot() . '/content');
    }

    protected function tearDown(): void
    {
        App::destroy();
        Dir::remove(static::indexRoot());
    }

    protected static function bootApp(array $props = []): App
    {
        $app = new App(array_replace_recursive([
            'roots' => ['index' => static::indexRoot()],
            'urls' => ['index' => 'https://example.com'],
            'options' => [
                static::PLUGIN_OPTION_KEY => [
                    'cache' => ['type' => 'memory']
                ]
            ]
        ], $props));

        $app->impersonate('kirby');

        return $app;
    }

    protected function callRoute(App $kirby, string $pattern, string $method = 'GET'): mixed
    {
        $api = require dirname(__DIR__) . '/src/extensions/api.php';
        $routes = $api['routes']($kirby);

        foreach ($routes as $route) {
            if (($route['pattern'] ?? '') === $pattern && ($route['method'] ?? 'GET') === $method) {
                return $route['action']();
            }
        }

        $this->fail("Route not found: {$method} {$pattern}");
    }

    protected static function indexRoot(): string
    {
        return __DIR__ . '/tmp/' . Str::kebab((new ReflectionClass(static::class))->getShortName());
    }
}
