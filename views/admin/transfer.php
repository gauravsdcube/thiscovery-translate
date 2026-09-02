<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\helpers\Html;
use humhub\modules\thiscoveryTranslate\assets\TranslateAsset;
use yii\helpers\Url;

/** @var string $activeTab */

TranslateAsset::register($this);
$this->title = Yii::t('ThiscoveryTranslateModule.base', 'Thiscovery Translate — Export / Import');
?>
<div class="panel panel-default tt-admin">
    <div class="panel-heading">
        <?= Yii::t('ThiscoveryTranslateModule.base', '<strong>Thiscovery Translate</strong> — Export / Import') ?>
        <span class="pull-right">
            <a href="<?= Html::encode(Url::to(['/thiscovery-translate/admin/help'])) ?>">
                <i class="fa fa-question-circle" aria-hidden="true"></i>
                <?= Yii::t('ThiscoveryTranslateModule.base', 'Help') ?>
            </a>
        </span>
    </div>
    <div class="panel-body">
        <?= $this->render('_tabs', ['activeTab' => $activeTab ?? 'transfer']) ?>

        <div class="alert alert-info">
            <ul class="mb-0" style="padding-left:1.2em;margin-bottom:0;">
                <li><?= Yii::t('ThiscoveryTranslateModule.base', 'Translation memory transfers by source_hash and can be reused across instances.') ?></li>
                <li><?= Yii::t('ThiscoveryTranslateModule.base', 'Object-field translation rows key on object_id — IDs must match on the target instance.') ?></li>
                <li><?= Yii::t('ThiscoveryTranslateModule.base', 'Usage / cost logs are never included. Secret settings (keys, tokens, passwords) are never exported.') ?></li>
            </ul>
        </div>

        <h4><?= Yii::t('ThiscoveryTranslateModule.base', 'Export') ?></h4>
        <p class="text-muted">
            <?= Yii::t('ThiscoveryTranslateModule.base', 'Download a JSON file with translations, translation memory, terminology, and non-secret settings.') ?>
        </p>
        <form method="get" action="<?= Html::encode(Url::to(['/thiscovery-translate/admin/export'])) ?>" class="form-horizontal" style="margin-bottom:2em;">
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="forms" value="1">
                    <?= Yii::t('ThiscoveryTranslateModule.base', 'Include Forms overlays') ?>
                </label>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fa fa-download" aria-hidden="true"></i>
                <?= Yii::t('ThiscoveryTranslateModule.base', 'Download JSON') ?>
            </button>
        </form>

        <hr>

        <h4><?= Yii::t('ThiscoveryTranslateModule.base', 'Import') ?></h4>
        <p class="text-muted">
            <?= Yii::t('ThiscoveryTranslateModule.base', 'Upload a previously exported JSON file. Rows are upserted by unique keys; existing matching rows are updated.') ?>
        </p>
        <form method="post" action="<?= Html::encode(Url::to(['/thiscovery-translate/admin/import'])) ?>" enctype="multipart/form-data" class="form-horizontal">
            <input type="hidden" name="<?= Html::encode(Yii::$app->request->csrfParam) ?>" value="<?= Html::encode(Yii::$app->request->csrfToken) ?>">
            <div class="form-group">
                <label class="control-label" for="tt-import-file">
                    <?= Yii::t('ThiscoveryTranslateModule.base', 'JSON file') ?>
                </label>
                <input id="tt-import-file" type="file" name="importFile" accept=".json,application/json" required class="form-control">
            </div>
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="merge_settings" value="1">
                    <?= Yii::t('ThiscoveryTranslateModule.base', 'Merge settings') ?>
                </label>
            </div>
            <button type="submit" class="btn btn-default"
                    data-confirm="<?= Html::encode(Yii::t('ThiscoveryTranslateModule.base', 'Import will upsert translation data from this file. Continue?')) ?>">
                <i class="fa fa-upload" aria-hidden="true"></i>
                <?= Yii::t('ThiscoveryTranslateModule.base', 'Import JSON') ?>
            </button>
        </form>
    </div>
</div>
