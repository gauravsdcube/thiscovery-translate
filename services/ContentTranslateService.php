<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\services;

use humhub\modules\comment\models\Comment;
use humhub\modules\post\models\Post;
use humhub\modules\thiscoveryTranslate\jobs\TranslateContentJob;
use humhub\modules\thiscoveryTranslate\models\ModuleSettings;
use Yii;

/**
 * Lazy UGC/content translation for RichText output (posts, comments).
 * Never rewrites whole pages — only the content string for a known record/field.
 */
class ContentTranslateService
{
    /** Sync Amazon calls for content per HTTP request (rest is queued). */
    public const REQUEST_AMAZON_BUDGET = 12;

    private static int $amazonUsed = 0;

    private ModuleSettings $settings;
    private TranslationService $translations;

    public function __construct(?ModuleSettings $settings = null)
    {
        $this->settings = $settings ?: ModuleSettings::loadSettings();
        $this->translations = new TranslationService($this->settings);
    }

    public function isEnabled(): bool
    {
        return $this->settings->featureEnabled && $this->settings->siteTranslateEnabled && $this->settings->contentTranslate;
    }

    /**
     * Translate richtext markdown for display if needed.
     */
    public function translateForDisplay(string $text, $record, string $field = 'message'): string
    {
        $meta = $this->resolveObject($record, $field);
        if ($meta === null) {
            return $text;
        }
        return $this->translateField(
            $meta['objectType'],
            $meta['objectId'],
            $meta['field'],
            $text,
            'content',
            $meta['module']
        );
    }

    /**
     * Generic object-field translation (posts, page-builder blocks, etc.).
     */
    public function translateField(
        string $objectType,
        string|int $objectId,
        string $field,
        string $text,
        string $context = 'content',
        ?string $module = null
    ): string {
        if (!$this->isEnabled() || trim($text) === '') {
            return $text;
        }

        $target = Yii::$app->language;
        $source = $this->settings->sourceLanguage;
        if ($target === '' || LocaleMap::sameLanguage($target, $source)) {
            return $text;
        }

        $module = $module ?? $objectType;

        // Always try cache/TM/object store first (no Amazon).
        $cached = $this->translations->getTranslation(
            $objectType,
            $objectId,
            $field,
            $text,
            $target,
            $source,
            $context,
            false,
            $module
        );
        if ($cached !== '' && $cached !== $text) {
            return $cached;
        }

        // Sync Amazon only within budget; otherwise queue for next view.
        if (self::$amazonUsed < self::REQUEST_AMAZON_BUDGET) {
            self::$amazonUsed++;
            return $this->translations->getTranslation(
                $objectType,
                $objectId,
                $field,
                $text,
                $target,
                $source,
                $context,
                true,
                $module
            );
        }

        try {
            Yii::$app->queue->push(new TranslateContentJob([
                'objectType' => $objectType,
                'objectId' => (string)$objectId,
                'field' => $field,
                'sourceText' => $text,
                'sourceLanguage' => $source,
                'targetLanguage' => $target,
                'context' => $context,
                'module' => $module,
            ]));
        } catch (\Throwable $e) {
            Yii::warning('Could not queue content translation: ' . $e->getMessage(), 'thiscovery-translate');
        }

        return $text;
    }

    /**
     * @return array{objectType:string,objectId:string|int,field:string,module:string}|null
     */
    private function resolveObject($record, string $field): ?array
    {
        if ($record instanceof Post) {
            return [
                'objectType' => 'post',
                'objectId' => (int)$record->id,
                'field' => $field !== '' ? $field : 'message',
                'module' => 'post',
            ];
        }
        if (class_exists(Comment::class) && $record instanceof Comment) {
            return [
                'objectType' => 'comment',
                'objectId' => (int)$record->id,
                'field' => $field !== '' ? $field : 'message',
                'module' => 'comment',
            ];
        }
        // ContentActiveRecord with a message field (wiki/pages etc.)
        if (
            is_object($record)
            && $record instanceof \humhub\modules\content\components\ContentActiveRecord
            && isset($record->id)
            && ($record->hasAttribute('message') || property_exists($record, 'message'))
        ) {
            $class = get_class($record);
            $short = substr(strrchr($class, '\\') ?: $class, 1);
            return [
                'objectType' => strtolower($short),
                'objectId' => (int)$record->id,
                'field' => $field !== '' ? $field : 'message',
                'module' => $short,
            ];
        }
        return null;
    }
}
