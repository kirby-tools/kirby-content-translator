<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator;

use Kirby\Cms\ModelWithContent;
use Kirby\Form\Form;

final class FieldResolver
{
    public static function resolveModelFields(ModelWithContent $model): array
    {
        $fields = $model->blueprint()->fields();
        $languageCode = $model->kirby()->languageCode();
        $content = $model->content($languageCode)->toArray();
        $form = new Form([
            'fields' => $fields,
            'values' => $content,
            'model' => $model,
            'strict' => true
        ]);

        $fields = $form->fields()->toArray();
        unset($fields['title']);

        foreach ($fields as $index => $props) {
            unset($fields[$index]['value']);
        }

        return $fields;
    }
}
