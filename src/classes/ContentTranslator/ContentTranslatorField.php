<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator;

use Kirby\Form\Field\DisplayField;
use Kirby\Reflection\Attributes\Prop;
use Kirby\Toolkit\Str;

final class ContentTranslatorField extends DisplayField
{
    #[Prop]
    protected bool|null $import = null;

    #[Prop]
    protected string|null $importFrom = null;

    #[Prop]
    protected bool|null $batch = null;

    #[Prop]
    protected bool|null $title = null;

    #[Prop]
    protected bool|null $slug = null;

    #[Prop]
    protected array|null $fieldTypes = null;

    #[Prop]
    protected array|null $includeFields = null;

    #[Prop]
    protected array|null $excludeFields = null;

    #[Prop]
    protected array|null $kirbyTags = null;

    #[Prop]
    protected array|string|null $cascade = null;

    #[Prop(computed: true)]
    protected string|null $systemPrompt = null;

    #[Prop(computed: true)]
    protected string $licenseStatus = 'inactive';

    #[Prop(computed: true)]
    protected string $licenseDialog = '';

    public function label(): string
    {
        // The blueprint normalizer sets the label to `Str::label()` of the key when
        // none is given, so that value counts as unset.
        if ($this->label === null || $this->label === Str::label($this->name ?? '')) {
            return $this->i18n('johannschopplich.content-translator.label');
        }

        return parent::label();
    }

    public function systemPrompt(): string|null
    {
        return $this->systemPrompt !== null ? trim($this->systemPrompt) : null;
    }

    public function licenseStatus(): string
    {
        return Licensing::status();
    }

    public function licenseDialog(): string
    {
        return Licensing::dialog();
    }
}
