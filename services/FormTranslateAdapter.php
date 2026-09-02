<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormFieldI18n;
use humhub\modules\thiscoveryForms\models\FormI18n;
use humhub\modules\thiscoveryForms\services\ChoiceOptions;
use Yii;

/**
 * Pre-translates Forms into existing FormI18n / FormFieldI18n overlays.
 */
class FormTranslateAdapter
{
    private TranslationService $service;

    public function __construct(?TranslationService $service = null)
    {
        $this->service = $service ?: new TranslationService();
    }

    /**
     * @return array{translated:int, skipped:int, failed:int}
     */
    public function translateForm(CustomForm $form, ?string $onlyLanguage = null): array
    {
        $stats = ['translated' => 0, 'skipped' => 0, 'failed' => 0];
        $settings = $this->service->getSettings();
        if (!$settings->formsTranslateEnabled || !$this->service->isEnabled()) {
            return $stats;
        }
        $source = (string)($form->source_language ?: $settings->sourceLanguage);
        $langs = array_values(array_filter((array)$form->enabled_languages, static fn($l) => is_string($l) && $l !== ''));
        if ($onlyLanguage) {
            $langs = array_values(array_filter($langs, static fn($l) => LocaleMap::sameLanguage($l, $onlyLanguage) || strcasecmp($l, $onlyLanguage) === 0));
        }
        foreach ($langs as $lang) {
            if (LocaleMap::sameLanguage($lang, $source)) {
                $stats['skipped']++;
                continue;
            }
            $r = $this->translateFormLanguage($form, $source, $lang);
            $stats['translated'] += $r['translated'];
            $stats['skipped'] += $r['skipped'];
            $stats['failed'] += $r['failed'];
        }
        return $stats;
    }

    /**
     * @return array{translated:int, skipped:int, failed:int}
     */
    public function translateFormLanguage(CustomForm $form, string $source, string $target): array
    {
        $stats = ['translated' => 0, 'skipped' => 0, 'failed' => 0];
        $fields = [
            'title' => (string)$form->title,
            'description' => (string)$form->description,
            'thank_you_content' => (string)$form->thank_you_content,
        ];
        $overlay = FormI18n::findOne(['form_id' => $form->id, 'language' => $target]) ?: new FormI18n([
            'form_id' => $form->id,
            'language' => $target,
        ]);
        foreach ($fields as $field => $text) {
            if (trim($text) === '') {
                $stats['skipped']++;
                continue;
            }
            try {
                $translated = $this->service->getTranslation(
                    'form',
                    $form->id,
                    $field,
                    $text,
                    $target,
                    $source,
                    'form',
                    true,
                    'thiscovery-forms'
                );
                if ($translated === $text && !LocaleMap::sameLanguage($source, $target)) {
                    // May still be valid if Amazon returned same; count as translated attempt stored
                }
                $overlay->$field = $translated;
                $stats['translated']++;
            } catch (\Throwable $e) {
                Yii::warning('Form field translate failed: ' . $e->getMessage(), 'thiscovery-translate');
                $stats['failed']++;
            }
        }
        try {
            $overlay->save(false);
        } catch (\Throwable $e) {
            $stats['failed']++;
        }

        /** @var FormField[] $formFields */
        $formFields = FormField::find()->where(['form_id' => $form->id])->all();
        foreach ($formFields as $ff) {
            $r = $this->translateField($ff, $source, $target);
            $stats['translated'] += $r['translated'];
            $stats['skipped'] += $r['skipped'];
            $stats['failed'] += $r['failed'];
        }

        // Keep fill-page language switcher in sync with generated overlays.
        self::ensureFormLanguageEnabled($form, $target);

        return $stats;
    }

    /**
     * Ensure $lang is in the form's enabled_languages (persisted in settings_json).
     */
    public static function ensureFormLanguageEnabled(CustomForm $form, string $lang): void
    {
        $lang = trim($lang);
        if ($lang === '') {
            return;
        }
        try {
            $enabled = $form->getEnabledLanguages();
            if (in_array($lang, $enabled, true)) {
                return;
            }
            $enabled[] = $lang;
            $form->enabled_languages = array_values(array_unique($enabled));
            if (method_exists($form, 'setSetting')) {
                $form->setSetting('enabled_languages', $form->enabled_languages);
            }
            $form->save(false);
        } catch (\Throwable $e) {
            Yii::warning('Could not enable form language ' . $lang . ': ' . $e->getMessage(), 'thiscovery-translate');
        }
    }

    /**
     * @return array{translated:int, skipped:int, failed:int}
     */
    private function translateField(FormField $field, string $source, string $target): array
    {
        $stats = ['translated' => 0, 'skipped' => 0, 'failed' => 0];
        $overlay = FormFieldI18n::findOne(['field_id' => $field->id, 'language' => $target]) ?: new FormFieldI18n([
            'field_id' => $field->id,
            'language' => $target,
        ]);

        foreach (['label' => (string)$field->label, 'help_text' => (string)$field->help_text] as $prop => $text) {
            if (trim($text) === '') {
                $stats['skipped']++;
                continue;
            }
            try {
                $overlay->$prop = $this->service->getTranslation(
                    'form_field',
                    $field->id,
                    $prop,
                    $text,
                    $target,
                    $source,
                    'form',
                    true,
                    'thiscovery-forms'
                );
                $stats['translated']++;
            } catch (\Throwable $e) {
                $stats['failed']++;
            }
        }

        $decoded = json_decode((string)$field->options_json, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
        $overlayOptions = $overlay->getOptionsOverlay();

        // Choice labels — getOptions() returns codes only; use choice pairs / decoded JSON.
        $pairs = method_exists($field, 'getChoicePairs')
            ? $field->getChoicePairs()
            : ChoiceOptions::itemsFromDecoded($decoded);
        if ($pairs !== []) {
            $translatedPairs = [];
            foreach ($pairs as $pair) {
                $code = trim((string)($pair['code'] ?? ''));
                $label = trim((string)($pair['label'] ?? $code));
                if ($code === '' && $label === '') {
                    continue;
                }
                if ($code === '') {
                    $code = $label;
                }
                $newLabel = $label !== '' ? $label : $code;
                if ($newLabel !== '') {
                    try {
                        $newLabel = $this->service->getTranslation(
                            'form_field',
                            $field->id,
                            'option:' . $code,
                            $newLabel,
                            $target,
                            $source,
                            'form',
                            true,
                            'thiscovery-forms'
                        );
                        $stats['translated']++;
                    } catch (\Throwable $e) {
                        $stats['failed']++;
                    }
                }
                $translatedPairs[] = ['code' => $code, 'label' => $newLabel];
            }
            if ($translatedPairs !== []) {
                $overlayOptions['options'] = ChoiceOptions::toStorage($translatedPairs);
            }
        }

        // Scalar display strings stored on the options blob
        $scalarMap = [
            'title' => 'page_title',
            'content' => 'rich_content',
            'html' => 'html_content',
            'instructions' => 'html_instructions',
            'lowLabel' => 'lowLabel',
            'highLabel' => 'highLabel',
            'page_title' => 'page_title',
            'rich_content' => 'rich_content',
            'html_content' => 'html_content',
            'html_instructions' => 'html_instructions',
        ];
        foreach ($scalarMap as $srcKey => $overlayKey) {
            if (empty($decoded[$srcKey]) || !is_string($decoded[$srcKey])) {
                continue;
            }
            $text = trim($decoded[$srcKey]);
            if ($text === '' || isset($overlayOptions[$overlayKey])) {
                continue;
            }
            try {
                $overlayOptions[$overlayKey] = $this->service->getTranslation(
                    'form_field',
                    $field->id,
                    $overlayKey,
                    $text,
                    $target,
                    $source,
                    'form',
                    true,
                    'thiscovery-forms'
                );
                $stats['translated']++;
            } catch (\Throwable $e) {
                $stats['failed']++;
            }
        }

        foreach (['rows', 'columns', 'items'] as $listKey) {
            if (empty($decoded[$listKey]) || !is_array($decoded[$listKey])) {
                continue;
            }
            $overlayOptions[$listKey] = [];
            foreach ($decoded[$listKey] as $i => $item) {
                if (!is_string($item) || trim($item) === '') {
                    $overlayOptions[$listKey][$i] = $item;
                    continue;
                }
                try {
                    $overlayOptions[$listKey][$i] = $this->service->getTranslation(
                        'form_field',
                        $field->id,
                        $listKey . ':' . $i,
                        $item,
                        $target,
                        $source,
                        'form',
                        true,
                        'thiscovery-forms'
                    );
                    $stats['translated']++;
                } catch (\Throwable $e) {
                    $overlayOptions[$listKey][$i] = $item;
                    $stats['failed']++;
                }
            }
        }

        $overlay->options_json = $overlayOptions !== [] ? json_encode($overlayOptions, JSON_UNESCAPED_UNICODE) : null;
        try {
            $overlay->save(false);
        } catch (\Throwable $e) {
            $stats['failed']++;
        }
        return $stats;
    }

    /**
     * Status summary for studio UI.
     * @return array<string, array{status:string,fields:int}>
     */
    public function statusForForm(CustomForm $form): array
    {
        $source = (string)($form->source_language ?: 'en-GB');
        $out = [];
        $formsSvc = class_exists(\humhub\modules\thiscoveryForms\services\TranslationService::class)
            ? new \humhub\modules\thiscoveryForms\services\TranslationService()
            : null;
        foreach ((array)$form->enabled_languages as $lang) {
            if (!is_string($lang) || $lang === '') {
                continue;
            }
            if (LocaleMap::sameLanguage($lang, $source)) {
                $out[$lang] = ['status' => 'source', 'fields' => 0];
                continue;
            }
            $i18n = FormI18n::findOne(['form_id' => $form->id, 'language' => $lang]);
            $fieldIds = FormField::find()->select('id')->where(['form_id' => $form->id])->column();
            $fieldCount = $fieldIds === [] ? 0 : (int)FormFieldI18n::find()
                ->where(['language' => $lang, 'field_id' => $fieldIds])
                ->count();
            $pct = $formsSvc ? $formsSvc->completeness($form, $lang) : ($i18n ? 50 : 0);
            $status = 'not_generated';
            if ($pct >= 80) {
                $status = 'ready';
            } elseif ($i18n || $fieldCount > 0) {
                $status = 'partial';
            }
            if ($status !== 'not_generated') {
                $fieldIdsStr = array_map('strval', $fieldIds);
                $staleQ = \humhub\modules\thiscoveryTranslate\models\Translation::find()
                    ->where([
                        'translation_status' => \humhub\modules\thiscoveryTranslate\models\Translation::STATUS_NEEDS_UPDATE,
                        'target_language' => LocaleMap::toAmazon($lang),
                    ])
                    ->andWhere([
                        'or',
                        ['object_type' => 'form', 'object_id' => (string)$form->id],
                        ['object_type' => 'form_field', 'object_id' => $fieldIdsStr ?: ['0']],
                    ]);
                if ((int)$staleQ->count() > 0) {
                    $status = 'stale';
                }
            }
            $out[$lang] = [
                'status' => $status,
                'fields' => $fieldCount,
                'pct' => $pct,
            ];
        }
        return $out;
    }
}