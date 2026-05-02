<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\Translation\Collector;
use JohannSchopplich\ContentTranslator\Translation\TranslationMode;
use JohannSchopplich\ContentTranslator\TranslatorConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CollectorTest extends TestCase
{
    /**
     * @param array<string, mixed> $partial
     * @return array<string, mixed>
     */
    private static function field(array $partial): array
    {
        return [
            'translate' => true,
            ...$partial,
        ];
    }

    private static function defaultConfig(array $overrides = []): TranslatorConfig
    {
        return new TranslatorConfig(
            fieldTypes: $overrides['fieldTypes'] ?? [
                'text', 'textarea', 'writer', 'list', 'tags',
                'blocks', 'layout', 'structure', 'object',
                'markdown', 'table',
            ],
            includeFields: $overrides['includeFields'] ?? [],
            excludeFields: $overrides['excludeFields'] ?? [],
            kirbyTags: $overrides['kirbyTags'] ?? [],
        );
    }

    #[Test]
    public function collects_text_writer_and_list_fields_as_batch_units(): void
    {
        $content = ['title' => 'Hello', 'body' => 'World', 'items' => 'List'];
        $fields = [
            'title' => self::field(['type' => 'text']),
            'body' => self::field(['type' => 'writer']),
            'items' => self::field(['type' => 'list']),
        ];

        $collector = new Collector($fields, self::defaultConfig());
        $result = $collector->collect($content);

        $this->assertCount(3, $result->translations);
        $this->assertSame(
            ['Hello', 'World', 'List'],
            array_map(fn ($t) => $t->unit->text, $result->translations),
        );
        $this->assertSame(
            ['title', 'body', 'items'],
            array_map(fn ($t) => $t->unit->fieldKey, $result->translations),
        );
        foreach ($result->translations as $t) {
            $this->assertSame(TranslationMode::Batch, $t->unit->mode);
        }
    }

    #[Test]
    public function expands_textarea_into_batch_units_with_kirbytag_protection(): void
    {
        $content = ['intro' => 'Visit (link: /a text: site)'];
        $fields = ['intro' => self::field(['type' => 'textarea'])];

        $collector = new Collector(
            fields: $fields,
            config: self::defaultConfig(['kirbyTags' => ['link' => ['text']]]),
        );
        $result = $collector->collect($content);

        $this->assertGreaterThanOrEqual(2, count($result->translations));
        foreach ($result->translations as $t) {
            $this->assertSame(TranslationMode::Batch, $t->unit->mode);
        }
        $texts = array_map(fn ($t) => $t->unit->text, $result->translations);
        $this->assertContains('site', $texts);

        $result->translations[0]->writeBack->__invoke($result->translations[0]->unit->text);
        $result->translations[1]->writeBack->__invoke('Seite');
        foreach ($result->finalizers as $finalize) {
            $finalize();
        }

        $this->assertSame('Visit (link: /a text: Seite)', $content['intro']);
    }

    #[Test]
    public function tags_round_trip_via_pipe_separated_translation(): void
    {
        $content = ['colors' => ['Red', 'Green', 'Blue']];
        $fields = ['colors' => self::field(['type' => 'tags'])];

        $collector = new Collector($fields, self::defaultConfig());
        $result = $collector->collect($content);

        $this->assertCount(1, $result->translations);
        $this->assertSame(TranslationMode::Batch, $result->translations[0]->unit->mode);
        $this->assertSame('Red | Green | Blue', $result->translations[0]->unit->text);

        $result->translations[0]->writeBack->__invoke('Rot | Grün | Blau');

        $this->assertSame(['Rot', 'Grün', 'Blau'], $content['colors']);
    }

    #[Test]
    public function each_table_cell_translates_independently(): void
    {
        $content = ['table' => [['A', 'B'], ['C', 'D']]];
        $fields = ['table' => self::field(['type' => 'table'])];

        $collector = new Collector($fields, self::defaultConfig());
        $result = $collector->collect($content);

        $this->assertCount(4, $result->translations);
        $this->assertSame(
            ['A', 'B', 'C', 'D'],
            array_map(fn ($t) => $t->unit->text, $result->translations),
        );
        foreach ($result->translations as $t) {
            $this->assertSame(TranslationMode::Single, $t->unit->mode);
        }

        $result->translations[0]->writeBack->__invoke('1');
        $result->translations[1]->writeBack->__invoke('2');
        $result->translations[2]->writeBack->__invoke('3');
        $result->translations[3]->writeBack->__invoke('4');

        $this->assertSame([['1', '2'], ['3', '4']], $content['table']);
    }

    #[Test]
    public function table_yaml_string_round_trips_through_translation(): void
    {
        $content = ['table' => "-\n  - A\n  - B"];
        $fields = ['table' => self::field(['type' => 'table'])];

        $collector = new Collector($fields, self::defaultConfig());
        $result = $collector->collect($content);

        $this->assertCount(2, $result->translations);

        $result->translations[0]->writeBack->__invoke('X');
        $result->translations[1]->writeBack->__invoke('Y');
        foreach ($result->finalizers as $finalize) {
            $finalize();
        }

        $this->assertSame([['X', 'Y']], \Kirby\Data\Data::decode($content['table'], 'yaml'));
    }

    #[Test]
    public function translates_structure_field_entries(): void
    {
        $content = ['items' => [['label' => 'One'], ['label' => 'Two']]];
        $fields = [
            'items' => self::field([
                'type' => 'structure',
                'fields' => ['label' => self::field(['type' => 'text'])],
            ]),
        ];

        $collector = new Collector($fields, self::defaultConfig());
        $result = $collector->collect($content);

        $this->assertCount(2, $result->translations);
        $this->assertSame(
            ['One', 'Two'],
            array_map(fn ($t) => $t->unit->text, $result->translations),
        );

        $result->translations[0]->writeBack->__invoke('Eins');
        $result->translations[1]->writeBack->__invoke('Zwei');

        $this->assertSame('Eins', $content['items'][0]['label']);
        $this->assertSame('Zwei', $content['items'][1]['label']);
    }

    #[Test]
    public function translates_object_field_properties(): void
    {
        $content = ['meta' => ['title' => 'Nested']];
        $fields = [
            'meta' => self::field([
                'type' => 'object',
                'fields' => ['title' => self::field(['type' => 'text'])],
            ]),
        ];

        $collector = new Collector($fields, self::defaultConfig());
        $result = $collector->collect($content);

        $this->assertCount(1, $result->translations);
        $this->assertSame('Nested', $result->translations[0]->unit->text);

        $result->translations[0]->writeBack->__invoke('Verschachtelt');
        $this->assertSame('Verschachtelt', $content['meta']['title']);
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $blockTypes
     */
    private static function blocksField(array $blockTypes): array
    {
        $fieldsets = [];
        foreach ($blockTypes as $type => $blockFields) {
            $fieldsets[$type] = ['tabs' => ['content' => ['fields' => $blockFields]]];
        }
        return self::field(['type' => 'blocks', 'fieldsets' => $fieldsets]);
    }

    #[Test]
    public function translates_block_contents_via_fieldsets(): void
    {
        $content = [
            'blocks' => [
                ['id' => '1', 'type' => 'heading', 'isHidden' => false, 'content' => ['text' => 'Title']],
                ['id' => '2', 'type' => 'text', 'isHidden' => false, 'content' => ['body' => 'Body']],
            ],
        ];
        $fields = [
            'blocks' => self::blocksField([
                'heading' => ['text' => self::field(['type' => 'text'])],
                'text' => ['body' => self::field(['type' => 'text'])],
            ]),
        ];

        $collector = new Collector($fields, self::defaultConfig());
        $result = $collector->collect($content);

        $this->assertCount(2, $result->translations);
        $this->assertSame(
            ['Title', 'Body'],
            array_map(fn ($t) => $t->unit->text, $result->translations),
        );
    }

    #[Test]
    public function skips_hidden_blocks_and_unknown_fieldset_types(): void
    {
        $content = [
            'blocks' => [
                ['id' => '1', 'type' => 'text', 'isHidden' => true, 'content' => ['text' => 'Hidden']],
                ['id' => '2', 'type' => 'unknown', 'isHidden' => false, 'content' => ['text' => 'Unknown']],
                ['id' => '3', 'type' => 'text', 'isHidden' => false, 'content' => ['text' => 'Visible']],
            ],
        ];
        $fields = [
            'blocks' => self::blocksField([
                'text' => ['text' => self::field(['type' => 'text'])],
            ]),
        ];

        $collector = new Collector($fields, self::defaultConfig());
        $result = $collector->collect($content);

        $this->assertCount(1, $result->translations);
        $this->assertSame('Visible', $result->translations[0]->unit->text);
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $blockTypes
     */
    private static function layoutField(array $blockTypes): array
    {
        $fieldsets = [];
        foreach ($blockTypes as $type => $blockFields) {
            $fieldsets[$type] = ['tabs' => ['content' => ['fields' => $blockFields]]];
        }
        return self::field(['type' => 'layout', 'fieldsets' => $fieldsets]);
    }

    #[Test]
    public function translates_layout_block_contents(): void
    {
        $content = [
            'layout' => [
                [
                    'columns' => [
                        [
                            'blocks' => [
                                ['id' => '1', 'type' => 'text', 'isHidden' => false, 'content' => ['body' => 'Column']],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $fields = [
            'layout' => self::layoutField([
                'text' => ['body' => self::field(['type' => 'text'])],
            ]),
        ];

        $collector = new Collector($fields, self::defaultConfig());
        $result = $collector->collect($content);

        $this->assertCount(1, $result->translations);
        $this->assertSame('Column', $result->translations[0]->unit->text);
    }

    #[Test]
    public function skips_field_marked_translate_false(): void
    {
        $content = ['title' => 'Hello', 'slug' => 'hello'];
        $fields = [
            'title' => self::field(['type' => 'text']),
            'slug' => self::field(['type' => 'text', 'translate' => false]),
        ];

        $collector = new Collector($fields, self::defaultConfig());
        $result = $collector->collect($content);

        $this->assertCount(1, $result->translations);
        $this->assertSame('Hello', $result->translations[0]->unit->text);
    }

    #[Test]
    public function respects_include_and_exclude_field_filters(): void
    {
        $content = ['title' => 'Hello', 'subtitle' => 'World', 'body' => 'Skip'];
        $fields = [
            'title' => self::field(['type' => 'text']),
            'subtitle' => self::field(['type' => 'text']),
            'body' => self::field(['type' => 'text']),
        ];

        $included = (new Collector($fields, self::defaultConfig(['includeFields' => ['title']])))
            ->collect($content);
        $this->assertSame(['Hello'], array_map(fn ($t) => $t->unit->text, $included->translations));

        $excluded = (new Collector($fields, self::defaultConfig(['excludeFields' => ['body']])))
            ->collect($content);
        $this->assertSame(['Hello', 'World'], array_map(fn ($t) => $t->unit->text, $excluded->translations));
    }

    #[Test]
    public function skips_field_types_outside_the_allowlist(): void
    {
        $content = ['title' => 'Hello'];
        $fields = ['title' => self::field(['type' => 'text'])];

        $collector = new Collector($fields, self::defaultConfig(['fieldTypes' => ['textarea']]));
        $result = $collector->collect($content);

        $this->assertCount(0, $result->translations);
    }

    /** @return array<string, array{0: string, 1: mixed}> */
    public static function skippableValues(): array
    {
        return [
            'empty string' => ['text', ''],
            'pure integer' => ['text', '123'],
            'decimal' => ['text', '45.67'],
            'negative number' => ['text', '-99'],
            'scientific notation' => ['text', '1.5e10'],
            'https URL' => ['text', 'https://example.com'],
            'http URL with path' => ['text', 'http://localhost:3000/path?query=1'],
            'whitespace-only textarea' => ['textarea', '   '],
            'empty markdown' => ['markdown', ''],
            'empty tags array' => ['tags', []],
            'empty table cells' => ['table', [['', '  ', null]]],
        ];
    }

    #[Test]
    #[DataProvider('skippableValues')]
    public function skips_filtered_values(string $fieldType, mixed $value): void
    {
        $content = ['x' => $value];
        $fields = ['x' => self::field(['type' => $fieldType])];

        $collector = new Collector($fields, self::defaultConfig());
        $result = $collector->collect($content);

        $this->assertCount(0, $result->translations);
    }
}
