<?php
/**
 * Admin shell wrapper added for left-nav + tabs.
 */
use humhub\modules\thiscoveryTranslate\assets\TranslateAsset;
use yii\helpers\Html as TtHtml;
use yii\helpers\Url as TtUrl;
TranslateAsset::register($this);
$activeTab = $activeTab ?? 'maintenance';
?>
<div class="panel panel-default tt-admin">
    <div class="panel-heading">
        <strong>Thiscovery Translate</strong> — Maintenance
        <span class="pull-right">
            <a href="<?= TtHtml::encode(TtUrl::to(['/thiscovery-translate/admin/help'])) ?>">
                <i class="fa fa-question-circle" aria-hidden="true"></i>
                <?= Yii::t('ThiscoveryTranslateModule.base', 'Help') ?>
            </a>
        </span>
    </div>
    <div class="panel-body">
        <?= $this->render('_tabs', ['activeTab' => $activeTab]) ?>

<?php

use humhub\helpers\Html;
use yii\helpers\Url;
?>
<div class="alert alert-warning">
    <?= Yii::t('ThiscoveryTranslateModule.base', 'Dangerous bulk actions require confirmation. Leaked locked/manual rows are deleted because they are corrupt.') ?>
</div>
<p>
    <?= Html::a(
        Yii::t('ThiscoveryTranslateModule.base', 'Purge leaked placeholders (ZZTT / ZTT)'),
        Url::to(['purge-leaked']),
        [
            'class' => 'btn btn-danger',
            'data-method' => 'POST',
            'data-confirm' => Yii::t('ThiscoveryTranslateModule.base', 'Delete translations (including locked/manual if leaked), TM rows, and form i18n overlays that still contain protector placeholders? Pages will retranslate on next view.'),
        ]
    ) ?>
</p>
<p>
    <?= Html::a(
        Yii::t('ThiscoveryTranslateModule.base', 'Clear unlocked machine translations'),
        Url::to(['clear-machine']),
        [
            'class' => 'btn btn-danger',
            'data-method' => 'POST',
            'data-confirm' => Yii::t('ThiscoveryTranslateModule.base', 'Delete all unlocked Amazon machine translations?'),
        ]
    ) ?>
</p>
<p>
    <?= Html::a(
        Yii::t('ThiscoveryTranslateModule.base', 'Bump terminology version'),
        Url::to(['bump-terminology']),
        [
            'class' => 'btn btn-default',
            'data-method' => 'POST',
            'data-confirm' => Yii::t('ThiscoveryTranslateModule.base', 'Increment terminology version? Content will not retranslate automatically.'),
        ]
    ) ?>
</p>
<p>
    <?= Html::a(
        Yii::t('ThiscoveryTranslateModule.base', 'Regenerate stale translations'),
        Url::to(['regenerate-stale']),
        [
            'class' => 'btn btn-primary',
            'data-method' => 'POST',
            'data-confirm' => Yii::t('ThiscoveryTranslateModule.base', 'Queue regeneration for unlocked rows marked needs_update?'),
        ]
    ) ?>
</p>
<p class="text-muted">
    <?= Yii::t('ThiscoveryTranslateModule.base', 'After bumping terminology, use Forms “Generate translation” or content regenerate jobs for affected items only.') ?>
</p>

    </div>
</div>
