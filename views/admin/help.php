<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\modules\thiscoveryTranslate\assets\TranslateAsset;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var string $activeTab */

TranslateAsset::register($this);
$this->title = Yii::t('ThiscoveryTranslateModule.base', 'Thiscovery Translate — Help');
?>
<div class="panel panel-default tt-admin">
    <div class="panel-heading">
        <?= Yii::t('ThiscoveryTranslateModule.base', '<strong>Thiscovery Translate</strong> help') ?>
    </div>
    <div class="panel-body">
        <?= $this->render('_tabs', ['activeTab' => $activeTab ?? 'help']) ?>

        <div class="tt-help">
            <h3><?= Yii::t('ThiscoveryTranslateModule.base', 'What this module does') ?></h3>
            <p>
                <?= Yii::t('ThiscoveryTranslateModule.base', 'Thiscovery Translate uses Amazon Translate with a cache and translation memory. It does not rewrite whole HTML pages. Site chrome and Forms can be enabled separately.') ?>
            </p>

            <h3><?= Yii::t('ThiscoveryTranslateModule.base', 'Site-wide translation') ?></h3>
            <ol>
                <li><?= Yii::t('ThiscoveryTranslateModule.base', 'Enable “Site-wide translation” on the Settings tab.') ?></li>
                <li><?= Yii::t('ThiscoveryTranslateModule.base', 'Tick the languages you need under Enabled languages, then Save.') ?></li>
                <li><?= Yii::t('ThiscoveryTranslateModule.base', 'Use the language picker in the top bar. Menus, page builder blocks, and stream posts translate lazily (first view may take a moment; later views use cache).') ?></li>
            </ol>

            <h3><?= Yii::t('ThiscoveryTranslateModule.base', 'Forms translation') ?></h3>
            <ol>
                <li><?= Yii::t('ThiscoveryTranslateModule.base', 'Enable “Forms translation” on the Settings tab.') ?></li>
                <li><?= Yii::t('ThiscoveryTranslateModule.base', 'On each form → Settings → Languages, tick the same extras, then Save.') ?></li>
                <li><?= Yii::t('ThiscoveryTranslateModule.base', 'On Translations, generate machine translation and/or export CSV/JSON, translate offline, and import.') ?></li>
                <li><?= Yii::t('ThiscoveryTranslateModule.base', 'Export and import still work when Forms machine translation is disabled.') ?></li>
            </ol>

            <h3><?= Yii::t('ThiscoveryTranslateModule.base', 'Adding languages') ?></h3>
            <p>
                <?= Yii::t('ThiscoveryTranslateModule.base', 'The Enabled languages list matches Amazon Translate’s supported locales (including regional variants such as fr-CA, es-MX, zh-TW). Tick a language here first; Forms then offers it when Forms translation is on.') ?>
            </p>

            <h3><?= Yii::t('ThiscoveryTranslateModule.base', 'AWS') ?></h3>
            <p>
                <?= Yii::t('ThiscoveryTranslateModule.base', 'Calls use the EC2 instance IAM role in the configured region (default eu-west-2). Use “Test Amazon Translate” on Settings to verify access.') ?>
            </p>

            <h3><?= Yii::t('ThiscoveryTranslateModule.base', 'Quality tools') ?></h3>
            <ul>
                <li><?= Yii::t('ThiscoveryTranslateModule.base', 'Terminology — preferred terms and do-not-translate phrases.') ?></li>
                <li><?= Yii::t('ThiscoveryTranslateModule.base', 'Translation Memory — reuse verified short strings.') ?></li>
                <li><?= Yii::t('ThiscoveryTranslateModule.base', 'Translations — review and lock object-level overlays.') ?></li>
                <li><?= Yii::t('ThiscoveryTranslateModule.base', 'Usage / Maintenance — budgets, clear machine rows, regenerate stale content.') ?></li>
            </ul>

            <p>
                <a class="btn btn-primary" href="<?= Html::encode(Url::to(['/thiscovery-translate/admin/index'])) ?>">
                    <?= Yii::t('ThiscoveryTranslateModule.base', 'Back to Settings') ?>
                </a>
            </p>
        </div>
    </div>
</div>
