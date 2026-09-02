<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\services;

use humhub\modules\thiscoveryTranslate\models\ModuleSettings;
use humhub\modules\thiscoveryTranslate\models\TranslationUsage;
use Yii;

class CostTracker
{
    public function record(
        string $hitKind,
        bool $cacheHit,
        int $chars,
        string $sourceLanguage,
        string $targetLanguage,
        string $provider = 'none',
        string $requestType = 'lookup',
        ?string $module = null,
        ?string $objectType = null
    ): void {
        // Avoid flooding usage table on every Yii::t / TM hit — only persist billable / notable events.
        $notable = in_array($provider, ['amazon'], true)
            || in_array($requestType, ['translate', 'error'], true)
            || in_array($hitKind, ['amazon', 'failed', 'budget'], true);
        if (!$notable) {
            return;
        }
        try {
            $row = new TranslationUsage([
                'created_at' => date('Y-m-d H:i:s'),
                'module' => $module,
                'object_type' => $objectType,
                'source_language' => LocaleMap::toAmazon($sourceLanguage),
                'target_language' => LocaleMap::toAmazon($targetLanguage),
                'character_count' => max(0, $chars),
                'provider' => $provider,
                'request_type' => $requestType,
                'cache_hit' => $cacheHit,
                'hit_kind' => $hitKind,
            ]);
            $row->save(false);
        } catch (\Throwable $e) {
            // never break translation path
        }

        if ($provider === 'amazon' && $chars > 0) {
            $this->addMonthlyChars($chars);
        }
    }

    public function monthlyCharsUsed(): int
    {
        return (int)Yii::$app->cache->get($this->monthKey());
    }

    public function withinHardLimit(int $additionalChars, ModuleSettings $settings): bool
    {
        $limit = (int)$settings->monthlyCharHardLimit;
        if ($limit <= 0) {
            return true;
        }
        return ($this->monthlyCharsUsed() + $additionalChars) <= $limit;
    }

    public function pastWarning(ModuleSettings $settings): bool
    {
        $warn = (int)$settings->monthlyCharWarning;
        if ($warn <= 0) {
            return false;
        }
        return $this->monthlyCharsUsed() >= $warn;
    }

    private function addMonthlyChars(int $chars): void
    {
        $key = $this->monthKey();
        $used = (int)Yii::$app->cache->get($key);
        Yii::$app->cache->set($key, $used + $chars, 86400 * 40);
    }

    private function monthKey(): string
    {
        return 'thiscovery-translate-month-chars:' . gmdate('Y-m');
    }
}
