<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\modules\thiscoveryTranslate\assets\TranslateAsset;
use humhub\modules\thiscoveryTranslate\services\LocaleMap;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var \humhub\modules\thiscoveryTranslate\models\ModuleSettings $model */
/** @var array $catalog */
/** @var array $natives */
/** @var string $activeTab */

TranslateAsset::register($this);
$this->title = Yii::t('ThiscoveryTranslateModule.base', 'Thiscovery Translate — Languages');
?>
<div class="panel panel-default tt-admin">
    <div class="panel-heading">
        <?= Yii::t('ThiscoveryTranslateModule.base', '<strong>Thiscovery Translate</strong> languages') ?>
        <span class="pull-right">
            <a href="<?= Html::encode(Url::to(['/thiscovery-translate/admin/help'])) ?>">
                <i class="fa fa-question-circle" aria-hidden="true"></i>
                <?= Yii::t('ThiscoveryTranslateModule.base', 'Help') ?>
            </a>
        </span>
    </div>
    <div class="panel-body">
        <?= $this->render('_tabs', ['activeTab' => $activeTab ?? 'languages']) ?>
        <p class="help-block">
            <?= Yii::t('ThiscoveryTranslateModule.base', 'Enabled languages are configured on the Settings tab. This table shows Amazon codes, native names, and RTL.') ?>
        </p>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                <tr>
                    <th><?= Yii::t('ThiscoveryTranslateModule.base', 'Code') ?></th>
                    <th><?= Yii::t('ThiscoveryTranslateModule.base', 'English') ?></th>
                    <th><?= Yii::t('ThiscoveryTranslateModule.base', 'Native') ?></th>
                    <th><?= Yii::t('ThiscoveryTranslateModule.base', 'Amazon') ?></th>
                    <th><?= Yii::t('ThiscoveryTranslateModule.base', 'RTL') ?></th>
                    <th><?= Yii::t('ThiscoveryTranslateModule.base', 'Enabled') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($catalog as $code => [$amz, $label]): ?>
                    <tr>
                        <td><code><?= Html::encode($code) ?></code></td>
                        <td><?= Html::encode($label) ?></td>
                        <td lang="<?= Html::encode($code) ?>"><?= Html::encode($natives[$code] ?? $label) ?></td>
                        <td><code><?= Html::encode($amz) ?></code></td>
                        <td><?= LocaleMap::isRtl($code) ? Yii::t('ThiscoveryTranslateModule.base', 'Yes') : Yii::t('ThiscoveryTranslateModule.base', 'No') ?></td>
                        <td><?= in_array($code, $model->availableLanguages, true) ? '✓' : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
