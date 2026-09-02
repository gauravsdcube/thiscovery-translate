<?php
/**
 * Admin shell wrapper added for left-nav + tabs.
 */
use humhub\modules\thiscoveryTranslate\assets\TranslateAsset;
use yii\helpers\Html as TtHtml;
use yii\helpers\Url as TtUrl;
TranslateAsset::register($this);
$activeTab = $activeTab ?? 'memory';
?>
<div class="panel panel-default tt-admin">
    <div class="panel-heading">
        <strong>Thiscovery Translate</strong> — Translation Memory
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
use yii\grid\GridView;
use yii\helpers\Url;

/** @var \yii\data\ActiveDataProvider $provider */
/** @var array $languageOptions */
/** @var string $filterSource */
/** @var string $filterTarget */
/** @var string $filterQ */
/** @var bool $filterLeaked */

$languageOptions = $languageOptions ?? ['' => 'All'];
$filterSource = $filterSource ?? '';
$filterTarget = $filterTarget ?? '';
$filterQ = $filterQ ?? '';
$filterLeaked = !empty($filterLeaked);
?>
<form method="get" action="<?= Html::encode(Url::to(['/thiscovery-translate/admin/memory'])) ?>" class="form-inline tt-admin-filters" style="margin-bottom:1rem;display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end">
    <div class="form-group">
        <label for="tt-tm-source"><?= Yii::t('ThiscoveryTranslateModule.base', 'Source') ?></label><br>
        <?= Html::dropDownList('source_language', $filterSource, $languageOptions, ['class' => 'form-control', 'id' => 'tt-tm-source']) ?>
    </div>
    <div class="form-group">
        <label for="tt-tm-target"><?= Yii::t('ThiscoveryTranslateModule.base', 'Target') ?></label><br>
        <?= Html::dropDownList('target_language', $filterTarget, $languageOptions, ['class' => 'form-control', 'id' => 'tt-tm-target']) ?>
    </div>
    <div class="form-group">
        <label for="tt-tm-q"><?= Yii::t('ThiscoveryTranslateModule.base', 'Search') ?></label><br>
        <?= Html::textInput('q', $filterQ, ['class' => 'form-control', 'id' => 'tt-tm-q', 'placeholder' => 'ZTT / text…']) ?>
    </div>
    <div class="form-group" style="padding-bottom:.35rem">
        <label>
            <?= Html::checkbox('leaked', $filterLeaked, ['value' => '1']) ?>
            <?= Yii::t('ThiscoveryTranslateModule.base', 'Leaked placeholders only') ?>
        </label>
    </div>
    <div class="form-group">
        <button type="submit" class="btn btn-primary"><?= Yii::t('ThiscoveryTranslateModule.base', 'Filter') ?></button>
        <a class="btn btn-default" href="<?= Html::encode(Url::to(['/thiscovery-translate/admin/memory'])) ?>"><?= Yii::t('ThiscoveryTranslateModule.base', 'Reset') ?></a>
    </div>
</form>

<?= GridView::widget([
    'dataProvider' => $provider,
    'columns' => [
        'source_language',
        'target_language',
        'context',
        [
            'attribute' => 'source_text',
            'value' => static fn($m) => mb_strimwidth((string)$m->source_text, 0, 80, '…'),
        ],
        [
            'attribute' => 'translated_text',
            'format' => 'raw',
            'value' => static function ($m) {
                $text = (string)$m->translated_text;
                $leaked = \humhub\modules\thiscoveryTranslate\services\ContentProtector::looksLeaked($text);
                $body = Html::encode(mb_strimwidth($text, 0, 80, '…'));
                if ($leaked) {
                    return '<span class="text-danger" title="leaked">' . $body . '</span>';
                }
                return $body;
            },
        ],
        'usage_count',
        [
            'attribute' => 'is_verified',
            'value' => static fn($m) => $m->is_verified ? '✓' : '',
        ],
        [
            'class' => \yii\grid\ActionColumn::class,
            'template' => '{verify}',
            'buttons' => [
                'verify' => static function ($url, $model) {
                    if ($model->is_verified) {
                        return '';
                    }
                    return Html::a(Yii::t('ThiscoveryTranslateModule.base', 'Verify'), Url::to(['memory-verify', 'id' => $model->id]), [
                        'class' => 'btn btn-xs btn-primary',
                        'data-method' => 'POST',
                    ]);
                },
            ],
        ],
    ],
]) ?>

    </div>
</div>
