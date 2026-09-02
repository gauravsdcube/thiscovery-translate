<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\jobs;

use humhub\modules\queue\ActiveJob;
use humhub\modules\thiscoveryTranslate\models\ModuleSettings;
use humhub\modules\thiscoveryTranslate\services\LocaleMap;
use humhub\modules\thiscoveryTranslate\services\UiAssistService;
use Yii;

/**
 * Pre-translates common UI chrome strings into TM/Redis after a language switch.
 */
class WarmUiLanguageJob extends ActiveJob
{
    public string $targetLanguage = '';
    public string $sourceLanguage = '';
    /** @var string[] */
    public array $extraPhrases = [];

    public function run()
    {
        $settings = ModuleSettings::loadSettings();
        if (!$settings->siteTranslateEnabled || $this->targetLanguage === '') {
            return;
        }
        $source = $this->sourceLanguage !== '' ? $this->sourceLanguage : $settings->sourceLanguage;
        if (LocaleMap::sameLanguage($source, $this->targetLanguage)) {
            return;
        }
        $phrases = array_values(array_unique(array_merge(UiAssistService::seedPhrases(), $this->extraPhrases)));
        $stats = (new UiAssistService($settings))->warm($phrases, $this->targetLanguage, $source);
        Yii::info('WarmUiLanguageJob ' . $this->targetLanguage . ' ' . json_encode($stats), 'thiscovery-translate');
    }
}
