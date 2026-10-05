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
            ['langs' => self::languageList($incomplete)]
        );
        if ($settings->formsPublishMode === 'block' && (int)$form->status === CustomForm::STATUS_OPEN) {
            return ['ok' => false, 'incomplete' => $incomplete, 'message' => $message];
        }
        return ['ok' => true, 'incomplete' => $incomplete, 'message' => $message];
    }

    /**
     * After a studio save: a language with no translation yet is queued, not reported as incomplete.
     * An open form still warns, because people can already switch to that language.
     *
     * @return array{level:string, message:string, block:bool}|null
     */
    public static function noticeAfterSave(CustomForm $form): ?array
    {
        $queued = self::queueFormTranslation((int)$form->id);
        $pub = self::checkPublishReady($form);
        if (empty($pub['message'])) {
            return null;
        }
        $svc = class_exists(FormsTranslationService::class) ? new FormsTranslationService() : null;
        $started = true;
        foreach ($pub['incomplete'] as $lang) {
            if ($svc && $svc->completeness($form, (string)$lang) > 0) {
                $started = false;
                break;
            }
        }
        if ($queued && $started && (int)$form->status !== CustomForm::STATUS_OPEN) {
            return [
                'level' => 'info',
                'block' => false,
                'message' => Yii::t(
                    'ThiscoveryTranslateModule.base',
                    'Translation queued for {langs}. It appears on the Translations tab when it is ready.',
                    ['langs' => self::languageList($pub['incomplete'])]
                ),
            ];
        }
        if (!$pub['ok']) {
            return [
                'level' => 'error',
                'block' => true,
                'message' => $pub['message'] . ' ' . Yii::t('ThiscoveryFormsModule.base', 'Form kept as draft until translations are ready.'),
            ];
        }
        return [
            'level' => 'warning',
            'block' => false,
            'message' => $pub['message'],
        ];
    }

    /**
     * @param string[] $codes
     */
    private static function languageList(array $codes): string
    {
        $labels = class_exists(FormsTranslationService::class) ? FormsTranslationService::languageLabels() : [];
        $names = [];
        foreach ($codes as $code) {
            $names[] = $labels[$code] ?? $code;
        }
        return implode(', ', $names);
    }
}
