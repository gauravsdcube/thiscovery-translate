<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\services;

use Yii;

/**
 * Fast Redis cache for UI chrome strings (menus / Yii::t assist).
 */
class UiStringCache
{
    private const TTL = 86400 * 14;

    public function get(string $text, string $sourceLanguage, string $targetLanguage, string $context = 'navigation'): ?string
    {
        try {
            $val = Yii::$app->cache->get($this->key($text, $sourceLanguage, $targetLanguage, $context));
            return is_string($val) && $val !== '' ? $val : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function set(string $text, string $translated, string $sourceLanguage, string $targetLanguage, string $context = 'navigation'): void
    {
        try {
            Yii::$app->cache->set(
                $this->key($text, $sourceLanguage, $targetLanguage, $context),
                $translated,
                self::TTL
            );
        } catch (\Throwable $e) {
        }
    }

    private function key(string $text, string $sourceLanguage, string $targetLanguage, string $context): string
    {
        $hash = hash('sha256', trim($text) . "\n" . LocaleMap::toAmazon($sourceLanguage) . "\n" . LocaleMap::toAmazon($targetLanguage) . "\n" . $context);
        return 'tt:ui:' . $hash;
    }
}
