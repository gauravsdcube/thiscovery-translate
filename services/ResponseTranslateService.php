<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\services;

use humhub\modules\thiscoveryTranslate\models\Translation;

/**
 * Optional evaluator translation of free-text responses — never overwrites originals.
 */
class ResponseTranslateService
{
    private TranslationService $service;

    public function __construct(?TranslationService $service = null)
    {
        $this->service = $service ?: new TranslationService();
    }

    public function translateResponse(
        int $answerFieldId,
        string $originalText,
        string $responseLanguage,
        string $targetLanguage = 'en-GB'
    ): string {
        if (!$this->service->isEnabled() || trim($originalText) === '') {
            return $originalText;
        }
        return $this->service->getTranslation(
            'form_answer_field',
            $answerFieldId,
            'value',
            $originalText,
            $targetLanguage,
            $responseLanguage !== '' ? $responseLanguage : $this->service->getSettings()->sourceLanguage,
            'survey',
            true,
            'thiscovery-forms'
        );
    }

    public function getCached(int $answerFieldId, string $targetLanguage): ?string
    {
        $row = Translation::find()
            ->where([
                'object_type' => 'form_answer_field',
                'object_id' => (string)$answerFieldId,
                'field' => 'value',
                'target_language' => LocaleMap::toAmazon($targetLanguage),
            ])
            ->andWhere(['not in', 'translation_status', [Translation::STATUS_FAILED, Translation::STATUS_NEEDS_UPDATE]])
            ->orderBy(['updated_at' => SORT_DESC])
            ->one();
        return $row ? (string)$row->translated_text : null;
    }
}
