<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\jobs;

use humhub\modules\queue\ActiveJob;
use humhub\modules\thiscoveryTranslate\models\Translation;
use humhub\modules\thiscoveryTranslate\services\TranslationService;
use Yii;

class RetranslateChangedContentJob extends ActiveJob
{
    public string $objectType = '';
    public string $targetLanguage = '';

    public function run()
    {
        $svc = new TranslationService();
        if (!$svc->isEnabled()) {
            return;
        }
        $q = Translation::find()->where([
            'translation_status' => Translation::STATUS_NEEDS_UPDATE,
            'is_locked' => false,
        ]);
        if ($this->objectType !== '') {
            $q->andWhere(['object_type' => $this->objectType]);
        }
        if ($this->targetLanguage !== '') {
            $q->andWhere(['target_language' => $this->targetLanguage]);
        }
        foreach ($q->limit(200)->all() as $row) {
            $svc->getTranslation(
                $row->object_type,
                $row->object_id,
                $row->field,
                $row->source_text,
                $row->target_language,
                $row->source_language,
                $row->context ?: 'generic',
                true,
                $row->object_type
            );
        }
    }
}
