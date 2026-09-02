<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\modules\thiscoveryTranslate\Module;
use humhub\modules\thiscoveryTranslate\services\TranslationService;

/**
 * Convenience accessor for other Thiscovery modules.
 */
function translation(): TranslationService
{
    $module = \Yii::$app->getModule('thiscovery-translate');
    if ($module instanceof Module) {
        return $module->getTranslationService();
    }
    return new TranslationService();
}
