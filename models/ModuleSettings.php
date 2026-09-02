<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\models;

use humhub\modules\thiscoveryTranslate\services\LocaleMap;
use Yii;
use yii\base\Model;

class ModuleSettings extends Model
{
    public const SETTING_ENABLED = 'feature_enabled';
    public const SETTING_SITE = 'site_translate_enabled';
    public const SETTING_FORMS = 'forms_translate_enabled';
    public const SETTING_REGION = 'aws_region';
    public const SETTING_SOURCE = 'source_language';
    public const SETTING_LANGUAGES = 'available_languages';
    public const SETTING_CONTENT = 'content_translate';
    public const SETTING_DISCLAIMER = 'show_disclaimer';
    public const SETTING_DAILY_BUDGET = 'daily_char_budget';
    public const SETTING_MONTHLY_WARN = 'monthly_char_warning';
    public const SETTING_MONTHLY_HARD = 'monthly_char_hard_limit';
    public const SETTING_COST_PER_M = 'estimated_cost_per_million';
    public const SETTING_TERM_VERSION = 'terminology_version';
    public const SETTING_UI_ASSIST = 'ui_missing_assist';
    public const SETTING_PUBLISH_MODE = 'forms_publish_mode';

    /** True when site-wide or Forms translation is on (legacy / Amazon gate). */
    public bool $featureEnabled = false;
    public bool $siteTranslateEnabled = false;
    public bool $formsTranslateEnabled = false;
    public string $awsRegion = 'eu-west-2';
    public string $sourceLanguage = 'en-GB';
    /** @var string[] */
    public array $availableLanguages = ['en-GB', 'cy', 'fr'];
    public bool $contentTranslate = true;
    public bool $showDisclaimer = true;
    public int $dailyCharBudget = 0;
    public int $monthlyCharWarning = 0;
    public int $monthlyCharHardLimit = 0;
    public float $estimatedCostPerMillion = 15.0;
    public int $terminologyVersion = 1;
    public bool $uiMissingAssist = true;
    /** warn|block */
    public string $formsPublishMode = 'warn';

    public function rules()
    {
        return [
            [['awsRegion', 'sourceLanguage'], 'required'],
            [['awsRegion', 'sourceLanguage', 'formsPublishMode'], 'string', 'max' => 128],
            [[
                'featureEnabled',
                'siteTranslateEnabled',
                'formsTranslateEnabled',
                'contentTranslate',
                'showDisclaimer',
                'uiMissingAssist',
            ], 'boolean'],
            [['dailyCharBudget', 'monthlyCharWarning', 'monthlyCharHardLimit', 'terminologyVersion'], 'integer', 'min' => 0],
            [['estimatedCostPerMillion'], 'number', 'min' => 0],
            [['availableLanguages'], 'each', 'rule' => ['string']],
            [['sourceLanguage'], 'in', 'range' => array_keys(LocaleMap::catalog())],
            [['formsPublishMode'], 'in', 'range' => ['warn', 'block']],
        ];
    }

    public function attributeLabels()
    {
        return [
            'siteTranslateEnabled' => Yii::t('ThiscoveryTranslateModule.base', 'Enable site-wide translation'),
            'formsTranslateEnabled' => Yii::t('ThiscoveryTranslateModule.base', 'Enable Forms translation'),
            'featureEnabled' => Yii::t('ThiscoveryTranslateModule.base', 'Enable Thiscovery Translate'),
            'awsRegion' => Yii::t('ThiscoveryTranslateModule.base', 'AWS region'),
            'sourceLanguage' => Yii::t('ThiscoveryTranslateModule.base', 'Default / source language'),
            'availableLanguages' => Yii::t('ThiscoveryTranslateModule.base', 'Enabled languages'),
            'contentTranslate' => Yii::t('ThiscoveryTranslateModule.base', 'Lazy-translate user content (posts, pages, comments)'),
            'showDisclaimer' => Yii::t('ThiscoveryTranslateModule.base', 'Show machine-translated disclaimer where applicable'),
            'dailyCharBudget' => Yii::t('ThiscoveryTranslateModule.base', 'Daily character budget (0 = unlimited)'),
            'monthlyCharWarning' => Yii::t('ThiscoveryTranslateModule.base', 'Monthly character warning threshold (0 = off)'),
            'monthlyCharHardLimit' => Yii::t('ThiscoveryTranslateModule.base', 'Monthly character hard limit (0 = off)'),
            'estimatedCostPerMillion' => Yii::t('ThiscoveryTranslateModule.base', 'Estimated USD per million characters'),
            'uiMissingAssist' => Yii::t('ThiscoveryTranslateModule.base', 'Translate missing menu/UI strings (Redis/TM + small Amazon budget; safe for page load)'),
            'formsPublishMode' => Yii::t('ThiscoveryTranslateModule.base', 'Forms publish incomplete translations'),
        ];
    }

    public function attributeHints()
    {
        return [
            'siteTranslateEnabled' => Yii::t(
                'ThiscoveryTranslateModule.base',
                'Language picker, menus, page builder, and stream content. Turn off to leave the site chrome in the source language.'
            ),
            'formsTranslateEnabled' => Yii::t(
                'ThiscoveryTranslateModule.base',
                'Machine translation and language tools inside Thiscovery Forms studio and participant fill. Independent of site-wide translation.'
            ),
        ];
    }

    public static function loadSettings(?\humhub\modules\thiscoveryTranslate\Module $module = null): self
    {
        if ($module === null && Yii::$app->hasModule('thiscovery-translate')) {
            $loaded = Yii::$app->getModule('thiscovery-translate', false);
            if ($loaded instanceof \humhub\modules\thiscoveryTranslate\Module) {
                $module = $loaded;
            } else {
                $module = Yii::$app->getModule('thiscovery-translate');
            }
        }
        $m = new self();
        if (!$module) {
            return $m;
        }
        $legacy = (bool)$module->settings->get(self::SETTING_ENABLED, false);
        $siteRaw = $module->settings->get(self::SETTING_SITE, null);
        $formsRaw = $module->settings->get(self::SETTING_FORMS, null);
        // First load after upgrade: mirror legacy master switch onto both toggles.
        $m->siteTranslateEnabled = $siteRaw === null || $siteRaw === '' ? $legacy : (bool)$siteRaw;
        $m->formsTranslateEnabled = $formsRaw === null || $formsRaw === '' ? $legacy : (bool)$formsRaw;
        $m->featureEnabled = $m->siteTranslateEnabled || $m->formsTranslateEnabled;

        $m->awsRegion = (string)$module->settings->get(self::SETTING_REGION, 'eu-west-2') ?: 'eu-west-2';
        $m->sourceLanguage = (string)$module->settings->get(self::SETTING_SOURCE, 'en-GB') ?: 'en-GB';
        $langs = $module->settings->get(self::SETTING_LANGUAGES, ['en-GB', 'cy', 'fr']);
        if (is_string($langs)) {
            $decoded = json_decode($langs, true);
            $langs = is_array($decoded) ? $decoded : ['en-GB', 'cy', 'fr'];
        }
        $m->availableLanguages = array_values(array_unique(array_filter(array_map('strval', (array)$langs))));
        if (!in_array($m->sourceLanguage, $m->availableLanguages, true)) {
            array_unshift($m->availableLanguages, $m->sourceLanguage);
        }
        $m->contentTranslate = (bool)$module->settings->get(self::SETTING_CONTENT, true);
        $m->showDisclaimer = (bool)$module->settings->get(self::SETTING_DISCLAIMER, true);
        $m->dailyCharBudget = (int)$module->settings->get(self::SETTING_DAILY_BUDGET, 0);
        $m->monthlyCharWarning = (int)$module->settings->get(self::SETTING_MONTHLY_WARN, 0);
        $m->monthlyCharHardLimit = (int)$module->settings->get(self::SETTING_MONTHLY_HARD, 0);
        $m->estimatedCostPerMillion = (float)$module->settings->get(self::SETTING_COST_PER_M, 15);
        $m->terminologyVersion = (int)$module->settings->get(self::SETTING_TERM_VERSION, 1);
        $m->uiMissingAssist = (bool)$module->settings->get(self::SETTING_UI_ASSIST, true);
        $m->formsPublishMode = (string)$module->settings->get(self::SETTING_PUBLISH_MODE, 'warn') ?: 'warn';
        return $m;
    }

    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        $module = Yii::$app->getModule('thiscovery-translate');
        if (!$module) {
            return false;
        }
        $this->featureEnabled = $this->siteTranslateEnabled || $this->formsTranslateEnabled;
        $module->settings->set(self::SETTING_SITE, $this->siteTranslateEnabled ? '1' : '0');
        $module->settings->set(self::SETTING_FORMS, $this->formsTranslateEnabled ? '1' : '0');
        $module->settings->set(self::SETTING_ENABLED, $this->featureEnabled ? '1' : '0');
        $module->settings->set(self::SETTING_REGION, $this->awsRegion);
        $module->settings->set(self::SETTING_SOURCE, $this->sourceLanguage);
        $langs = array_values(array_intersect($this->availableLanguages, array_keys(LocaleMap::catalog())));
        if (!in_array($this->sourceLanguage, $langs, true)) {
            $langs[] = $this->sourceLanguage;
        }
        $module->settings->set(self::SETTING_LANGUAGES, json_encode(array_values(array_unique($langs))));
        $module->settings->set(self::SETTING_CONTENT, $this->contentTranslate ? '1' : '0');
        $module->settings->set(self::SETTING_DISCLAIMER, $this->showDisclaimer ? '1' : '0');
        $module->settings->set(self::SETTING_DAILY_BUDGET, (string)max(0, $this->dailyCharBudget));
        $module->settings->set(self::SETTING_MONTHLY_WARN, (string)max(0, $this->monthlyCharWarning));
        $module->settings->set(self::SETTING_MONTHLY_HARD, (string)max(0, $this->monthlyCharHardLimit));
        $module->settings->set(self::SETTING_COST_PER_M, (string)$this->estimatedCostPerMillion);
        $module->settings->set(self::SETTING_TERM_VERSION, (string)max(1, $this->terminologyVersion));
        $module->settings->set(self::SETTING_UI_ASSIST, $this->uiMissingAssist ? '1' : '0');
        $module->settings->set(self::SETTING_PUBLISH_MODE, $this->formsPublishMode);
        return true;
    }

    /** True when either site-wide or Forms translation is on. */
    public static function isFeatureEnabled(): bool
    {
        $s = self::loadSettings();
        return $s->siteTranslateEnabled || $s->formsTranslateEnabled;
    }

    public static function isSiteTranslateEnabled(): bool
    {
        return self::loadSettings()->siteTranslateEnabled;
    }

    public static function isFormsTranslateEnabled(): bool
    {
        return self::loadSettings()->formsTranslateEnabled;
    }

    public function pickerOptions(): array
    {
        return LocaleMap::labelMapFor($this->availableLanguages);
    }
}
