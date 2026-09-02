<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\helpers\Html;
use humhub\modules\thiscoveryTranslate\assets\TranslateAsset;
use humhub\modules\thiscoveryTranslate\models\ModuleSettings;
use humhub\widgets\form\ActiveForm;
use yii\helpers\Url;

/** @var ModuleSettings $model */
/** @var array $languageCatalog */
/** @var string $activeTab */

TranslateAsset::register($this);
$this->title = Yii::t('ThiscoveryTranslateModule.base', 'Thiscovery Translate');
$guide = function (string $text) {
    return $this->render('_setting_guide', ['text' => $text]);
};
?>
<div class="panel panel-default tt-admin">
    <div class="panel-heading">
        <?= Yii::t('ThiscoveryTranslateModule.base', '<strong>Thiscovery Translate</strong> module configuration') ?>
        <span class="pull-right">
            <a href="<?= Html::encode(Url::to(['/thiscovery-translate/admin/help'])) ?>">
                <i class="fa fa-question-circle" aria-hidden="true"></i>
                <?= Yii::t('ThiscoveryTranslateModule.base', 'Help') ?>
            </a>
        </span>
    </div>
    <div class="panel-body">
        <?= $this->render('_tabs', ['activeTab' => $activeTab ?? 'settings']) ?>

        <p class="tt-studio__hint">
            <?= Yii::t('ThiscoveryTranslateModule.base', 'Settings are grouped into collapsible sections. Click ? next to a label for a short explanation. Open the Help tab for the full guide.') ?>
        </p>
        <div class="tt-set-toolbar">
            <button type="button" class="btn btn-sm btn-light" data-tt-acc-all="open"><?= Yii::t('ThiscoveryTranslateModule.base', 'Expand all') ?></button>
            <button type="button" class="btn btn-sm btn-light" data-tt-acc-all="close"><?= Yii::t('ThiscoveryTranslateModule.base', 'Collapse all') ?></button>
        </div>

        <?php $form = ActiveForm::begin(['options' => ['class' => 'tt-admin-form']]); ?>

        <details class="tt-set-acc" open>
            <summary>
                <span class="tt-set-acc__title"><?= Yii::t('ThiscoveryTranslateModule.base', 'Features') ?></span>
                <span class="tt-set-acc__summary"><?= Yii::t('ThiscoveryTranslateModule.base', 'Site-wide and Forms translation switches') ?></span>
            </summary>
            <div class="tt-set-acc__body">
                <p class="tt-set-acc__intro">
                    <?= Yii::t('ThiscoveryTranslateModule.base', 'Turn site-wide and Forms translation on independently. Export/import of form translation files always stays available in Forms.') ?>
                </p>
                <div class="tt-field">
                    <?= $form->field($model, 'siteTranslateEnabled')->checkbox() ?>
                    <?= $guide(Yii::t('ThiscoveryTranslateModule.base', 'Shows the language picker and translates menus, page builder content, and stream posts when enabled.')) ?>
                </div>
                <div class="tt-field">
                    <?= $form->field($model, 'formsTranslateEnabled')->checkbox() ?>
                    <?= $guide(Yii::t('ThiscoveryTranslateModule.base', 'Enables Amazon machine translation inside Forms studio. Manual editing and CSV/JSON import still work when this is off.')) ?>
                </div>
            </div>
        </details>

        <details class="tt-set-acc" open>
            <summary>
                <span class="tt-set-acc__title"><?= Yii::t('ThiscoveryTranslateModule.base', 'Languages & AWS') ?></span>
                <span class="tt-set-acc__summary"><?= Yii::t('ThiscoveryTranslateModule.base', 'Region, source language, enabled locales') ?></span>
            </summary>
            <div class="tt-set-acc__body">
                <?= $form->field($model, 'awsRegion')->textInput(['maxlength' => 64])
                    ->hint(Yii::t('ThiscoveryTranslateModule.base', 'Uses the EC2 instance IAM role. Default eu-west-2 (London).')) ?>
                <div class="tt-field">
                    <?= $form->field($model, 'sourceLanguage')->dropDownList($languageCatalog) ?>
                    <?= $guide(Yii::t('ThiscoveryTranslateModule.base', 'Language of original content. Amazon Translate treats this as the source for machine translation.')) ?>
                </div>
                <div class="tt-field">
                    <label class="control-label"><?= Yii::t('ThiscoveryTranslateModule.base', 'Enabled languages') ?></label>
                    <?= $guide(Yii::t('ThiscoveryTranslateModule.base', 'Tick every language the site and Forms may use. The list matches Amazon Translate. Forms Settings mirrors this list when Forms translation is on.')) ?>
                    <p class="help-block">
                        <?= Yii::t('ThiscoveryTranslateModule.base', 'Full Amazon Translate catalogue ({n} locales). Use search in your browser (Ctrl/Cmd+F) to jump to a language.', [
                            'n' => count($languageCatalog),
                        ]) ?>
                    </p>
                    <div class="tt-lang-grid">
                        <?= $form->field($model, 'availableLanguages')->checkboxList($languageCatalog, [
                            'item' => static function ($index, $label, $name, $checked, $value) {
                                return '<label class="tt-lang-check">'
                                    . Html::checkbox($name, $checked, ['value' => $value, 'uncheck' => null])
                                    . '<span class="tt-lang-check__label">' . Html::encode($label) . '</span>'
                                    . '<code class="tt-lang-check__code">' . Html::encode($value) . '</code>'
                                    . '</label>';
                            },
                            'tag' => 'div',
                            'class' => 'tt-lang-grid__inner',
                        ])->label(false) ?>
                    </div>
                </div>
            </div>
        </details>

        <details class="tt-set-acc">
            <summary>
                <span class="tt-set-acc__title"><?= Yii::t('ThiscoveryTranslateModule.base', 'Site-wide options') ?></span>
                <span class="tt-set-acc__summary"><?= Yii::t('ThiscoveryTranslateModule.base', 'Content, UI assist, disclaimer') ?></span>
            </summary>
            <div class="tt-set-acc__body">
                <p class="tt-set-acc__intro">
                    <?= Yii::t('ThiscoveryTranslateModule.base', 'Applied when site-wide translation is enabled.') ?>
                </p>
                <?= $form->field($model, 'contentTranslate')->checkbox() ?>
                <?= $form->field($model, 'uiMissingAssist')->checkbox() ?>
                <?= $form->field($model, 'showDisclaimer')->checkbox() ?>
            </div>
        </details>

        <details class="tt-set-acc">
            <summary>
                <span class="tt-set-acc__title"><?= Yii::t('ThiscoveryTranslateModule.base', 'Forms options') ?></span>
                <span class="tt-set-acc__summary"><?= Yii::t('ThiscoveryTranslateModule.base', 'Publish behaviour for incomplete overlays') ?></span>
            </summary>
            <div class="tt-set-acc__body">
                <p class="tt-set-acc__intro">
                    <?= Yii::t('ThiscoveryTranslateModule.base', 'Applied when Forms translation is enabled.') ?>
                </p>
                <?= $form->field($model, 'formsPublishMode')->dropDownList([
                    'warn' => Yii::t('ThiscoveryTranslateModule.base', 'Warn only'),
                    'block' => Yii::t('ThiscoveryTranslateModule.base', 'Prevent publishing incomplete translations'),
                ]) ?>
            </div>
        </details>

        <details class="tt-set-acc">
            <summary>
                <span class="tt-set-acc__title"><?= Yii::t('ThiscoveryTranslateModule.base', 'Budgets') ?></span>
                <span class="tt-set-acc__summary"><?= Yii::t('ThiscoveryTranslateModule.base', 'Character limits and cost estimate') ?></span>
            </summary>
            <div class="tt-set-acc__body">
                <?= $form->field($model, 'dailyCharBudget')->input('number', ['min' => 0]) ?>
                <?= $form->field($model, 'monthlyCharWarning')->input('number', ['min' => 0]) ?>
                <?= $form->field($model, 'monthlyCharHardLimit')->input('number', ['min' => 0]) ?>
                <?= $form->field($model, 'estimatedCostPerMillion')->input('number', ['min' => 0, 'step' => '0.01']) ?>
            </div>
        </details>

        <div class="form-group tt-admin-actions">
            <?= Html::submitButton(Yii::t('ThiscoveryTranslateModule.base', 'Save'), ['class' => 'btn btn-primary']) ?>
            <a class="btn btn-default" data-method="POST" href="<?= Url::to(['test']) ?>">
                <?= Yii::t('ThiscoveryTranslateModule.base', 'Test Amazon Translate') ?>
            </a>
            <a class="btn btn-link" href="<?= Url::to(['help']) ?>">
                <i class="fa fa-question-circle" aria-hidden="true"></i>
                <?= Yii::t('ThiscoveryTranslateModule.base', 'Open Help') ?>
            </a>
        </div>
        <?php ActiveForm::end(); ?>
    </div>
</div>
