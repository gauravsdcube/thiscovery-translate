<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\widgets;

use humhub\components\Widget;
use humhub\modules\thiscoveryTranslate\services\LanguageService;
use Yii;

class LanguageSelector extends Widget
{
    public function run()
    {
        $langService = new LanguageService();
        $options = $langService->pickerOptions();
        if (count($options) < 2) {
            return '';
        }
        // Assets registered once via Events::onViewBeginPage (avoid View::EVENT_BEFORE_RENDER circular deps).
        $current = Yii::$app->language;
        if (!isset($options[$current])) {
            foreach (array_keys($options) as $code) {
                if (\humhub\modules\thiscoveryTranslate\services\LocaleMap::sameLanguage($code, $current)) {
                    $current = $code;
                    break;
                }
            }
            if (!isset($options[$current])) {
                $current = array_key_first($options);
            }
        }
        return $this->render('languageSelector', [
            'options' => $options,
            'current' => $current,
            'actionUrl' => ['/thiscovery-translate/language/set'],
        ]);
    }
}
