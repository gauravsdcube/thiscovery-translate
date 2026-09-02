<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\assets;

use yii\web\AssetBundle;
use yii\web\JqueryAsset;
use yii\web\View;

class TranslateAsset extends AssetBundle
{
    public $sourcePath = '@thiscovery-translate/resources';

    public $css = [
        'css/thiscovery-translate.css',
    ];

    public $js = [
        'js/thiscovery-translate.js',
    ];

    public $jsOptions = [
        'position' => View::POS_END,
    ];

    public $depends = [
        JqueryAsset::class,
    ];
}
