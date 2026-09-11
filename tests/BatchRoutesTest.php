<?php

declare(strict_types = 1);

use Kirby\Cms\App;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Filesystem\Dir;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class BatchRoutesTest extends ApiRouteTestCase
{
    private string $root;

    protected function setUp(): void
    {
        // Kirby's memory storage drops a language's content when a later
        // title or slug change clones the page, so the page lives on disk.
        $this->root = __DIR__ . '/tmp';
        mkdir($this->root . '/content/1_about', recursive: true);
        file_put_contents(
            $this->root . '/content/1_about/default.en.txt',
            "Title: About\n\n----\n\nTeaser: Short\n\n----\n\nAuthor: \n\n----\n\nText: Hello\n"
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Dir::remove($this->root);
    }

    /**
     * @param array<string, mixed> $request
     */
    private function app(array $request, bool $isLockingEnabled = true, string $userId = 'editor'): App
    {
        $app = new App([
            'roots' => ['index' => $this->root],
            'options' => ['content.locking' => $isLockingEnabled],
            'languages' => [
                ['code' => 'en', 'name' => 'English', 'default' => true],
                ['code' => 'de', 'name' => 'Deutsch', 'locale' => 'de_DE'],
                ['code' => 'fr', 'name' => 'Français']
            ],
            'users' => [
                ['id' => 'editor', 'email' => 'editor@example.com', 'name' => 'Editor', 'role' => 'admin'],
                ['id' => 'colleague', 'email' => 'colleague@example.com', 'name' => 'Colleague', 'role' => 'admin'],
                ['id' => 'reader', 'email' => 'reader@example.com', 'role' => 'reader'],
                ['id' => 'writer', 'email' => 'writer@example.com', 'role' => 'writer']
            ],
            'roles' => [
                ['name' => 'admin'],
                ['name' => 'reader', 'permissions' => ['pages' => ['update' => false]]],
                ['name' => 'writer', 'permissions' => ['pages' => ['changeTitle' => false]]]
            ],
            'blueprints' => [
                'pages/default' => [
                    'fields' => [
                        'teaser' => ['type' => 'text', 'maxlength' => 10],
                        'author' => ['type' => 'text', 'required' => true],
                        'text' => ['type' => 'textarea']
                    ]
                ]
            ],
            'request' => $request
        ]);

        $app->impersonate($userId);

        return $app;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function write(array $body): array
    {
        $app = $this->app(['method' => 'POST', 'body' => ['path' => 'pages/about', ...$body]]);

        return $this->callRoute($app, '__content-translator__/batch-write');
    }

    private static function editAs(string $userId, string $language): void
    {
        $app = App::instance();
        $app->impersonate($userId, fn () => $app->page('about')->version('changes')->save(['text' => 'Draft'], $language));
    }

    #[Test]
    public function batch_write_saves_content_title_and_slug_of_one_language(): void
    {
        $response = $this->write([
            'language' => 'de',
            'content' => ['text' => 'Hallo', 'author' => 'Anna', 'teaser' => 'Kurz'],
            'title' => 'Über uns & mehr',
            'slug' => 'Über uns & mehr'
        ]);

        $page = App::instance()->page('about');

        $this->assertSame(['status' => 'saved'], $response);
        $this->assertSame('Hallo', $page->content('de')->get('text')->value());
        $this->assertSame('Über uns & mehr', $page->content('de')->get('title')->value());
        $this->assertSame('ueber-uns-mehr', $page->slug('de'));
        $this->assertSame('Hello', $page->content('en')->get('text')->value());
    }

    #[Test]
    public function batch_write_builds_the_slug_with_the_rules_of_the_target_language(): void
    {
        // French has no rule for `ü`, so it falls back to the ASCII table.
        $response = $this->write(['language' => 'fr', 'content' => ['author' => 'Anne'], 'slug' => 'Über uns']);

        $this->assertSame(['status' => 'saved'], $response);
        $this->assertSame('uber-uns', App::instance()->page('about')->slug('fr'));
    }

    #[Test]
    public function batch_write_saves_invalid_content_and_names_each_invalid_field(): void
    {
        $response = $this->write([
            'language' => 'de',
            'content' => ['text' => 'Hallo', 'teaser' => 'Viel zu lang geraten']
        ]);

        $page = App::instance()->page('about');

        $this->assertSame('saved', $response['status']);
        $this->assertSame(['teaser', 'author'], array_keys($response['invalidFields']));
        $this->assertArrayHasKey('maxlength', $response['invalidFields']['teaser']['message']);
        $this->assertArrayHasKey('required', $response['invalidFields']['author']['message']);
        $this->assertSame('Viel zu lang geraten', $page->content('de')->get('teaser')->value());
    }

    #[Test]
    public function batch_write_saves_the_content_and_returns_titleError_without_the_changeTitle_permission(): void
    {
        $app = $this->app(
            [
                'method' => 'POST',
                'body' => [
                    'path' => 'pages/about',
                    'language' => 'de',
                    'content' => ['text' => 'Hallo'],
                    'title' => 'Ueber uns'
                ]
            ],
            userId: 'writer'
        );

        $response = $this->callRoute($app, '__content-translator__/batch-write');
        $page = $app->page('about');

        $this->assertSame('saved', $response['status']);
        $this->assertArrayHasKey('titleError', $response);
        $this->assertSame('Hallo', $page->content('de')->get('text')->value());
        $this->assertSame('About', $page->content('de')->get('title')->value());
    }

    #[Test]
    public function batch_write_returns_locked_while_another_user_edits_a_different_language(): void
    {
        $app = $this->app(['method' => 'POST', 'body' => ['path' => 'pages/about', 'language' => 'de', 'content' => ['text' => 'Hallo']]]);
        self::editAs('colleague', 'fr');

        $response = $this->callRoute($app, '__content-translator__/batch-write');

        $this->assertSame(['status' => 'locked', 'lockedBy' => 'Colleague'], $response);
        $this->assertFalse($app->page('about')->version()->exists('de'));
    }

    #[Test]
    public function batch_write_returns_unsavedChanges_for_a_language_the_current_user_has_changed(): void
    {
        $app = $this->app(['method' => 'POST', 'body' => ['path' => 'pages/about', 'language' => 'de', 'content' => ['text' => 'Hallo']]]);
        self::editAs('editor', 'de');

        $response = $this->callRoute($app, '__content-translator__/batch-write');

        $this->assertSame(['status' => 'unsavedChanges'], $response);
        $this->assertFalse($app->page('about')->version()->exists('de'));
    }

    #[Test]
    public function batch_write_saves_while_another_user_edits_with_content_locking_false(): void
    {
        $app = $this->app(['method' => 'POST', 'body' => ['path' => 'pages/about', 'language' => 'de', 'content' => ['text' => 'Hallo']]], isLockingEnabled: false);
        self::editAs('colleague', 'fr');

        $response = $this->callRoute($app, '__content-translator__/batch-write');

        $this->assertSame('saved', $response['status']);
    }

    #[Test]
    public function batch_write_throws_InvalidArgumentException_for_the_default_language(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->write(['language' => 'en', 'content' => ['text' => 'Hi']]);
    }

    #[Test]
    public function batch_status_lists_a_language_with_unsaved_changes_in_languagesWithUnsavedChanges(): void
    {
        $app = $this->app(['method' => 'GET', 'query' => ['path' => 'pages/about']]);

        $this->assertSame(
            ['isUpdateAllowed' => true, 'lockedBy' => null, 'languagesWithUnsavedChanges' => []],
            $this->callRoute($app, '__content-translator__/batch-status')
        );

        self::editAs('editor', 'de');

        $this->assertSame(
            ['isUpdateAllowed' => true, 'lockedBy' => null, 'languagesWithUnsavedChanges' => ['de']],
            $this->callRoute($app, '__content-translator__/batch-status')
        );
    }

    #[Test]
    public function batch_status_returns_the_user_who_edits_another_language_as_lockedBy(): void
    {
        $app = $this->app(['method' => 'GET', 'query' => ['path' => 'pages/about']]);
        self::editAs('colleague', 'fr');

        $this->assertSame(
            'Colleague',
            $this->callRoute($app, '__content-translator__/batch-status')['lockedBy']
        );
    }

    #[Test]
    public function batch_status_returns_isUpdateAllowed_false_for_a_user_without_the_update_permission(): void
    {
        $app = $this->app(['method' => 'GET', 'query' => ['path' => 'pages/about']], userId: 'reader');

        $this->assertSame(
            ['isUpdateAllowed' => false, 'lockedBy' => null, 'languagesWithUnsavedChanges' => []],
            $this->callRoute($app, '__content-translator__/batch-status')
        );
    }
}
