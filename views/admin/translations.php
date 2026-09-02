<?php
/**
 * Admin shell wrapper added for left-nav + tabs.
 */
use humhub\modules\thiscoveryTranslate\assets\TranslateAsset;
use yii\helpers\Html as TtHtml;
use yii\helpers\Url as TtUrl;
TranslateAsset::register($this);
$activeTab = $activeTab ?? 'translations';
?>
<div class="panel panel-default tt-admin">
    <div class="panel-heading">
        <strong>Thiscovery Translate</strong> — Translations
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
/** @var string $filterObjectType */
/** @var string[] $objectTypeOptions */

$languageOptions = $languageOptions ?? ['' => 'All'];
$filterSource = $filterSource ?? '';
$filterTarget = $filterTarget ?? '';
$filterQ = $filterQ ?? '';
$filterLeaked = !empty($filterLeaked);
$filterObjectType = $filterObjectType ?? '';
$objectTypeOptions = $objectTypeOptions ?? [];
$objectTypeSelect = ['' => Yii::t('ThiscoveryTranslateModule.base', 'All types')];
foreach ($objectTypeOptions as $ot) {
    $objectTypeSelect[(string)$ot] = (string)$ot;
}
?>
<form method="get" action="<?= Html::encode(Url::to(['/thiscovery-translate/admin/translations'])) ?>" class="form-inline tt-admin-filters" style="margin-bottom:1rem;display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end">
    <div class="form-group">
        <label for="tt-tr-source"><?= Yii::t('ThiscoveryTranslateModule.base', 'Source') ?></label><br>
        <?= Html::dropDownList('source_language', $filterSource, $languageOptions, ['class' => 'form-control', 'id' => 'tt-tr-source']) ?>
    </div>
    <div class="form-group">
        <label for="tt-tr-target"><?= Yii::t('ThiscoveryTranslateModule.base', 'Target') ?></label><br>
        <?= Html::dropDownList('target_language', $filterTarget, $languageOptions, ['class' => 'form-control', 'id' => 'tt-tr-target']) ?>
    </div>
    <div class="form-group">
        <label for="tt-tr-type"><?= Yii::t('ThiscoveryTranslateModule.base', 'Object type') ?></label><br>
        <?= Html::dropDownList('object_type', $filterObjectType, $objectTypeSelect, ['class' => 'form-control', 'id' => 'tt-tr-type']) ?>
    </div>
    <div class="form-group">
        <label for="tt-tr-q"><?= Yii::t('ThiscoveryTranslateModule.base', 'Search') ?></label><br>
        <?= Html::textInput('q', $filterQ, ['class' => 'form-control', 'id' => 'tt-tr-q', 'placeholder' => 'ZTT / text…']) ?>
    </div>
    <div class="form-group" style="padding-bottom:.35rem">
        <label>
            <?= Html::checkbox('leaked', $filterLeaked, ['value' => '1']) ?>
            <?= Yii::t('ThiscoveryTranslateModule.base', 'Leaked placeholders only') ?>
        </label>
    </div>
    <div class="form-group">
        <button type="submit" class="btn btn-primary"><?= Yii::t('ThiscoveryTranslateModule.base', 'Filter') ?></button>
        <a class="btn btn-default" href="<?= Html::encode(Url::to(['/thiscovery-translate/admin/translations'])) ?>"><?= Yii::t('ThiscoveryTranslateModule.base', 'Reset') ?></a>
    </div>
</form>

<?= GridView::widget([
    'dataProvider' => $provider,
    'filterSelector' => '.tt-admin-filters input, .tt-admin-filters select',
    'columns' => [
        'object_type',
        'object_id',
        'field',
        'source_language',
        'target_language',
        [
            'attribute' => 'source_text',
            'value' => static fn($m) => mb_strimwidth((string)$m->source_text, 0, 60, '…'),
        ],
        [
            'attribute' => 'translated_text',
            'format' => 'raw',
            'value' => static function ($m) {
                $leaked = \humhub\modules\thiscoveryTranslate\services\ContentProtector::looksLeaked((string)$m->translated_text);
                $style = $leaked ? 'border-color:#c0392b;background:#fdecea' : '';
                return Html::beginForm(Url::to(['translation-lock', 'id' => $m->id]), 'post')
                    . ($leaked ? '<div class="text-danger" style="font-size:12px;margin-bottom:4px">' . Html::encode(Yii::t('ThiscoveryTranslateModule.base', 'Contains leaked placeholders')) . '</div>' : '')
                    . Html::textarea('translated_text', $m->translated_text, ['rows' => 2, 'class' => 'form-control', 'style' => $style])
                    . Html::submitButton(Yii::t('ThiscoveryTranslateModule.base', 'Lock'), ['class' => 'btn btn-xs btn-primary mt-1'])
                    . Html::endForm();
            },
        ],
        'translation_method',
        'translation_status',
        [
            'attribute' => 'is_locked',
            'value' => static fn($m) => $m->is_locked ? '✓' : '',
        ],
    ],
]) ?>

    </div>
</div>
