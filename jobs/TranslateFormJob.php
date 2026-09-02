<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\jobs;

use humhub\modules\queue\ActiveJob;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryTranslate\services\FormTranslateAdapter;
use Yii;

class TranslateFormJob extends ActiveJob
{
    public int $formId;
    public ?string $onlyLanguage = null;

    public function run()
    {
        if (!class_exists(CustomForm::class)) {
            return;
        }
        $form = CustomForm::findOne($this->formId);
        if (!$form) {
            return;
        }
        $stats = (new FormTranslateAdapter())->translateForm($form, $this->onlyLanguage);
        Yii::info('TranslateFormJob form=' . $this->formId . ' ' . json_encode($stats), 'thiscovery-translate');
    }
}
