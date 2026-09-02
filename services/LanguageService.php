<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\services;

use humhub\modules\thiscoveryTranslate\models\ModuleSettings;
use Yii;

class LanguageService
{
    public const COOKIE_NAME = 'tt_language';

    private ModuleSettings $settings;

    public function __construct(?ModuleSettings $settings = null)
    {
        $this->settings = $settings ?: ModuleSettings::loadSettings();
    }

    /**
     * @return array<string, array{code:string,label:string,native:string,rtl:bool,order:int}>
     */
    public function enabledLanguageMeta(): array
    {
        $out = [];
        $order = 0;
        foreach ($this->settings->availableLanguages as $code) {
            $label = LocaleMap::labels()[$code] ?? $code;
            $native = LocaleMap::nativeLabels()[$code] ?? $label;
            $out[$code] = [
                'code' => $code,
                'label' => $label,
                'native' => $native,
                'rtl' => LocaleMap::isRtl($code),
                'order' => $order++,
            ];
        }
        return $out;
    }

    /**
     * @return array<string, string> code => native label for picker
     */
    public function pickerOptions(): array
    {
        $opts = [];
        foreach ($this->enabledLanguageMeta() as $code => $meta) {
            $opts[$code] = $meta['native'];
        }
        return $opts;
    }

    /**
     * Resolution: picker cookie → profile → current app → Accept-Language → instance default.
     * Cookie is checked first so the picker always wins (core HumHub may autoset profile/default earlier).
     */
    public function resolveCurrentLanguage(): string
    {
        $enabled = $this->settings->availableLanguages;
        if ($enabled === []) {
            return $this->settings->sourceLanguage;
        }

        $cookie = null;
        if (Yii::$app->request instanceof \yii\web\Request) {
            $cookie = Yii::$app->request->cookies->getValue(self::COOKIE_NAME);
            if (!is_string($cookie) || $cookie === '') {
                // Back-compat with earlier builds that used the core "language" cookie.
                $cookie = Yii::$app->request->cookies->getValue('language');
            }
        }
        if (is_string($cookie) && $this->isAllowed($cookie, $enabled)) {
            return $this->normalizeToEnabled($cookie, $enabled);
        }

        if (!Yii::$app->user->isGuest) {
            $user = Yii::$app->user->getIdentity();
            if ($user && !empty($user->language) && $this->isAllowed($user->language, $enabled)) {
                return $this->normalizeToEnabled($user->language, $enabled);
            }
        }

        $current = Yii::$app->language;
        if ($this->isAllowed($current, $enabled)) {
            return $this->normalizeToEnabled($current, $enabled);
        }

        if (Yii::$app->request instanceof \yii\web\Request) {
            $browser = Yii::$app->request->getPreferredLanguage($enabled);
            if (is_string($browser) && $browser !== '') {
                return $this->normalizeToEnabled($browser, $enabled);
            }
        }

        return $this->settings->sourceLanguage;
    }

    public function applyHtmlLanguageAttributes(): void
    {
        // Applied via View event registering meta; dir/lang set on html via JS or layout param.
        Yii::$app->language = $this->resolveCurrentLanguage();
        Yii::$app->formatter->locale = Yii::$app->language;
    }

    public function isRtl(?string $code = null): bool
    {
        return LocaleMap::isRtl($code ?: Yii::$app->language);
    }

    /** @param string[] $enabled */
    private function isAllowed(string $code, array $enabled): bool
    {
        foreach ($enabled as $e) {
            if (strcasecmp($e, $code) === 0 || LocaleMap::sameLanguage($e, $code)) {
                return true;
            }
        }
        return false;
    }

    /** @param string[] $enabled */
    private function normalizeToEnabled(string $code, array $enabled): string
    {
        foreach ($enabled as $e) {
            if (strcasecmp($e, $code) === 0 || LocaleMap::sameLanguage($e, $code)) {
                return $e;
            }
        }
        return $enabled[0] ?? $code;
    }
}
