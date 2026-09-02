<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate;

use humhub\components\console\Application as ConsoleApplication;
use humhub\components\Module as BaseModule;
use humhub\modules\thiscoveryTranslate\models\ModuleSettings;
use humhub\modules\thiscoveryTranslate\services\LanguageService;
use humhub\modules\thiscoveryTranslate\services\LocaleMap;
use humhub\modules\thiscoveryTranslate\services\TranslationService;
use Yii;
use yii\helpers\Url;

class Module extends BaseModule
{
    public $resourcesPath = 'resources';
    public $icon = 'fa-language';

    public function init()
    {
        parent::init();
        if (Yii::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'humhub\modules\thiscoveryTranslate\commands';
            return;
        }
        // Never call getModule()/loadSettings during construction recursion.
        $site = (bool)$this->settings->get(ModuleSettings::SETTING_SITE, null);
        $forms = (bool)$this->settings->get(ModuleSettings::SETTING_FORMS, null);
        $legacy = (bool)$this->settings->get(ModuleSettings::SETTING_ENABLED, false);
        $siteOn = $this->settings->get(ModuleSettings::SETTING_SITE, null) === null
            ? $legacy
            : $site;
        $formsOn = $this->settings->get(ModuleSettings::SETTING_FORMS, null) === null
            ? $legacy
            : $forms;
        if ($siteOn || $formsOn) {
            $this->registerAvailableLanguages();
        }
    }

    private function registerAvailableLanguages(): void
    {
        $langsJson = $this->settings->get(ModuleSettings::SETTING_LANGUAGES, '[]');
        $langs = is_string($langsJson) ? (json_decode($langsJson, true) ?: []) : (array)$langsJson;
        $available = Yii::$app->params['availableLanguages'] ?? [];
        $labels = LocaleMap::labels();
        foreach ($langs as $code) {
            $code = (string)$code;
            if ($code !== '' && !isset($available[$code])) {
                $available[$code] = $labels[$code] ?? $code;
            }
        }
        Yii::$app->params['availableLanguages'] = $available;
    }

    public function getName()
    {
        return Yii::t('ThiscoveryTranslateModule.base', 'Thiscovery Translate');
    }

    public function getDescription()
    {
        return Yii::t(
            'ThiscoveryTranslateModule.base',
            'Semantic Amazon Translate with persistent cache, translation memory, and Forms pre-translation.'
        );
    }

    public function getConfigUrl()
    {
        return Url::to(['/thiscovery-translate/admin/index']);
    }

    public function getSettings(): ModuleSettings
    {
        return ModuleSettings::loadSettings($this);
    }

    public function getTranslationService(): TranslationService
    {
        return new TranslationService($this->getSettings());
    }

    /** @deprecated use getTranslationService() */
    public function getTranslateService(): TranslationService
    {
        return $this->getTranslationService();
    }

    public function getLanguageService(): LanguageService
    {
        return new LanguageService($this->getSettings());
    }

    public function getPermissions($content = null)
    {
        return [
            new \humhub\modules\thiscoveryTranslate\permissions\ManageTranslationSettings(),
            new \humhub\modules\thiscoveryTranslate\permissions\ManageTerminology(),
            new \humhub\modules\thiscoveryTranslate\permissions\ManageTranslations(),
            new \humhub\modules\thiscoveryTranslate\permissions\ReviewTranslations(),
            new \humhub\modules\thiscoveryTranslate\permissions\TranslateResponses(),
            new \humhub\modules\thiscoveryTranslate\permissions\ViewTranslationUsage(),
        ];
    }
}
