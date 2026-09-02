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
?>
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
            'value' => static fn($m) => mb_strimwidth((string)$m->translated_text, 0, 80, '…'),
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
