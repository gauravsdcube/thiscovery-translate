<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\modules\admin\widgets\AdminMenu;
use humhub\modules\content\widgets\richtext\AbstractRichText;
use humhub\modules\content\widgets\richtext\ProsemirrorRichText;
use humhub\modules\thiscoveryTranslate\Events;
use humhub\modules\thiscoveryTranslate\Module;
use humhub\widgets\LanguageChooser;
use humhub\widgets\TopMenuRightStack;
use yii\base\Application;
use yii\web\View;

require_once __DIR__ . '/helpers.php';

return [
    'id' => 'thiscovery-translate',
    'class' => Module::class,
    'namespace' => 'humhub\modules\thiscoveryTranslate',
    'events' => [
        ['class' => Application::class, 'event' => Application::EVENT_BEFORE_REQUEST, 'callback' => [Events::class, 'onBeforeRequest']],
        ['class' => TopMenuRightStack::class, 'event' => TopMenuRightStack::EVENT_INIT, 'callback' => [Events::class, 'onTopMenuRightInit']],
        ['class' => AdminMenu::class, 'event' => AdminMenu::EVENT_INIT, 'callback' => [Events::class, 'onAdminMenuInit']],
        ['class' => LanguageChooser::class, 'event' => LanguageChooser::EVENT_BEFORE_RUN, 'callback' => [Events::class, 'onLanguageChooserBeforeRun']],
        ['class' => View::class, 'event' => View::EVENT_BEGIN_PAGE, 'callback' => [Events::class, 'onViewBeginPage']],
        ['class' => ProsemirrorRichText::class, 'event' => AbstractRichText::EVENT_BEFORE_OUTPUT, 'callback' => [Events::class, 'onRichTextBeforeOutput']],
    ],
];
