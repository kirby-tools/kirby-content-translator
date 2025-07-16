<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator;

use Exception;
use Kirby\Cms\ModelWithContent;
use Kirby\Text\KirbyTag;

final class KirbyText
{
    /** @see https://github.com/getkirby/kirby/blob/main/src/Text/KirbyTags.php */
    private const KIRBY_TAGS_REGEX = '!
        (?=[^\]])               # positive lookahead that matches a group after the main expression without including ] in the result
        (?=\([a-z0-9_-]+:)      # positive lookahead that requires starts with ( and lowercase ASCII letters, digits, underscores or hyphens followed with : immediately to the right of the current location
        (\(                     # capturing group 1
            (?:[^()]+|(?1))*+   # repetitions of any chars other than ( and ) or the whole group 1 pattern (recursed)
        \))                     # end of capturing group 1
    !isx';

    private ModelWithContent $model;
    private array $kirbyTags;

    public function __construct(ModelWithContent $model, array $kirbyTags = [])
    {
        $this->model = $model;
        $this->kirbyTags = $kirbyTags;
    }

    public function translateText(string $text, string $targetLanguage, string|null $sourceLanguage = null): string
    {
        if (empty($text)) {
            return '';
        }

        if (!empty($this->kirbyTags)) {
            $text = preg_replace_callback(
                self::KIRBY_TAGS_REGEX,
                fn (array $matches) => $this->processKirbyTag($matches[0], $targetLanguage, $sourceLanguage),
                $text
            );
        }

        return $this->protectedTranslate($text, $targetLanguage, $sourceLanguage);
    }

    private function processKirbyTag(string $tagString, string $targetLanguage, string|null $sourceLanguage = null): string
    {
        try {
            $tag = KirbyTag::parse($tagString, [
                'kirby' => $this->model->kirby(),
                'parent' => $this->model
            ]);

            $tagType = $tag->type();
            $translatableAttributes = $this->kirbyTags[$tagType] ?? null;

            if (empty($translatableAttributes)) {
                return '<div translate="no">' . $tagString . '</div>';
            }

            $newAttributes = [];
            $hasTranslations = false;

            // Translate the main value if `value` is in the attributes list
            $newValue = $tag->value;
            if (in_array('value', $translatableAttributes, true) && !empty($tag->value)) {
                $newValue = Translator::translateText($tag->value, $targetLanguage, $sourceLanguage);
                $hasTranslations = true;
            }

            // Process each attribute
            foreach ($tag->attrs as $attrName => $attrValue) {
                if (in_array($attrName, $translatableAttributes, true) && !empty($attrValue)) {
                    $newAttributes[$attrName] = Translator::translateText($attrValue, $targetLanguage, $sourceLanguage);
                    $hasTranslations = true;
                } else {
                    $newAttributes[$attrName] = $attrValue;
                }
            }

            // If no translations were made, return the original
            if (!$hasTranslations) {
                return $tagString;
            }

            return $this->createKirbyTag($tagType, $newValue, $newAttributes);

        } catch (Exception $e) {
            if ($this->model->kirby()->option('debug', false)) {
                throw $e;
            }

            return '<div translate="no">' . $tagString . '</div>';
        }
    }

    private function createKirbyTag(string $type, string|null $value, array $attributes): string
    {
        $parts = [];

        // Start with the tag type and main value
        if (!empty($value)) {
            $parts[] = $type . ': ' . $value;
        } else {
            $parts[] = $type;
        }

        // Add attributes
        foreach ($attributes as $name => $attrValue) {
            if (!empty($attrValue)) {
                $parts[] = $name . ': ' . $attrValue;
            }
        }

        return '(' . implode(' ', $parts) . ')';
    }

    private function protectedTranslate(string $text, string $targetLanguage, string|null $sourceLanguage = null): string
    {
        $protectedText = preg_replace_callback(
            self::KIRBY_TAGS_REGEX,
            fn (array $matches) => '<div translate="no">' . $matches[0] . '</div>',
            $text
        );

        $translatedText = Translator::translateText($protectedText, $targetLanguage, $sourceLanguage);

        return preg_replace(
            '!<div translate="no">(.*?)</div>!s',
            '$1',
            $translatedText
        );
    }
}
