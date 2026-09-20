<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\Cascade;
use Kirby\Cms\App;
use Kirby\Cms\ModelWithContent;
use Kirby\Filesystem\F;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CascadeTest extends ApiRouteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $content = self::indexRoot() . '/content';
        F::write($content . '/1_about/about.en.txt', "Title: About\n");
        F::write($content . '/1_about/cover.jpg', '');
        F::write($content . '/1_about/plan.pdf', '');
        F::write($content . '/1_about/1_team/default.en.txt', "Title: Team\n");
    }

    /**
     * @param array<string, mixed> $hostBlueprint
     * @param array<string, mixed> $request
     */
    private function app(array $hostBlueprint, array $request = [], string $blueprint = 'pages/about'): App
    {
        $app = self::bootApp([
            'request' => $request,
            // `Find` resolves the `account` path of an impersonated user only with this option.
            'options' => ['api.allowImpersonation' => true],
            'users' => [['id' => 'editor', 'email' => 'editor@example.com', 'role' => 'admin']],
            'languages' => [
                ['code' => 'en', 'name' => 'English', 'default' => true],
                ['code' => 'de', 'name' => 'Deutsch']
            ],
            'blueprints' => [
                $blueprint => $hostBlueprint,
                'pages/vault' => ['options' => ['access' => false]],
                'pages/archive' => ['options' => ['update' => false]],
                'pages/default' => [
                    'options' => ['changeTitle' => false],
                    'fields' => ['text' => ['type' => 'text']]
                ]
            ]
        ]);

        // The `kirby` superuser of `bootApp()` is exempt from blueprint options.
        $app->impersonate('editor');

        return $app;
    }

    /**
     * @param array<string, ModelWithContent> $models
     * @return list<string>
     */
    private static function ids(array $models): array
    {
        return array_values(array_map(static fn (ModelWithContent $model): string => $model->id(), $models));
    }

    #[Test]
    public function models_returns_the_models_a_section_names(): void
    {
        $kirby = $this->app([
            'sections' => [
                'translator' => ['type' => 'content-translator', 'cascade' => 'page.files']
            ]
        ]);

        $models = Cascade::models($kirby->page('about'));

        $this->assertSame(['about/cover.jpg', 'about/plan.pdf'], self::ids($models));
    }

    #[Test]
    public function models_returns_the_models_the_view_button_names(): void
    {
        $kirby = $this->app([
            'buttons' => [
                'content-translator' => ['cascade' => 'page.children'],
                'languages' => true
            ]
        ]);

        $models = Cascade::models($kirby->page('about'));

        $this->assertSame(['about/team'], self::ids($models));
    }

    #[Test]
    public function models_resolves_a_list_of_queries_in_order(): void
    {
        $kirby = $this->app([
            'buttons' => [
                'content-translator' => [
                    'cascade' => ['page.children', 'page.file("plan.pdf")']
                ]
            ]
        ]);

        $models = Cascade::models($kirby->page('about'));

        $this->assertSame(['about/team', 'about/plan.pdf'], self::ids($models));
    }

    #[Test]
    public function models_ignores_a_query_result_that_is_no_page_or_file(): void
    {
        $kirby = $this->app([
            'buttons' => [
                'content-translator' => [
                    'cascade' => [
                        'page.find("modules").children',
                        'page.title.value',
                        'kirby.users',
                        'site',
                        'page.files.first'
                    ]
                ]
            ]
        ]);

        $models = Cascade::models($kirby->page('about'));

        $this->assertSame(['about/cover.jpg'], self::ids($models));
    }

    #[Test]
    public function models_ignores_a_cascade_option_that_is_no_string(): void
    {
        $kirby = $this->app([
            'buttons' => [
                'content-translator' => ['cascade' => [true, ['page.files'], 'page.children']]
            ]
        ]);

        $models = Cascade::models($kirby->page('about'));

        $this->assertSame(['about/team'], self::ids($models));
    }

    #[Test]
    public function models_returns_no_model_with_buttons_false(): void
    {
        $kirby = $this->app(['buttons' => false]);

        $this->assertSame([], Cascade::models($kirby->page('about')));
    }

    #[Test]
    public function models_returns_no_model_for_a_view_button_listed_without_options(): void
    {
        $kirby = $this->app(['buttons' => ['preview', 'content-translator']]);

        $this->assertSame([], Cascade::models($kirby->page('about')));
    }

    #[Test]
    public function models_never_returns_the_host(): void
    {
        $kirby = $this->app([
            'buttons' => [
                'content-translator' => ['cascade' => ['page', 'page.files.first']]
            ]
        ]);

        $models = Cascade::models($kirby->page('about'));

        $this->assertSame(['about/cover.jpg'], self::ids($models));
    }

    #[Test]
    public function models_returns_a_model_two_queries_name_once(): void
    {
        $kirby = $this->app([
            'buttons' => [
                'content-translator' => [
                    'cascade' => ['page.files', 'page.file("plan.pdf")']
                ]
            ]
        ]);

        $models = Cascade::models($kirby->page('about'));

        $this->assertSame(['about/cover.jpg', 'about/plan.pdf'], self::ids($models));
    }

    #[Test]
    public function models_returns_a_page_file_and_a_user_file_that_share_an_id(): void
    {
        F::write(self::indexRoot() . '/content/editor/default.en.txt', "Title: Editor\n");
        F::write(self::indexRoot() . '/content/editor/avatar.jpg', '');
        F::write(self::indexRoot() . '/site/accounts/editor/avatar.jpg', '');

        $kirby = $this->app([
            'buttons' => [
                'content-translator' => [
                    'cascade' => ['kirby.page("editor").files', 'kirby.user("editor").files']
                ]
            ]
        ]);

        $models = Cascade::models($kirby->page('about'));

        $this->assertCount(2, $models);
    }

    #[Test]
    public function models_never_returns_a_page_the_user_cannot_access(): void
    {
        F::write(self::indexRoot() . '/content/1_about/2_vault/vault.en.txt', "Title: Vault\n");

        $kirby = $this->app([
            'buttons' => [
                'content-translator' => ['cascade' => 'page.children']
            ]
        ]);

        $models = Cascade::models($kirby->page('about'));

        $this->assertSame(['about/team'], self::ids($models));
    }

    #[Test]
    public function models_never_returns_a_file_of_a_page_the_user_cannot_access(): void
    {
        F::write(self::indexRoot() . '/content/1_about/2_vault/vault.en.txt', "Title: Vault\n");
        F::write(self::indexRoot() . '/content/1_about/2_vault/secret.pdf', '');

        $kirby = $this->app([
            'buttons' => [
                'content-translator' => ['cascade' => ['kirby.page("about/vault").files', 'page.files.first']]
            ]
        ]);

        $models = Cascade::models($kirby->page('about'));

        $this->assertSame(['about/cover.jpg'], self::ids($models));
    }

    #[Test]
    public function models_never_returns_a_page_the_user_cannot_update(): void
    {
        F::write(self::indexRoot() . '/content/1_about/2_archive/archive.en.txt', "Title: Archive\n");

        $kirby = $this->app([
            'buttons' => [
                'content-translator' => ['cascade' => 'page.children']
            ]
        ]);

        $models = Cascade::models($kirby->page('about'));

        $this->assertSame(['about/team'], self::ids($models));
    }

    #[Test]
    public function models_returns_the_models_a_site_blueprint_names(): void
    {
        F::write(self::indexRoot() . '/content/logo.svg', '');

        $kirby = $this->app([
            'buttons' => [
                'content-translator' => ['cascade' => 'site.files']
            ]
        ], blueprint: 'site');

        $models = Cascade::models($kirby->site());

        $this->assertSame(['logo.svg'], self::ids($models));
    }

    #[Test]
    public function cascade_returns_the_panel_path_of_every_model(): void
    {
        $kirby = $this->app(
            ['buttons' => ['content-translator' => ['cascade' => ['page.children', 'page.files']]]],
            ['query' => ['path' => 'pages/about']]
        );

        $models = $this->callRoute($kirby, '__content-translator__/cascade');

        $this->assertSame(
            ['pages/about+team', 'pages/about/files/cover.jpg', 'pages/about/files/plan.pdf'],
            array_column($models, 'path')
        );
    }

    #[Test]
    public function cascade_returns_the_title_of_a_page_and_the_filename_of_a_file(): void
    {
        $kirby = $this->app(
            ['buttons' => ['content-translator' => ['cascade' => ['page.children', 'page.file("plan.pdf")']]]],
            ['query' => ['path' => 'pages/about']]
        );

        $models = $this->callRoute($kirby, '__content-translator__/cascade');

        $this->assertSame(['Team', 'plan.pdf'], array_column($models, 'title'));
    }

    #[Test]
    public function cascade_returns_the_fields_and_the_status_of_every_model(): void
    {
        $kirby = $this->app(
            ['buttons' => ['content-translator' => ['cascade' => 'page.children']]],
            ['query' => ['path' => 'pages/about']]
        );

        [$team] = $this->callRoute($kirby, '__content-translator__/cascade');

        $this->assertSame(['text'], array_keys($team['fields']));
        $this->assertSame(
            [
                'isUpdateAllowed' => true,
                'isTitleChangeAllowed' => false,
                'lockedBy' => null,
                'languagesWithUnsavedChanges' => []
            ],
            $team['status']
        );
    }
}
