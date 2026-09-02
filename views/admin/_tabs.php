<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use yii\helpers\Url;

/** @var string $activeTab */

$tabs = [
    'settings' => ['label' => Yii::t('ThiscoveryTranslateModule.base', 'Settings'), 'url' => ['/thiscovery-translate/admin/index']],
    'languages' => ['label' => Yii::t('ThiscoveryTranslateModule.base', 'Languages'), 'url' => ['/thiscovery-translate/admin/languages']],
    'terminology' => ['label' => Yii::t('ThiscoveryTranslateModule.base', 'Terminology'), 'url' => ['/thiscovery-translate/admin/terminology']],
    'memory' => ['label' => Yii::t('ThiscoveryTranslateModule.base', 'Translation Memory'), 'url' => ['/thiscovery-translate/admin/memory']],
    'translations' => ['label' => Yii::t('ThiscoveryTranslateModule.base', 'Translations'), 'url' => ['/thiscovery-translate/admin/translations']],
    'usage' => ['label' => Yii::t('ThiscoveryTranslateModule.base', 'Usage'), 'url' => ['/thiscovery-translate/admin/usage']],
    'maintenance' => ['label' => Yii::t('ThiscoveryTranslateModule.base', 'Maintenance'), 'url' => ['/thiscovery-translate/admin/maintenance']],
    'help' => ['label' => Yii::t('ThiscoveryTranslateModule.base', 'Help'), 'url' => ['/thiscovery-translate/admin/help']],
];
$activeTab = $activeTab ?? 'settings';
?>
<ul class="nav nav-tabs tab-sub-menu tt-admin-tabs mb-3">
    <?php foreach ($tabs as $key => $tab): ?>
        <li class="nav-item">
            <a class="nav-link<?= $activeTab === $key ? ' active' : '' ?>" href="<?= Url::to($tab['url']) ?>">
                <?= htmlspecialchars($tab['label']) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
