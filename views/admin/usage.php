<?php
/**
 * Admin shell wrapper added for left-nav + tabs.
 */
use humhub\modules\thiscoveryTranslate\assets\TranslateAsset;
use yii\helpers\Html as TtHtml;
use yii\helpers\Url as TtUrl;
TranslateAsset::register($this);
$activeTab = $activeTab ?? 'usage';
?>
<div class="panel panel-default tt-admin">
    <div class="panel-heading">
        <strong>Thiscovery Translate</strong> — Usage
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

/** @var int $requests */
/** @var int $awsRequests */
/** @var int $avoided */
/** @var float $hitRate */
/** @var int $awsChars */
/** @var float $estimatedCost */
/** @var int $monthlyChars */
/** @var \humhub\modules\thiscoveryTranslate\models\ModuleSettings $settings */
/** @var bool $pastWarning */
?>
<?php if ($pastWarning): ?>
    <div class="alert alert-warning">
        <?= Yii::t('ThiscoveryTranslateModule.base', 'Monthly character warning threshold reached.') ?>
    </div>
<?php endif; ?>
<table class="table">
    <tr><th><?= Yii::t('ThiscoveryTranslateModule.base', 'Translation requests (month)') ?></th><td><?= (int)$requests ?></td></tr>
    <tr><th><?= Yii::t('ThiscoveryTranslateModule.base', 'Amazon requests') ?></th><td><?= (int)$awsRequests ?></td></tr>
    <tr><th><?= Yii::t('ThiscoveryTranslateModule.base', 'AWS requests avoided') ?></th><td><?= (int)$avoided ?></td></tr>
    <tr><th><?= Yii::t('ThiscoveryTranslateModule.base', 'Cache / memory hit rate') ?></th><td><?= htmlspecialchars((string)$hitRate) ?>%</td></tr>
    <tr><th><?= Yii::t('ThiscoveryTranslateModule.base', 'Amazon characters (month)') ?></th><td><?= (int)$awsChars ?></td></tr>
    <tr><th><?= Yii::t('ThiscoveryTranslateModule.base', 'Tracked monthly characters') ?></th><td><?= (int)$monthlyChars ?></td></tr>
    <tr><th><?= Yii::t('ThiscoveryTranslateModule.base', 'Estimated cost (USD)') ?></th><td><?= htmlspecialchars(number_format((float)$estimatedCost, 2)) ?></td></tr>
</table>
<p class="text-muted">
    <?= Yii::t('ThiscoveryTranslateModule.base', 'Estimates use the configurable rate of {rate} USD per million characters.', [
        'rate' => $settings->estimatedCostPerMillion,
    ]) ?>
</p>

    </div>
</div>
