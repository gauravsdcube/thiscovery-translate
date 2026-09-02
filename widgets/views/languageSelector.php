<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\helpers\Html;
use yii\helpers\Url;

/** @var array<string, string> $options */
/** @var string $current */
/** @var array|string $actionUrl */
?>
<div class="tt-lang-picker notranslate" translate="no" data-tt-lang-picker>
    <form class="tt-lang-picker__form" action="<?= Html::encode(Url::to($actionUrl)) ?>" method="post">
        <input type="hidden" name="<?= Html::encode(Yii::$app->request->csrfParam) ?>" value="<?= Html::encode(Yii::$app->request->getCsrfToken()) ?>">
        <label class="visually-hidden" for="tt-lang-select"><?= Yii::t('ThiscoveryTranslateModule.base', 'Language') ?></label>
        <select id="tt-lang-select" name="language" class="form-select form-select-sm tt-lang-picker__select"
                aria-label="<?= Html::encode(Yii::t('ThiscoveryTranslateModule.base', 'Language')) ?>">
            <?php foreach ($options as $code => $label): ?>
                <option value="<?= Html::encode($code) ?>" <?= $code === $current ? 'selected' : '' ?> lang="<?= Html::encode($code) ?>">
                    <?= Html::encode($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>
