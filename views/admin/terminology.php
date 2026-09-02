<?php
/**
 * Admin shell wrapper added for left-nav + tabs.
 */
use humhub\modules\thiscoveryTranslate\assets\TranslateAsset;
use yii\helpers\Html as TtHtml;
use yii\helpers\Url as TtUrl;
TranslateAsset::register($this);
$activeTab = $activeTab ?? 'terminology';
?>
<div class="panel panel-default tt-admin">
    <div class="panel-heading">
        <strong>Thiscovery Translate</strong> — Terminology
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
use humhub\modules\thiscoveryTranslate\models\TranslationTerminology;
use humhub\modules\thiscoveryTranslate\services\LocaleMap;
use humhub\widgets\form\ActiveForm;
use yii\grid\GridView;
use yii\helpers\Url;

/** @var \yii\data\ActiveDataProvider $provider */
/** @var TranslationTerminology $model */

$langOptions = array_merge(['*' => Yii::t('ThiscoveryTranslateModule.base', 'All languages')], LocaleMap::labels());
?>
<?php $form = ActiveForm::begin(); ?>
    <div class="row">
        <div class="col-md-3"><?= $form->field($model, 'source_term')->textInput() ?></div>
        <div class="col-md-2"><?= $form->field($model, 'target_language')->dropDownList($langOptions) ?></div>
        <div class="col-md-3"><?= $form->field($model, 'preferred_translation')->textInput() ?></div>
        <div class="col-md-2"><?= $form->field($model, 'do_not_translate')->checkbox() ?></div>
        <div class="col-md-2"><?= $form->field($model, 'is_active')->checkbox() ?></div>
    </div>
    <?= $form->field($model, 'description')->textInput() ?>
    <?= Html::submitButton(Yii::t('ThiscoveryTranslateModule.base', 'Add term'), ['class' => 'btn btn-primary']) ?>
<?php ActiveForm::end(); ?>
<hr>
<?= GridView::widget([
    'dataProvider' => $provider,
    'columns' => [
        'source_term',
        'target_language',
        'preferred_translation',
        [
            'attribute' => 'do_not_translate',
            'value' => static fn($m) => $m->do_not_translate ? '✓' : '',
        ],
        [
            'attribute' => 'is_active',
            'value' => static fn($m) => $m->is_active ? '✓' : '',
        ],
        [
            'class' => \yii\grid\ActionColumn::class,
            'template' => '{delete}',
            'buttons' => [
                'delete' => static function ($url, $model) {
                    return Html::a(Yii::t('ThiscoveryTranslateModule.base', 'Delete'), Url::to(['terminology-delete', 'id' => $model->id]), [
                        'class' => 'btn btn-xs btn-danger',
                        'data-method' => 'POST',
                        'data-confirm' => Yii::t('ThiscoveryTranslateModule.base', 'Delete this term?'),
                    ]);
                },
            ],
        ],
    ],
]) ?>

    </div>
</div>
