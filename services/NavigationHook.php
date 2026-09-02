<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\services;

use humhub\modules\thiscoveryTranslate\models\ModuleSettings;
use Yii;

/**
 * Translates custom Thiscovery Navigation labels stored in English in the DB.
 * Catalog fallbacks already go through Yii::t (UI assist); this covers non-empty admin labels.
 */
class NavigationHook
{
    public static function translateLabel(string $menuKey, string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return $label;
        }
        try {
            $settings = ModuleSettings::loadSettings();
            if (!$settings->siteTranslateEnabled) {
                return $label;
            }
            $target = Yii::$app->language;
            $source = $settings->sourceLanguage;
            if ($target === '' || LocaleMap::sameLanguage($target, $source)) {
                return $label;
            }

            // Short chrome: Redis/TM via UiAssist (same memory as language warm).
            if ($settings->uiMissingAssist && mb_strlen($label) <= 80 && !str_contains($label, "\n")) {
                $assist = new UiAssistService($settings);
                $cache = new UiStringCache();
                $cached = $cache->get($label, $source, $target, 'navigation');
                if ($cached !== null && $cached !== '') {
                    return $cached;
                }
                $fromTm = $assist->peekMemory($label, $target, $source);
                if ($fromTm !== null && $fromTm !== '') {
                    $cache->set($label, $fromTm, $source, $target, 'navigation');
                    return $fromTm;
                }
                $out = $assist->translate($label, $target, $source);
                if ($out !== '' && $out !== $label) {
                    // Also persist as menu object for admin review.
                    try {
                        (new TranslationService($settings))->getMenuLabel($menuKey, $label, $target, $source, false);
                    } catch (\Throwable $e) {
                        // ignore
                    }
                    return $out;
                }
            }

            return (new TranslationService($settings))->getMenuLabel($menuKey, $label, $target, $source, true);
        } catch (\Throwable $e) {
            Yii::warning('NavigationHook failed: ' . $e->getMessage(), 'thiscovery-translate');
            return $label;
        }
    }
}
