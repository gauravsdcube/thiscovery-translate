<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\services;

use humhub\modules\thiscoveryTranslate\models\TranslationMemoryEntry;
use Yii;

class TranslationMemoryService
{
    public function hash(string $text, string $sourceLanguage, string $context = 'generic'): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
        return hash('sha256', $normalized . "\n" . LocaleMap::toAmazon($sourceLanguage) . "\n" . $context);
    }

    public function find(string $text, string $sourceLanguage, string $targetLanguage, string $context = 'generic'): ?string
    {
        $hash = $this->hash($text, $sourceLanguage, $context);
        $sourceAmz = LocaleMap::toAmazon($sourceLanguage);
        $targetAmz = LocaleMap::toAmazon($targetLanguage);

        $row = TranslationMemoryEntry::findOne([
            'source_language' => $sourceAmz,
            'target_language' => $targetAmz,
            'source_hash' => $hash,
            'context' => $context,
        ]);
        if (!$row && $context !== 'generic') {
            $row = TranslationMemoryEntry::findOne([
                'source_language' => $sourceAmz,
                'target_language' => $targetAmz,
                'source_hash' => $this->hash($text, $sourceLanguage, 'generic'),
                'context' => 'generic',
            ]);
        }
        if (!$row) {
            return null;
        }
        // Skip usage_count write on hot path — avoids a DB UPDATE per Yii::t.
        return (string)$row->translated_text;
    }

    public function remember(
        string $text,
        string $translated,
        string $sourceLanguage,
        string $targetLanguage,
        string $context = 'generic',
        string $method = 'amazon',
        bool $verified = false
    ): void {
        $sourceAmz = LocaleMap::toAmazon($sourceLanguage);
        $targetAmz = LocaleMap::toAmazon($targetLanguage);
        $hash = $this->hash($text, $sourceLanguage, $context);
        $now = date('Y-m-d H:i:s');
        $row = TranslationMemoryEntry::findOne([
            'source_language' => $sourceAmz,
            'target_language' => $targetAmz,
            'source_hash' => $hash,
            'context' => $context,
        ]);
        if (!$row) {
            $row = new TranslationMemoryEntry([
                'source_language' => $sourceAmz,
                'target_language' => $targetAmz,
                'source_hash' => $hash,
                'context' => $context,
                'usage_count' => 0,
                'created_at' => $now,
            ]);
        }
        if ($row->is_verified && !$verified) {
            // Do not overwrite verified TM with fresh machine text
            $row->usage_count = ((int)$row->usage_count) + 1;
            $row->updated_at = $now;
            $row->save(false);
            return;
        }
        $row->source_text = $text;
        $row->translated_text = $translated;
        $row->translation_method = $method;
        $row->is_verified = $verified;
        $row->usage_count = ((int)$row->usage_count) + 1;
        $row->updated_at = $now;
        try {
            $row->save(false);
        } catch (\Throwable $e) {
            Yii::warning('TM save failed: ' . $e->getMessage(), 'thiscovery-translate');
        }
    }
}
