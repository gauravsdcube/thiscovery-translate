<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate;

use humhub\helpers\ControllerHelper;
use humhub\helpers\Html as HhHtml;
use humhub\libs\ParameterEvent;
use humhub\modules\admin\permissions\ManageModules;
use humhub\modules\admin\widgets\AdminMenu;
use humhub\modules\content\widgets\ContainerProfileHeader;
use humhub\modules\content\widgets\richtext\AbstractRichText;
use humhub\modules\space\models\Space;
use humhub\modules\space\widgets\Header as SpaceHeader;
use humhub\modules\space\widgets\SpaceChooserItem;
use humhub\modules\space\widgets\SpaceDirectoryCard;
use humhub\modules\thiscoveryTranslate\assets\TranslateAsset;
use humhub\modules\thiscoveryTranslate\models\ModuleSettings;
use humhub\modules\thiscoveryTranslate\services\ContentTranslateService;
use humhub\modules\thiscoveryTranslate\services\LanguageService;
use humhub\modules\thiscoveryTranslate\services\LocaleMap;
use humhub\modules\thiscoveryTranslate\services\SpaceHook;
use humhub\modules\thiscoveryTranslate\widgets\LanguageSelector;
use humhub\modules\ui\menu\MenuLink;
use humhub\widgets\LanguageChooser;
use humhub\widgets\TopMenuRightStack;
use Yii;
use yii\base\WidgetEvent;
use yii\web\View;

class Events
{
    public static function onBeforeRequest(): void
    {
        $settings = ModuleSettings::loadSettings();
        if (!$settings->siteTranslateEnabled && !$settings->formsTranslateEnabled) {
            return;
        }

        // Language catalogue is shared (site picker + Forms studio).
        $available = Yii::$app->params['availableLanguages'] ?? [];
        foreach ($settings->availableLanguages as $code) {
            $label = LocaleMap::labels()[$code] ?? $code;
            if (!isset($available[$code])) {
                $available[$code] = $label;
            }
        }
        Yii::$app->params['availableLanguages'] = $available;

        // Site-wide language resolution + UI assist only when site translation is on.
        if (!$settings->siteTranslateEnabled) {
            return;
        }

        $langService = new LanguageService($settings);
        $lang = $langService->resolveCurrentLanguage();
        Yii::$app->language = $lang;
        Yii::$app->formatter->locale = $lang;

        if ($settings->uiMissingAssist) {
            self::bootNativeAssist($settings);
        }
    }

    /**
     * Assist missing Yii::t catalogs via Redis/TM, with a tiny Amazon budget (no page-load storms).
     * HumHub rebinds this closure onto I18N — never use $this/self instance methods.
     */
    private static function bootNativeAssist(ModuleSettings $settings): void
    {
        $source = $settings->sourceLanguage;
        Yii::$app->i18n->beforeTranslateCallback = function (
            string $category,
            string $message,
            array $params,
            ?string $language
        ) use ($source) {
            static $busy = false;
            if ($busy || $category === '' || str_starts_with($category, 'ThiscoveryTranslateModule')) {
                return [$category, $message, $params, $language];
            }
            $lang = $language ?: Yii::$app->language;
            if ($lang === '' || LocaleMap::sameLanguage($lang, $source)) {
                return [$category, $message, $params, $language];
            }
            // Only short UI chrome — never long content blobs via Yii::t
            if (mb_strlen($message) > 80 || str_contains($message, "\n")) {
                return [$category, $message, $params, $language];
            }
            // Prefer Redis/TM first (cheap). Only probe native catalogs on miss.
            $module = Yii::$app->getModule('thiscovery-translate');
            if (!$module instanceof Module || !$module->getIsEnabled()) {
                return [$category, $message, $params, $language];
            }
            $busy = true;
            try {
                $settings = $module->getSettings();
                $assist = new \humhub\modules\thiscoveryTranslate\services\UiAssistService($settings);
                $cache = new \humhub\modules\thiscoveryTranslate\services\UiStringCache();
                $cached = $cache->get($message, $source, $lang, 'navigation');
                if ($cached !== null && $cached !== '') {
                    return [$category, $cached, $params, $language];
                }
                // TM hit without Amazon / without MessageSource probing
                $fromTm = $assist->peekMemory($message, $lang, $source);
                if ($fromTm !== null && $fromTm !== '') {
                    $cache->set($message, $fromTm, $source, $lang, 'navigation');
                    return [$category, $fromTm, $params, $language];
                }
                // Thiscovery modules ship no locale packs — always assist those categories.
                $isThiscovery = str_starts_with($category, 'Thiscovery') || str_starts_with($category, 'CustomForms');
                if (!$isThiscovery && Events::nativeCatalogHas($category, $message, $lang)) {
                    return [$category, $message, $params, $language];
                }
                $translated = $assist->translate($message, $lang, $source);
                if ($translated !== '' && $translated !== $message) {
                    $message = $translated;
                }
            } catch (\Throwable $e) {
                // never break the page
            } finally {
                $busy = false;
            }
            return [$category, $message, $params, $language];
        };
    }

    /** @internal Called from beforeTranslateCallback (must not rely on $this/$self — HumHub rebinds the closure). */
    public static function nativeCatalogHas(string $category, string $message, string $language): bool
    {
        static $cache = [];
        static $langsWithoutFiles = null;
        // Languages with no HumHub message packs — skip expensive MessageSource probing.
        if ($langsWithoutFiles === null) {
            $langsWithoutFiles = [];
            foreach (['hi', 'pa', 'gu', 'bn'] as $code) {
                $langsWithoutFiles[$code] = true;
            }
        }
        $base = strtolower(explode('-', str_replace('_', '-', $language))[0] ?? '');
        if (isset($langsWithoutFiles[$base])) {
            return false;
        }
        $key = $category . "\0" . $language . "\0" . $message;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        try {
            $source = Yii::$app->i18n->getMessageSource($category);
            $result = $source->translate($category, $message, $language);
            // Empty catalog entries count as missing (HumHub ships many '' stubs).
            $cache[$key] = ($result !== false && $result !== null && $result !== '' && $result !== $message);
        } catch (\Throwable $e) {
            $cache[$key] = false;
        }
        return $cache[$key];
    }

    public static function onTopMenuRightInit($event): void
    {
        if (!self::moduleActive()) {
            return;
        }
        $settings = ModuleSettings::loadSettings();
        if (count($settings->availableLanguages) < 2) {
            return;
        }
        /** @var TopMenuRightStack $stack */
        $stack = $event->sender;
        $stack->addWidget(LanguageSelector::class, [], ['sortOrder' => 50]);
    }

    public static function onAdminMenuInit($event): void
    {
        if (Yii::$app->user->isGuest) {
            return;
        }
        if (!Yii::$app->user->isAdmin() && !Yii::$app->user->can(ManageModules::class)) {
            return;
        }
        /** @var AdminMenu $menu */
        $menu = $event->sender;
        $menu->addEntry(new MenuLink([
            'label' => Yii::t('ThiscoveryTranslateModule.base', 'Thiscovery Translate'),
            'url' => ['/thiscovery-translate/admin/index'],
            'icon' => 'language',
            'sortOrder' => 570,
            'isActive' => ControllerHelper::isActivePath('thiscovery-translate'),
        ]));
    }

    public static function onLanguageChooserBeforeRun(WidgetEvent $event): void
    {
        if (!self::moduleActive()) {
            return;
        }
        $event->isValid = false;
    }

    public static function onViewBeginPage($event): void
    {
        if (!self::moduleActive()) {
            return;
        }
        /** @var View $view */
        $view = $event->sender;
        try {
            TranslateAsset::register($view);
        } catch (\Throwable $e) {
            Yii::warning('TranslateAsset register failed: ' . $e->getMessage(), 'thiscovery-translate');
        }
        $rtl = (new LanguageService())->isRtl();
        $lang = Yii::$app->language;
        $dir = $rtl ? 'rtl' : 'ltr';
        $view->registerJs(
            "document.documentElement.setAttribute('lang'," . json_encode($lang) . ");"
            . "document.documentElement.setAttribute('dir'," . json_encode($dir) . ");"
            . "document.documentElement.classList.toggle('tt-rtl'," . ($rtl ? 'true' : 'false') . ");",
            View::POS_HEAD,
            'thiscovery-translate-html-lang'
        );
    }

    /**
     * Lazy-translate post/comment richtext markdown before it is rendered.
     * Uses object store + TM; Amazon only within a small per-request budget.
     */
    public static function onRichTextBeforeOutput(ParameterEvent $event): void
    {
        if (!self::moduleActive()) {
            return;
        }
        /** @var AbstractRichText $richText */
        $richText = $event->sender;
        if (!empty($richText->edit)) {
            return;
        }
        $text = (string)($event->parameters['output'] ?? '');
        if (trim($text) === '') {
            return;
        }
        try {
            $record = $richText->record ?? null;
            if ($record instanceof Space) {
                $about = trim((string)$record->about);
                $desc = trim((string)$record->description);
                if ($about !== '' && $text === $about) {
                    $translated = SpaceHook::translateAbout($record);
                } elseif ($desc !== '' && ($text === $desc || trim(strip_tags($text)) === trim(strip_tags($desc)))) {
                    $translated = SpaceHook::translateDescription($record);
                } else {
                    $translated = (new ContentTranslateService())->translateField(
                        'space',
                        (int)$record->id,
                        'richtext',
                        $text,
                        'space',
                        'space'
                    );
                }
            } elseif (!empty($record)) {
                $translated = (new ContentTranslateService())->translateForDisplay(
                    $text,
                    $record,
                    'message'
                );
            } else {
                // Core space about page outputs RichText without a record.
                $ctrl = Yii::$app->controller ?? null;
                if (
                    $ctrl instanceof \humhub\modules\space\controllers\SpaceController
                    && isset($ctrl->action->id) && $ctrl->action->id === 'about'
                    && isset($ctrl->contentContainer) && $ctrl->contentContainer instanceof Space
                ) {
                    $space = $ctrl->contentContainer;
                    $about = trim((string)$space->about);
                    $desc = trim((string)$space->description);
                    if ($about !== '' && (trim(strip_tags($text)) === trim(strip_tags($about)) || $text === $about)) {
                        $translated = SpaceHook::translateAbout($space);
                    } elseif ($desc !== '') {
                        $translated = SpaceHook::translateDescription($space);
                    } else {
                        return;
                    }
                } else {
                    return;
                }
            }
            if ($translated !== '' && $translated !== $text) {
                $event->parameters['output'] = $translated;
            }
        } catch (\Throwable $e) {
            Yii::warning('RichText translate failed: ' . $e->getMessage(), 'thiscovery-translate');
        }
    }

    /**
     * Default HumHub space header — decorate name/description for display.
     */
    public static function onSpaceHeaderBeforeRun(WidgetEvent $event): void
    {
        if (!self::moduleActive() || !SpaceHook::enabled()) {
            return;
        }
        /** @var SpaceHeader $widget */
        $widget = $event->sender;
        if (!$widget->space instanceof Space) {
            return;
        }
        $widget->space = SpaceHook::decorateForDisplay($widget->space);
    }

    /**
     * Profile header (space default view title/subtitle).
     */
    public static function onContainerProfileHeaderBeforeRun(WidgetEvent $event): void
    {
        if (!self::moduleActive() || !SpaceHook::enabled()) {
            return;
        }
        /** @var ContainerProfileHeader $widget */
        $widget = $event->sender;
        if (!$widget->container instanceof Space) {
            return;
        }
        $widget->container = SpaceHook::decorateForDisplay($widget->container);
        $widget->title = HhHtml::encode($widget->container->getDisplayName());
        $widget->subTitle = HhHtml::encode($widget->container->getDisplayNameSub());
    }

    /**
     * Spaces directory cards — decorate name/description for display.
     */
    public static function onSpaceDirectoryCardBeforeRun(WidgetEvent $event): void
    {
        if (!self::moduleActive() || !SpaceHook::enabled()) {
            return;
        }
        /** @var SpaceDirectoryCard $widget */
        $widget = $event->sender;
        if (!$widget->space instanceof Space) {
            return;
        }
        $widget->space = SpaceHook::decorateForDisplay($widget->space);
    }

    /**
     * Space chooser dropdown items.
     */
    public static function onSpaceChooserItemBeforeRun(WidgetEvent $event): void
    {
        if (!self::moduleActive() || !SpaceHook::enabled()) {
            return;
        }
        /** @var SpaceChooserItem $widget */
        $widget = $event->sender;
        if (!$widget->space instanceof Space) {
            return;
        }
        $widget->space = SpaceHook::decorateForDisplay($widget->space);
    }

    private static function moduleActive(): bool
    {
        $module = Yii::$app->getModule('thiscovery-translate');
        return $module instanceof Module && $module->getIsEnabled() && ModuleSettings::isSiteTranslateEnabled();
    }
}
