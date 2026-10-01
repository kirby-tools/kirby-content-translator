<?php

declare(strict_types = 1);

use Kirby\Cms\App;
use Kirby\Exception\PermissionException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class VariablesRouteTest extends ApiRouteTestCase
{
    /**
     * @param array<int|string, mixed> $source
     * @param array<int|string, mixed> $target
     * @param array<string, mixed> $props
     */
    private static function bootLanguagesApp(array $source, array $target = [], array $props = []): App
    {
        return self::bootApp([
            'options' => ['languages' => true],
            'languages' => [
                ['code' => 'en', 'name' => 'English', 'default' => true, 'translations' => $source],
                ['code' => 'de', 'name' => 'Deutsch', 'translations' => $target]
            ],
            ...$props
        ]);
    }

    /**
     * @param array<int|string, mixed> $source
     * @param array<int|string, mixed> $target
     */
    private function callVariablesRoute(array $source, array $target): array
    {
        $app = self::bootLanguagesApp($source, $target);

        // Round-trips through JSON, which turns the route's `(object)` casts back into arrays.
        return json_decode(json_encode($this->callRoute($app, '__content-translator__/variables')), true);
    }

    #[Test]
    public function sends_the_variables_of_every_language(): void
    {
        $response = $this->callVariablesRoute(
            source: ['cart.title' => 'Shopping cart', 'cart.count' => ['No items', '{count} items']],
            target: ['cart.title' => 'Warenkorb', 'legacy' => 'Alt']
        );

        $this->assertSame(['cart.title' => 'Shopping cart', 'cart.count' => ['No items', '{count} items']], $response['en']['variables']);
        $this->assertSame(['cart.title' => 'Warenkorb', 'legacy' => 'Alt'], $response['de']['variables']);
    }

    #[Test]
    public function refuses_a_user_without_access_to_the_languages_view(): void
    {
        $app = self::bootLanguagesApp(['cart.title' => 'Shopping cart'], props: [
            'users' => [
                ['id' => 'editor', 'email' => 'editor@example.com', 'role' => 'editor']
            ],
            'roles' => [
                ['name' => 'editor', 'permissions' => ['access' => ['languages' => false]]]
            ]
        ]);
        $app->impersonate('editor');

        $this->expectException(PermissionException::class);

        $this->callRoute($app, '__content-translator__/variables');
    }

    #[Test]
    public function sends_the_variables_to_a_user_without_languages_update(): void
    {
        $app = self::bootLanguagesApp(['cart.title' => 'Shopping cart'], props: [
            'users' => [
                ['id' => 'translator', 'email' => 'translator@example.com', 'role' => 'translator']
            ],
            'roles' => [
                ['name' => 'translator', 'permissions' => ['languages' => ['update' => false]]]
            ]
        ]);
        $app->impersonate('translator');

        $response = $this->callRoute($app, '__content-translator__/variables');

        $this->assertSame(['cart.title' => 'Shopping cart'], (array)$response['en']['variables']);
    }

    #[Test]
    public function sends_an_empty_or_list_shaped_set_of_variables_as_an_object(): void
    {
        $app = self::bootLanguagesApp(['Imprint']);

        $payload = json_encode($this->callRoute($app, '__content-translator__/variables'));

        $this->assertStringContainsString('"en":{"variables":{"0":"Imprint"}', $payload);
        $this->assertStringContainsString('"de":{"variables":{}', $payload);
    }

    #[Test]
    public function sends_the_default_language_keys_kirby_reserves_for_its_own_strings(): void
    {
        if (!method_exists(App::class, 'coreI18nStrings')) {
            $this->markTestSkipped('Kirby refuses a key that shadows one of its own strings since 5.6.');
        }

        $response = $this->callVariablesRoute(
            source: ['menu' => 'Menu', 'cart.title' => 'Shopping cart', 'save' => 'Save'],
            target: []
        );

        $this->assertSame(['menu', 'save'], $response['de']['reservedKeys']);
    }
}
