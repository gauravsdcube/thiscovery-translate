<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\services;

use humhub\modules\thiscoveryTranslate\models\ModuleSettings;
use humhub\modules\thiscoveryTranslate\models\Translation;
use Yii;

/**
 * Public API for Thiscovery modules. Hides AWS/caching/hashing.
 */
class TranslationService
{
    private ModuleSettings $settings;
    private TranslationResolver $resolver;

    public function __construct(?ModuleSettings $settings = null)
    {
        $this->settings = $settings ?: ModuleSettings::loadSettings();
        $this->resolver = new TranslationResolver($this->settings);
    }

    public function isEnabled(): bool
    {
        return $this->settings->featureEnabled;
    }

    public function getSettings(): ModuleSettings
    {
        return $this->settings;
    }

    /**
     * Translate a free string (lazy). Uses TM + Amazon on miss.
     */
    public function translateString(
        string $text,
        string $targetLanguage,
        string $sourceLanguage = '',
        string $context = 'generic',
        bool $allowAmazon = true
    ): string {
        $sourceLanguage = $sourceLanguage !== '' ? $sourceLanguage : $this->settings->sourceLanguage;
        $result = $this->resolver->resolve(
            $text,
            $sourceLanguage,
            $targetLanguage,
            'string',
            '',
            $context,
            $context,
            $allowAmazon,
            'thiscovery-translate'
        );
        return $result['text'];
    }

    /**
     * Object-field translation (lazy for UGC).
     */
    public function getTranslation(
        string $objectType,
        string|int $objectId,
        string $field,
        string $sourceText,
        string $targetLanguage,
        string $sourceLanguage = '',
        string $context = 'generic',
        bool $allowAmazon = true,
        ?string $module = null
    ): string {
        $sourceLanguage = $sourceLanguage !== '' ? $sourceLanguage : $this->settings->sourceLanguage;
        $result = $this->resolver->resolve(
            $sourceText,
            $sourceLanguage,
            $targetLanguage,
            $objectType,
            (string)$objectId,
            $field,
            $context,
            $allowAmazon,
            $module ?? $objectType
        );
        return $result['text'];
    }

    /**
     * Prefetch object translations for a stream of IDs (avoids N+1).
     *
     * @param string[]|int[] $objectIds
     * @return array<string, string> key = objectId.field => translated
     */
    public function prefetch(string $objectType, array $objectIds, string $field, string $targetLanguage): array
    {
        $targetAmz = LocaleMap::toAmazon($targetLanguage);
        $ids = array_map('strval', $objectIds);
        if ($ids === []) {
            return [];
        }
        $rows = Translation::find()
            ->where([
                'object_type' => $objectType,
                'field' => $field,
                'target_language' => $targetAmz,
                'object_id' => $ids,
            ])
            ->andWhere(['not in', 'translation_status', [Translation::STATUS_NEEDS_UPDATE, Translation::STATUS_FAILED]])
            ->all();
        $out = [];
        foreach ($rows as $row) {
            $out[$row->object_id . '.' . $row->field] = (string)$row->translated_text;
        }
        return $out;
    }

    public function sourceHash(string $text, string $sourceLanguage, string $fieldOrContext = 'generic'): string
    {
        return $this->resolver->sourceHash($text, $sourceLanguage, $fieldOrContext);
    }

    /**
     * Translate a stable menu display label by menu_key (not routes/permissions).
     */
    public function getMenuLabel(
        string $menuKey,
        string $sourceLabel,
        string $targetLanguage = '',
        string $sourceLanguage = '',
        bool $allowAmazon = true
    ): string {
        $targetLanguage = $targetLanguage !== '' ? $targetLanguage : Yii::$app->language;
        $sourceLanguage = $sourceLanguage !== '' ? $sourceLanguage : $this->settings->sourceLanguage;
        return $this->getTranslation(
            'menu',
            $menuKey,
            'label',
            $sourceLabel,
            $targetLanguage,
            $sourceLanguage,
            'navigation',
            $allowAmazon,
            'menu'
        );
    }

    /**
     * Save a human override and lock it.
     */
    public function saveManual(
        string $objectType,
        string|int $objectId,
        string $field,
        string $sourceText,
        string $translatedText,
        string $sourceLanguage,
        string $targetLanguage,
        bool $lock = true,
        string $context = 'generic'
    ): Translation {
        $now = date('Y-m-d H:i:s');
        $hash = $this->sourceHash($sourceText, $sourceLanguage, $field !== '' ? $field : $context);
        $row = Translation::findOne([
            'object_type' => $objectType,
            'object_id' => (string)$objectId,
            'field' => $field,
            'target_language' => LocaleMap::toAmazon($targetLanguage),
            'source_hash' => $hash,
        ]) ?: new Translation([
            'object_type' => $objectType,
            'object_id' => (string)$objectId,
            'field' => $field,
            'source_hash' => $hash,
            'created_at' => $now,
        ]);
        $row->source_language = LocaleMap::toAmazon($sourceLanguage);
        $row->target_language = LocaleMap::toAmazon($targetLanguage);
        $row->source_text = $sourceText;
        $row->translated_text = $translatedText;
        $row->translation_method = Translation::METHOD_MANUAL;
        $row->translation_status = Translation::STATUS_VERIFIED;
        $row->is_manual = true;
        $row->is_locked = $lock;
        $row->context = $context;
        $row->updated_at = $now;
        $row->translated_at = $now;
        $row->save(false);
        (new TranslationMemoryService())->remember(
            $sourceText,
            $translatedText,
            $sourceLanguage,
            $targetLanguage,
            $context,
            Translation::METHOD_MANUAL,
            true
        );
        return $row;
    }
}
