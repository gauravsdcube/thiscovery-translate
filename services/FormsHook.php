<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\services\TranslationService as FormsTranslationService;
use humhub\modules\thiscoveryTranslate\jobs\TranslateFormJob;
use humhub\modules\thiscoveryTranslate\models\ModuleSettings;
use Yii;

/**
 * Soft-dep helper called from Thiscovery Forms after save / generate actions.
 */
class FormsHook
{
    public static function queueFormTranslation(int $formId, ?string $onlyLanguage = null): bool
    {
        if (!Yii::$app->hasModule('thiscovery-translate')) {
            return false;
        }
        $module = Yii::$app->getModule('thiscovery-translate');
        if (!$module || !$module->getIsEnabled() || !ModuleSettings::isFormsTranslateEnabled()) {
            return false;
        }
        try {
            Yii::$app->queue->push(new TranslateFormJob([
                'formId' => $formId,
                'onlyLanguage' => $onlyLanguage,
            ]));
            return true;
        } catch (\Throwable $e) {
            Yii::warning('Could not queue form translation: ' . $e->getMessage(), 'thiscovery-translate');
            try {
                if (!class_exists(CustomForm::class)) {
                    return false;
                }
                $form = CustomForm::findOne($formId);
                if ($form) {
                    (new FormTranslateAdapter())->translateForm($form, $onlyLanguage);
                    return true;
                }
            } catch (\Throwable $e2) {
            }
            return false;
        }
    }

    /**
     * Publish guard: warn or block when enabled languages lack overlays.
     * @return array{ok:bool, incomplete:string[], message:?string}
     */
    public static function checkPublishReady(CustomForm $form): array
    {
        $settings = ModuleSettings::loadSettings();
        if (!$settings->formsTranslateEnabled) {
            return ['ok' => true, 'incomplete' => [], 'message' => null];
        }
        $source = (string)($form->source_language ?: $settings->sourceLanguage);
        $incomplete = [];
        $svc = class_exists(FormsTranslationService::class) ? new FormsTranslationService() : null;
        foreach ((array)$form->enabled_languages as $lang) {
            if (!is_string($lang) || $lang === '' || LocaleMap::sameLanguage($lang, $source)) {
                continue;
            }
            $pct = $svc ? $svc->completeness($form, $lang) : 0;
            if ($pct < 80) {
                $incomplete[] = $lang;
            }
        }
        if ($incomplete === []) {
            return ['ok' => true, 'incomplete' => [], 'message' => null];
        }
        $message = Yii::t(
            'ThiscoveryTranslateModule.base',
            'Translations incomplete for: {langs}',
            ['langs' => implode(', ', $incomplete)]
        );
        if ($settings->formsPublishMode === 'block' && (int)$form->status === CustomForm::STATUS_OPEN) {
            return ['ok' => false, 'incomplete' => $incomplete, 'message' => $message];
        }
        return ['ok' => true, 'incomplete' => $incomplete, 'message' => $message];
    }
}
