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
 * Resolution order: manual locked → native (caller) → object store → TM → Amazon → source.
 */
class TranslationResolver
{
    private ModuleSettings $settings;
    private TranslationMemoryService $memory;
    private TranslateProviderInterface $provider;
    private ContentProtector $protector;
    private CostTracker $cost;
    private ConcurrencyLock $lock;
    private TerminologyService $terminology;

    public function __construct(
        ?ModuleSettings $settings = null,
        ?TranslateProviderInterface $provider = null
    ) {
        $this->settings = $settings ?: ModuleSettings::loadSettings();
        $this->provider = $provider ?: new AmazonTranslateProvider($this->settings);
        $this->memory = new TranslationMemoryService();
        $this->protector = new ContentProtector();
        $this->cost = new CostTracker();
        $this->lock = new ConcurrencyLock();
        $this->terminology = new TerminologyService();
    }

    /**
     * @return array{text: string, method: string, from_cache: bool}
     */
    public function resolve(
        string $text,
        string $sourceLanguage,
        string $targetLanguage,
        string $objectType = 'string',
        string $objectId = '',
        string $field = '',
        string $context = 'generic',
        bool $allowAmazon = true,
        ?string $module = null,
        bool $skipNative = false
    ): array {
        $text = (string)$text;
        if ($text === '' || !$this->settings->featureEnabled) {
            return ['text' => $text, 'method' => 'none', 'from_cache' => true];
        }
        if (LocaleMap::sameLanguage($sourceLanguage, $targetLanguage)) {
            $this->cost->record('same_lang', true, 0, $sourceLanguage, $targetLanguage, 'none', 'skip', $module, $objectType);
            return ['text' => $text, 'method' => 'same', 'from_cache' => true];
        }
        if (!$this->isWorthTranslating($text)) {
            $this->cost->record('skip', true, 0, $sourceLanguage, $targetLanguage, 'none', 'skip', $module, $objectType);
            return ['text' => $text, 'method' => 'skip', 'from_cache' => true];
        }

        $sourceHash = $this->memory->hash($text, $sourceLanguage, $field !== '' ? $field : $context);
        $sourceAmz = LocaleMap::toAmazon($sourceLanguage);
        $targetAmz = LocaleMap::toAmazon($targetLanguage);

        // 1) Locked / manual object translation for this hash
        $objectRow = $this->findObjectTranslation($objectType, $objectId, $field, $targetAmz, $sourceHash, true);
        if ($objectRow) {
            $this->cost->record('manual', true, 0, $sourceLanguage, $targetLanguage, 'none', 'lookup', $module, $objectType);
            return ['text' => (string)$objectRow->translated_text, 'method' => Translation::METHOD_MANUAL, 'from_cache' => true];
        }

        // 2) Exact object store (any non-stale method) for this hash
        $objectRow = $this->findObjectTranslation($objectType, $objectId, $field, $targetAmz, $sourceHash, false);
        if ($objectRow && $objectRow->translation_status !== Translation::STATUS_NEEDS_UPDATE
            && $objectRow->translation_status !== Translation::STATUS_FAILED) {
            $cached = (string)$objectRow->translated_text;
            if (ContentProtector::looksLeaked($cached) && !(bool)$objectRow->is_locked) {
                // Drop bad cache so we can retranslate.
                try {
                    $objectRow->delete();
                } catch (\Throwable $e) {
                    // continue to Amazon / TM
                }
            } else {
                $this->cost->record('object', true, 0, $sourceLanguage, $targetLanguage, 'none', 'lookup', $module, $objectType);
                return ['text' => $cached, 'method' => (string)$objectRow->translation_method, 'from_cache' => true];
            }
        }

        // 3) Translation memory
        $mem = $this->memory->find($text, $sourceLanguage, $targetLanguage, $context);
        if ($mem !== null) {
            if (ContentProtector::looksLeaked($mem)) {
                $mem = null;
            } else {
                $this->persistObject($objectType, $objectId, $field, $sourceLanguage, $targetLanguage, $text, $sourceHash, $mem, Translation::METHOD_MEMORY, $context);
                $this->cost->record('memory', true, 0, $sourceLanguage, $targetLanguage, 'none', 'lookup', $module, $objectType);
                return ['text' => $mem, 'method' => Translation::METHOD_MEMORY, 'from_cache' => true];
            }
        }

        if ($mem === null && !$allowAmazon) {
            return ['text' => $text, 'method' => 'source', 'from_cache' => true];
        }

        if (!$this->cost->withinHardLimit(mb_strlen($text), $this->settings)) {
            Yii::warning('Translation hard budget reached; returning source.', 'thiscovery-translate');
            return ['text' => $text, 'method' => 'budget', 'from_cache' => true];
        }

        $lockKey = $sourceHash . '|' . $sourceAmz . '|' . $targetAmz . '|' . $context;
        if (!$this->lock->acquire($lockKey)) {
            $waited = $this->lock->waitFor($lockKey, function () use ($objectType, $objectId, $field, $targetAmz, $sourceHash, $text, $sourceLanguage, $targetLanguage, $context) {
                $row = $this->findObjectTranslation($objectType, $objectId, $field, $targetAmz, $sourceHash, false);
                if ($row) {
                    return (string)$row->translated_text;
                }
                return $this->memory->find($text, $sourceLanguage, $targetLanguage, $context);
            });
            if (is_string($waited) && $waited !== '') {
                $this->cost->record('memory', true, 0, $sourceLanguage, $targetLanguage, 'none', 'lookup', $module, $objectType);
                return ['text' => $waited, 'method' => Translation::METHOD_MEMORY, 'from_cache' => true];
            }
        }

        try {
            $protected = $this->protector->protect($text);
            $protected = $this->protector->protectTerms($protected, $this->terminology->doNotTranslateTerms());
            // Always HTML when markers present — translate="no" spans must not go through text mode.
            $format = ($this->protector->hasTokens() || $this->looksLikeHtml($text)) ? 'html' : 'text';
            $translated = $this->provider->translate($protected, $sourceAmz, $targetAmz, $format);
            $translated = $this->protector->restore($translated);
            $translated = $this->terminology->applyPreferred($translated, $targetLanguage);
            if (trim($translated) === '') {
                $translated = $text;
            }
            // Never persist leaked protector markers (old ZZTT… or data-tth spans).
            if (ContentProtector::looksLeaked($translated)) {
                Yii::warning('Translation leaked protector tokens; returning source and not caching.', 'thiscovery-translate');
                $this->cost->record('failed', false, mb_strlen($text), $sourceLanguage, $targetLanguage, 'amazon', 'leak', $module, $objectType);
                return ['text' => $text, 'method' => 'failed', 'from_cache' => false];
            }
            $this->persistObject($objectType, $objectId, $field, $sourceLanguage, $targetLanguage, $text, $sourceHash, $translated, Translation::METHOD_AMAZON, $context);
            $this->memory->remember($text, $translated, $sourceLanguage, $targetLanguage, $context, Translation::METHOD_AMAZON);
            $this->cost->record('amazon', false, mb_strlen($text), $sourceLanguage, $targetLanguage, 'amazon', 'translate', $module, $objectType);
            return ['text' => $translated, 'method' => Translation::METHOD_AMAZON, 'from_cache' => false];
        } catch (\Throwable $e) {
            Yii::warning('Amazon Translate failed: ' . $e->getMessage(), 'thiscovery-translate');
            $this->markFailed($objectType, $objectId, $field, $sourceLanguage, $targetLanguage, $text, $sourceHash, $context);
            $this->cost->record('failed', false, 0, $sourceLanguage, $targetLanguage, 'amazon', 'error', $module, $objectType);
            return ['text' => $text, 'method' => 'failed', 'from_cache' => false];
        } finally {
            $this->lock->release($lockKey);
        }
    }

    public function sourceHash(string $text, string $sourceLanguage, string $fieldOrContext = 'generic'): string
    {
        return $this->memory->hash($text, $sourceLanguage, $fieldOrContext);
    }

    private function findObjectTranslation(
        string $objectType,
        string $objectId,
        string $field,
        string $targetAmz,
        string $sourceHash,
        bool $lockedOnly
    ): ?Translation {
        if ($objectType === '' || $objectType === 'string') {
            // Still allow string-scoped rows keyed by hash in object_id
            $objectId = $objectId !== '' ? $objectId : $sourceHash;
            $objectType = $objectType !== '' ? $objectType : 'string';
        }
        $q = Translation::find()->where([
            'object_type' => $objectType,
            'object_id' => (string)$objectId,
            'field' => $field,
            'target_language' => $targetAmz,
            'source_hash' => $sourceHash,
        ]);
        if ($lockedOnly) {
            $q->andWhere(['or', ['is_locked' => true], ['is_manual' => true]]);
        }
        return $q->one();
    }

    private function persistObject(
        string $objectType,
        string $objectId,
        string $field,
        string $sourceLanguage,
        string $targetLanguage,
        string $sourceText,
        string $sourceHash,
        string $translated,
        string $method,
        string $context
    ): void {
        $objectType = $objectType !== '' ? $objectType : 'string';
        $objectId = $objectId !== '' ? (string)$objectId : $sourceHash;
        $sourceAmz = LocaleMap::toAmazon($sourceLanguage);
        $targetAmz = LocaleMap::toAmazon($targetLanguage);
        $now = date('Y-m-d H:i:s');
        try {
            $row = Translation::findOne([
                'object_type' => $objectType,
                'object_id' => $objectId,
                'field' => $field,
                'target_language' => $targetAmz,
                'source_hash' => $sourceHash,
            ]);
            if ($row && $row->is_locked) {
                return;
            }
            if (!$row) {
                $row = new Translation([
                    'object_type' => $objectType,
                    'object_id' => $objectId,
                    'field' => $field,
                    'source_hash' => $sourceHash,
                    'created_at' => $now,
                ]);
            }
            $row->source_language = $sourceAmz;
            $row->target_language = $targetAmz;
            $row->source_text = $sourceText;
            $row->translated_text = $translated;
            $row->translation_method = $method;
            $row->translation_status = Translation::STATUS_MACHINE;
            $row->context = $context;
            $row->terminology_version = $this->settings->terminologyVersion;
            $row->updated_at = $now;
            $row->translated_at = $now;
            $row->save(false);

            // Mark older hashes for same object field as needs_update (not deleted)
            Translation::updateAll(
                ['translation_status' => Translation::STATUS_NEEDS_UPDATE, 'updated_at' => $now],
                [
                    'and',
                    ['object_type' => $objectType, 'object_id' => $objectId, 'field' => $field, 'target_language' => $targetAmz],
                    ['!=', 'source_hash', $sourceHash],
                    ['is_locked' => false],
                ]
            );
        } catch (\Throwable $e) {
            Yii::warning('Persist translation failed: ' . $e->getMessage(), 'thiscovery-translate');
        }
    }

    private function markFailed(
        string $objectType,
        string $objectId,
        string $field,
        string $sourceLanguage,
        string $targetLanguage,
        string $sourceText,
        string $sourceHash,
        string $context
    ): void {
        try {
            $this->persistObject($objectType, $objectId, $field, $sourceLanguage, $targetLanguage, $sourceText, $sourceHash, $sourceText, Translation::METHOD_AMAZON, $context);
            Translation::updateAll(
                ['translation_status' => Translation::STATUS_FAILED],
                [
                    'object_type' => $objectType !== '' ? $objectType : 'string',
                    'object_id' => $objectId !== '' ? $objectId : $sourceHash,
                    'field' => $field,
                    'target_language' => LocaleMap::toAmazon($targetLanguage),
                    'source_hash' => $sourceHash,
                ]
            );
        } catch (\Throwable $e) {
        }
    }

    private function isWorthTranslating(string $text): bool
    {
        $t = trim($text);
        if ($t === '' || mb_strlen($t) < 2) {
            return false;
        }
        if (preg_match('/^[\d\s\p{P}\p{S}]+$/u', $t)) {
            return false;
        }
        if (preg_match('#^https?://#i', $t)) {
            return false;
        }
        if (preg_match('/^[^\s]+@[^\s]+$/', $t)) {
            return false;
        }
        if (preg_match('/^[a-f0-9\-]{32,36}$/i', $t)) {
            return false;
        }
        return true;
    }

    private function looksLikeHtml(string $text): bool
    {
        return (bool)preg_match('/<[a-z][\s\S]*>/i', $text);
    }
}
