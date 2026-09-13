<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator;

use Kirby\Cms\Language;
use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Cms\Url;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Form\Form;
use Kirby\Toolkit\Str;
use Throwable;

/**
 * The Panel side of a batch translation, reduced to one request per target
 * language: the lock checks, the content write, and the title and slug
 * changes all happen in that request.
 *
 * Runs as the current user: Kirby's model rules check the permissions.
 *
 * @internal
 */
final class BatchTranslation
{
    /**
     * Reports the model's lock, permission, and unsaved-changes state before
     * anything is translated, so a refused, locked, or unsaved language costs
     * no provider call.
     *
     * @return array{isUpdateAllowed: bool, lockedBy: string|null, languagesWithUnsavedChanges: list<string>}
     */
    public static function status(ModelWithContent $model): array
    {
        return [
            'isUpdateAllowed' => $model->permissions()->can('update'),
            'lockedBy' => self::lockingUsername($model),
            'languagesWithUnsavedChanges' => array_values(array_filter(
                $model->kirby()->languages()->codes(),
                fn (string $code): bool => $model->version('changes')->exists($code)
            ))
        ];
    }

    /**
     * Saves the content, skipping validation as `update()` does by default, so
     * a paid translation is never discarded over a field it did not touch. The
     * invalid fields come back instead.
     *
     * @param array<string, mixed> $content
     * @return array{
     *     status: 'saved'|'unsavedChanges'|'locked',
     *     lockedBy?: string,
     *     invalidFields?: array<string, array{label: string|null, message: array<string, string>}>,
     *     titleError?: string,
     *     slugError?: string
     * }
     */
    public static function writeLanguage(
        ModelWithContent $model,
        string $languageCode,
        array $content,
        string|null $title = null,
        string|null $slug = null
    ): array {
        $language = Language::ensure($languageCode);

        if ($language->isDefault()) {
            throw new InvalidArgumentException('The batch never writes the default language');
        }

        // Kirby's own save refuses every language while another user edits
        // any one of them, and writing the latest version skips that check.
        if (($lockedBy = self::lockingUsername($model)) !== null) {
            return ['status' => 'locked', 'lockedBy' => $lockedBy];
        }

        // Unsaved changes would cover the translation in the form and
        // overwrite it once someone saves them.
        if ($model->version('changes')->exists($language)) {
            return ['status' => 'unsavedChanges'];
        }

        $model = $model->update($content, $language->code());
        $result = ['status' => 'saved'];

        if ($title !== null && $title !== '' && ($model instanceof Page || $model instanceof Site)) {
            try {
                $model = $model->changeTitle($title, $language->code());
            } catch (Throwable $error) {
                $result['titleError'] = $error->getMessage();
            }
        }

        if ($slug !== null && $slug !== '' && $model instanceof Page && !$model->isHomeOrErrorPage()) {
            try {
                $model = $model->changeSlug(self::slug($slug, $language), $language->code());
            } catch (Throwable $error) {
                $result['slugError'] = $error->getMessage();
            }
        }

        $invalidFields = Form::for($model, language: $language->code())->errors();

        if ($invalidFields !== []) {
            $result['invalidFields'] = $invalidFields;
        }

        return $result;
    }

    /**
     * Sanitizes with the target language's slug rules. Kirby reads them from
     * the current language, which in a batch is the default language.
     */
    private static function slug(string $text, Language $language): string
    {
        $previousRules = Str::$language;
        Str::$language = $language->rules();

        try {
            return Url::slug($text);
        } finally {
            Str::$language = $previousRules;
        }
    }

    private static function lockingUsername(ModelWithContent $model): string|null
    {
        $lock = $model->lock();

        if (!$lock->isLocked()) {
            return null;
        }

        // `isLocked()` rules out a lock without a user, and `username()` falls
        // back to the email address.
        return $lock->user()->username();
    }
}
