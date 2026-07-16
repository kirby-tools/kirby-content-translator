<?php

declare(strict_types = 1);

use JohannSchopplich\ContentTranslator\Translation\Collector;
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

        $this->assertCount(2, $result->translations);
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
    public function splits_tags_string_per_item_and_round_trips_in_same_shape(): void
    {
        $content = ['colors' => 'Red, Green, Blue'];
        $fields = ['colors' => self::field(['type' => 'tags'])];

        $collector = new Collector($fields, self::defaultConfig());
        $result = $collector->collect($content);

        $this->assertCount(1, $result->translations);
        $this->assertSame('Red | Green | Blue', $result->translations[0]->unit->text);

        $result->translations[0]->writeBack->__invoke('Rot | Grün | Blau');

        $this->assertSame('Rot, Grün, Blau', $content['colors']);
    }

    #[Test]
    public function translates_each_table_cell_independently(): void
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
    public function translates_table_cells_inside_block_content(): void
    {
        $content = [
            'blocks' => [
                [
                    'id' => '1',
                    'type' => 'table',
                    'isHidden' => false,
                    'content' => [
                        'table' => [['A', 'B'], ['C', 'D']],
                        'caption' => 'Caption',
                    ],
                ],
            ],
        ];
        $fields = [
            'blocks' => self::blocksField([
                'table' => [
                    'table' => self::field(['type' => 'table']),
                    'caption' => self::field(['type' => 'writer']),
                ],
            ]),
        ];

        $collector = new Collector($fields, self::defaultConfig());
        $result = $collector->collect($content);

        $this->assertCount(5, $result->translations);
        $this->assertSame(
            ['A', 'B', 'C', 'D', 'Caption'],
            array_map(fn ($t) => $t->unit->text, $result->translations),
        );

        $replacements = ['1', '2', '3', '4', 'Untertitel'];
        foreach ($result->translations as $i => $t) {
            $t->writeBack->__invoke($replacements[$i]);
        }
        foreach ($result->finalizers as $finalize) {
            $finalize();
        }

        $blocks = \Kirby\Data\Data::decode($content['blocks'], 'json');
        $this->assertSame([['1', '2'], ['3', '4']], $blocks[0]['content']['table']);
        $this->assertSame('Untertitel', $blocks[0]['content']['caption']);
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

    #[Test]
    public function structure_yaml_string_round_trips_through_translation(): void
    {
        $content = ['items' => "-\n  label: One\n-\n  label: Two"];
        $fields = [
            'items' => self::field([
                'type' => 'structure',
                'fields' => ['label' => self::field(['type' => 'text'])],
            ]),
        ];

        $collector = new Collector($fields, self::defaultConfig());
        $result = $collector->collect($content);

        $this->assertCount(2, $result->translations);
        $this->assertSame(['One', 'Two'], array_map(fn ($t) => $t->unit->text, $result->translations));

        $result->translations[0]->writeBack->__invoke('Eins');
        $result->translations[1]->writeBack->__invoke('Zwei');
        foreach ($result->finalizers as $finalize) {
            $finalize();
        }

        $this->assertIsString($content['items']);
        $this->assertSame(
            [['label' => 'Eins'], ['label' => 'Zwei']],
            \Kirby\Data\Data::decode($content['items'], 'yaml'),
        );
    }

    #[Test]
    public function object_yaml_string_round_trips_through_translation(): void
    {
        $content = ['meta' => 'title: Nested'];
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
        foreach ($result->finalizers as $finalize) {
            $finalize();
        }

        $this->assertIsString($content['meta']);
        $this->assertSame(
            ['title' => 'Verschachtelt'],
            \Kirby\Data\Data::decode($content['meta'], 'yaml'),
        );
    }

    #[Test]
    public function blocks_json_string_round_trips_through_translation(): void
    {
        $blocks = [
            ['id' => '1', 'type' => 'text', 'isHidden' => false, 'content' => ['body' => 'Hello']],
        ];
        $content = ['blocks' => json_encode($blocks)];
        $fields = [
            'blocks' => self::blocksField([
                'text' => ['body' => self::field(['type' => 'text'])],
            ]),
        ];

        $collector = new Collector($fields, self::defaultConfig());
        $result = $collector->collect($content);

        $this->assertCount(1, $result->translations);
        $this->assertSame('Hello', $result->translations[0]->unit->text);

        $result->translations[0]->writeBack->__invoke('Hallo');
        foreach ($result->finalizers as $finalize) {
            $finalize();
        }

        $this->assertIsString($content['blocks']);
        $decoded = \Kirby\Data\Data::decode($content['blocks'], 'json');
        $this->assertSame('Hallo', $decoded[0]['content']['body']);
    }

    #[Test]
    public function layout_json_string_round_trips_through_translation(): void
    {
        $layout = [
            [
                'columns' => [
                    [
                        'blocks' => [
                            ['id' => '1', 'type' => 'text', 'isHidden' => false, 'content' => ['body' => 'Column']],
                        ],
                    ],
                ],
            ],
        ];
        $content = ['layout' => json_encode($layout)];
        $fields = [
            'layout' => self::layoutField([
                'text' => ['body' => self::field(['type' => 'text'])],
            ]),
        ];

        $collector = new Collector($fields, self::defaultConfig());
        $result = $collector->collect($content);

        $this->assertCount(1, $result->translations);
        $this->assertSame('Column', $result->translations[0]->unit->text);

        $result->translations[0]->writeBack->__invoke('Spalte');
        foreach ($result->finalizers as $finalize) {
            $finalize();
        }

        $this->assertIsString($content['layout']);
        $decoded = \Kirby\Data\Data::decode($content['layout'], 'json');
        $this->assertSame('Spalte', $decoded[0]['columns'][0]['blocks'][0]['content']['body']);
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
    public function skips_blocks_marked_hidden(): void
    {
        $content = [
            'blocks' => [
                ['id' => '1', 'type' => 'text', 'isHidden' => true, 'content' => ['text' => 'Hidden']],
                ['id' => '2', 'type' => 'text', 'isHidden' => false, 'content' => ['text' => 'Visible']],
            ],
        ];
        $fields = [
            'blocks' => self::blocksField([
                'text' => ['text' => self::field(['type' => 'text'])],
            ]),
        ];

        $result = (new Collector($fields, self::defaultConfig()))->collect($content);

        $this->assertCount(1, $result->translations);
        $this->assertSame('Visible', $result->translations[0]->unit->text);
    }

    #[Test]
    public function skips_blocks_with_unknown_fieldset_type(): void
    {
        $content = [
            'blocks' => [
                ['id' => '1', 'type' => 'unknown', 'isHidden' => false, 'content' => ['text' => 'Unknown']],
                ['id' => '2', 'type' => 'text', 'isHidden' => false, 'content' => ['text' => 'Visible']],
            ],
        ];
        $fields = [
            'blocks' => self::blocksField([
                'text' => ['text' => self::field(['type' => 'text'])],
            ]),
        ];

        $result = (new Collector($fields, self::defaultConfig()))->collect($content);

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

    #[Test]
    public function skips_content_keys_without_blueprint_field(): void
    {
        $content = ['title' => 'Hello', 'orphan' => 'No blueprint here'];
        $fields = ['title' => self::field(['type' => 'text'])];

        $result = (new Collector($fields, self::defaultConfig()))->collect($content);

        $this->assertCount(1, $result->translations);
        $this->assertSame('Hello', $result->translations[0]->unit->text);
    }

    #[Test]
    public function translates_remaining_fields_when_some_are_skippable(): void
    {
        $content = [
            'title' => 'Hello',
            'price' => '49.99',
            'url' => 'https://example.com',
            'body' => 'World',
        ];
        $fields = [
            'title' => self::field(['type' => 'text']),
            'price' => self::field(['type' => 'text']),
            'url' => self::field(['type' => 'text']),
            'body' => self::field(['type' => 'text']),
        ];

        $result = (new Collector($fields, self::defaultConfig()))->collect($content);

        $this->assertSame(
            ['Hello', 'World'],
            array_map(fn ($t) => $t->unit->text, $result->translations),
        );
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
