<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\jobs;

use humhub\modules\queue\ActiveJob;
use humhub\modules\thiscoveryTranslate\services\TranslationService;
use Yii;

class TranslateContentJob extends ActiveJob
{
    public string $objectType;
    public string $objectId;
    public string $field;
    public string $sourceText;
    public string $sourceLanguage;
    public string $targetLanguage;
    public string $context = 'generic';
    public ?string $module = null;

    public function run()
    {
        $svc = new TranslationService();
        if (!$svc->isEnabled()) {
            return;
        }
        $svc->getTranslation(
            $this->objectType,
            $this->objectId,
            $this->field,
            $this->sourceText,
            $this->targetLanguage,
            $this->sourceLanguage,
            $this->context,
            true,
            $this->module
        );
    }
}
