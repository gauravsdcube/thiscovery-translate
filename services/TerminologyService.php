<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\services;

use humhub\modules\thiscoveryTranslate\models\ModuleSettings;
use humhub\modules\thiscoveryTranslate\models\TranslationTerminology;
use Yii;

class TerminologyService
{
    /** @return string[] */
    public function doNotTranslateTerms(): array
    {
        try {
            return TranslationTerminology::find()
                ->select('source_term')
                ->where(['is_active' => true, 'do_not_translate' => true])
                ->column();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Preferred replacements for a target language (applied after machine translate if needed).
     * @return array<string, string> source => preferred
     */
    public function preferredMap(string $targetLanguage): array
    {
        $targetAmz = LocaleMap::toAmazon($targetLanguage);
        try {
            $rows = TranslationTerminology::find()
                ->where(['is_active' => true, 'do_not_translate' => false])
                ->andWhere(['or', ['target_language' => $targetAmz], ['target_language' => '*']])
                ->andWhere(['not', ['preferred_translation' => null]])
                ->andWhere(['<>', 'preferred_translation', ''])
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
        $map = [];
        foreach ($rows as $row) {
            $map[(string)$row->source_term] = (string)$row->preferred_translation;
        }
        return $map;
    }

    public function applyPreferred(string $text, string $targetLanguage): string
    {
        foreach ($this->preferredMap($targetLanguage) as $source => $preferred) {
            $text = str_replace($source, $preferred, $text);
        }
        return $text;
    }
}
