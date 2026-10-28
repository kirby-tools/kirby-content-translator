<?php

declare(strict_types = 1);

use Kirby\Cms\App;
use Kirby\Form\Field;
use Kirby\Form\Form;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ContentTranslatorFieldTest extends ApiRouteTestCase
{
    #[Test]
    public function toArray_exports_the_field_options(): void
    {
        $props = $this->formField($this->appWithField([
            'import' => false,
            'importFrom' => 'all',
            'batch' => true,
            'title' => true,
            'slug' => false,
            'confirm' => false,
            'fieldTypes' => ['text', 'writer'],
            'includeFields' => ['intro'],
            'excludeFields' => ['seo'],
            'kirbyTags' => ['link' => ['text']],
            'cascade' => 'page.files',
            'systemPrompt' => "  Keep the brand names.\n"
        ]))->toArray();

        $expected = [
            'batch' => true,
            'cascade' => 'page.files',
            'confirm' => false,
            'excludeFields' => ['seo'],
            'fieldTypes' => ['text', 'writer'],
            'import' => false,
            'importFrom' => 'all',
            'includeFields' => ['intro'],
            'kirbyTags' => ['link' => ['text']],
            'licenseDialog' => 'johannschopplich-kirby-content-translator/activate',
            'licenseStatus' => 'inactive',
            'slug' => false,
            'systemPrompt' => 'Keep the brand names.',
            'title' => true
        ];

        $this->assertSame($expected, array_intersect_key($props, $expected));
    }

    #[Test]
    public function toArray_omits_the_options_a_blueprint_leaves_unset(): void
    {
        $props = $this->formField($this->appWithField())->toArray();

        $this->assertSame('inactive', $props['licenseStatus']);
        foreach (['import', 'importFrom', 'batch', 'title', 'slug', 'confirm', 'fieldTypes', 'includeFields', 'excludeFields', 'kirbyTags', 'cascade', 'systemPrompt'] as $key) {
            $this->assertArrayNotHasKey($key, $props);
        }
    }

    #[Test]
    public function label_falls_back_to_the_plugin_translation_unless_the_blueprint_sets_one(): void
    {
        $this->assertSame('Content Translator', $this->formField($this->appWithField())->label());
        $this->assertSame('Translate', $this->formField($this->appWithField(['label' => 'Translate']))->label());
    }

    private function appWithField(array $field = []): App
    {
        return self::bootApp([
            'blueprints' => [
                'pages/article' => [
                    'title' => 'Article',
                    'fields' => [
                        'translator' => ['type' => 'content-translator', ...$field]
                    ]
                ]
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'test',
                        'template' => 'article'
                    ]
                ]
            ]
        ]);
    }

    private function formField(App $app): Field
    {
        return Form::for($app->page('test'))->field('translator');
    }
}
