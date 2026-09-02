<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\modules\admin\widgets\AdminMenu;
use humhub\modules\content\widgets\ContainerProfileHeader;
use humhub\modules\content\widgets\richtext\AbstractRichText;
use humhub\modules\content\widgets\richtext\ProsemirrorRichText;
use humhub\modules\space\widgets\Header as SpaceHeader;
use humhub\modules\space\widgets\SpaceChooserItem;
use humhub\modules\space\widgets\SpaceDirectoryCard;
use humhub\modules\thiscoveryTranslate\Events;
use humhub\modules\thiscoveryTranslate\Module;
use humhub\widgets\LanguageChooser;
use humhub\widgets\TopMenuRightStack;
use yii\base\Application;
use yii\base\Widget;
use yii\web\View;

require_once __DIR__ . '/helpers.php';

$events = [
    ['class' => Application::class, 'event' => Application::EVENT_BEFORE_REQUEST, 'callback' => [Events::class, 'onBeforeRequest']],
    ['class' => TopMenuRightStack::class, 'event' => TopMenuRightStack::EVENT_INIT, 'callback' => [Events::class, 'onTopMenuRightInit']],
    ['class' => AdminMenu::class, 'event' => AdminMenu::EVENT_INIT, 'callback' => [Events::class, 'onAdminMenuInit']],
    ['class' => LanguageChooser::class, 'event' => LanguageChooser::EVENT_BEFORE_RUN, 'callback' => [Events::class, 'onLanguageChooserBeforeRun']],
    ['class' => View::class, 'event' => View::EVENT_BEGIN_PAGE, 'callback' => [Events::class, 'onViewBeginPage']],
    ['class' => ProsemirrorRichText::class, 'event' => AbstractRichText::EVENT_BEFORE_OUTPUT, 'callback' => [Events::class, 'onRichTextBeforeOutput']],
    ['class' => SpaceHeader::class, 'event' => Widget::EVENT_BEFORE_RUN, 'callback' => [Events::class, 'onSpaceHeaderBeforeRun']],
    ['class' => ContainerProfileHeader::class, 'event' => Widget::EVENT_BEFORE_RUN, 'callback' => [Events::class, 'onContainerProfileHeaderBeforeRun']],
    ['class' => SpaceDirectoryCard::class, 'event' => Widget::EVENT_BEFORE_RUN, 'callback' => [Events::class, 'onSpaceDirectoryCardBeforeRun']],
    ['class' => SpaceChooserItem::class, 'event' => Widget::EVENT_BEFORE_RUN, 'callback' => [Events::class, 'onSpaceChooserItemBeforeRun']],
];

// Skip registrations whose target or callback class is not loadable (avoids boot fatals).
$events = array_values(array_filter($events, static function (array $e): bool {
    $target = $e['class'] ?? null;
    $cb = $e['callback'][0] ?? null;
    if (!is_string($target) || $target === '' || !class_exists($target)) {
        return false;
    }
    if (!is_string($cb) || $cb === '' || !class_exists($cb)) {
        return false;
    }
    return true;
}));

return [
    'id' => 'thiscovery-translate',
    'class' => Module::class,
    'namespace' => 'humhub\modules\thiscoveryTranslate',
    'events' => $events,
];
