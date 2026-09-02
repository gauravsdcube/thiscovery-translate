<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\controllers;

use humhub\components\access\ControllerAccess;
use humhub\components\Controller;
use humhub\modules\thiscoveryTranslate\models\ModuleSettings;
use humhub\modules\thiscoveryTranslate\services\LocaleMap;
use Yii;
use yii\web\Cookie;

/**
 * Guest-accessible language switcher (cookie + optional profile update).
 */
class LanguageController extends Controller
{
    public $enableCsrfValidation = true;

    /** Allow guests (core LanguageChooser is guest-facing). */
    protected $access = ControllerAccess::class;

    public function actionSet()
    {
        $this->forcePostRequest();
        $settings = ModuleSettings::loadSettings();
        if (!$settings->siteTranslateEnabled) {
            Yii::$app->session->setFlash('error', Yii::t('ThiscoveryTranslateModule.base', 'Site-wide translation is disabled.'));
            return $this->safeRedirect();
        }
        $language = (string)Yii::$app->request->post('language', '');
        $normalized = null;
        foreach ($settings->availableLanguages as $code) {
            if (strcasecmp($code, $language) === 0 || LocaleMap::sameLanguage($code, $language)) {
                $normalized = $code;
                break;
            }
        }
        if ($normalized === null) {
            Yii::$app->session->setFlash('error', Yii::t('ThiscoveryTranslateModule.base', 'That language is not available.'));
            return $this->safeRedirect();
        }

        // Ensure HumHub i18n allows this locale for the current request / cookie validation.
        $available = Yii::$app->params['availableLanguages'] ?? [];
        if (!isset($available[$normalized])) {
            $available[$normalized] = LocaleMap::labels()[$normalized] ?? $normalized;
            Yii::$app->params['availableLanguages'] = $available;
        }

        Yii::$app->response->cookies->add(new Cookie([
            'name' => \humhub\modules\thiscoveryTranslate\services\LanguageService::COOKIE_NAME,
            'value' => $normalized,
            'expire' => time() + 86400 * 365,
            'httpOnly' => false,
            'sameSite' => Cookie::SAME_SITE_LAX,
        ]));
        // Also set core cookie when locale is already allowed (guests / ChooseLanguage path).
        Yii::$app->response->cookies->add(new Cookie([
            'name' => 'language',
            'value' => $normalized,
            'expire' => time() + 86400 * 365,
            'httpOnly' => false,
            'sameSite' => Cookie::SAME_SITE_LAX,
        ]));

        if (!Yii::$app->user->isGuest) {
            try {
                $user = Yii::$app->user->getIdentity();
                if ($user && $user->hasAttribute('language')) {
                    $user->language = $normalized;
                    $user->save(false, ['language']);
                }
            } catch (\Throwable $e) {
                Yii::warning('Could not persist user language: ' . $e->getMessage(), 'thiscovery-translate');
            }
        }

        Yii::$app->language = $normalized;
        Yii::$app->formatter->locale = $normalized;

        // Pre-warm common menu/chrome strings in the background (and a tiny sync burst for top nav).
        try {
            $source = $settings->sourceLanguage;
            if (!LocaleMap::sameLanguage($source, $normalized)) {
                $priority = \humhub\modules\thiscoveryTranslate\services\UiAssistService::seedPhrases();
                // Sync warm enough chrome for the redirected page; rest via queue.
                (new \humhub\modules\thiscoveryTranslate\services\UiAssistService($settings))
                    ->warm(array_slice($priority, 0, 20), $normalized, $source);
                Yii::$app->queue->push(new \humhub\modules\thiscoveryTranslate\jobs\WarmUiLanguageJob([
                    'targetLanguage' => $normalized,
                    'sourceLanguage' => $source,
                    'extraPhrases' => $priority,
                ]));
            }
        } catch (\Throwable $e) {
            Yii::warning('UI warm queue failed: ' . $e->getMessage(), 'thiscovery-translate');
        }

        return $this->safeRedirect();
    }

    private function safeRedirect()
    {
        $referrer = Yii::$app->request->referrer;
        $home = Yii::$app->homeUrl;
        if (is_string($referrer) && $referrer !== '' && str_contains($referrer, '/thiscovery-translate/language/')) {
            $referrer = $home;
        }
        return $this->redirect($referrer ?: $home);
    }
}
