<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\commands;

use humhub\modules\thiscoveryTranslate\models\ModuleSettings;
use humhub\modules\thiscoveryTranslate\services\AmazonTranslateProvider;
use humhub\modules\thiscoveryTranslate\services\TranslationService;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class SmokeController extends Controller
{
    public function actionIndex()
    {
        $settings = ModuleSettings::loadSettings();
        $this->stdout('featureEnabled=' . ($settings->featureEnabled ? '1' : '0') . "\n");
        $this->stdout('contentTranslate=' . ($settings->contentTranslate ? '1' : '0') . "\n");
        $this->stdout('region=' . $settings->awsRegion . "\n");
        try {
            $provider = new AmazonTranslateProvider($settings);
            $out = $provider->translate('Hello', 'en', 'cy', 'text');
            $this->stdout("provider=OK {$out}\n", Console::FG_GREEN);

            $svc = new TranslationService($settings);
            // Force feature for smoke of resolver path
            $settings->featureEnabled = true;
            $svc = new TranslationService($settings);
            $t1 = $svc->translateString('Continue', 'fr', 'en-GB', 'generic', true);
            $t2 = $svc->translateString('Continue', 'fr', 'en-GB', 'generic', true);
            $this->stdout("resolver1={$t1}\n");
            $this->stdout("resolver2={$t2} (should match; second should be cache/TM)\n");
            return ExitCode::OK;
        } catch (\Throwable $e) {
            $this->stderr('FAIL ' . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /** Translate latest post to a target language (default cy). */
    public function actionContent(string $lang = 'cy')
    {
        $settings = ModuleSettings::loadSettings();
        $this->stdout('contentTranslate=' . ($settings->contentTranslate ? '1' : '0') . "\n");
        \Yii::$app->language = $lang;
        $post = \humhub\modules\post\models\Post::find()->orderBy(['id' => SORT_DESC])->one();
        if (!$post) {
            $this->stderr("No posts found\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $this->stdout("post={$post->id}\n");
        $this->stdout('source=' . mb_substr($post->message, 0, 100) . "\n");
        $out = (new \humhub\modules\thiscoveryTranslate\services\ContentTranslateService($settings))
            ->translateForDisplay($post->message, $post, 'message');
        $this->stdout('target=' . mb_substr($out, 0, 120) . "\n");
        $this->stdout('changed=' . ($out !== $post->message ? 'yes' : 'no') . "\n");
        return ExitCode::OK;
    }

    /** Pre-warm UI chrome phrases for a language. */
    public function actionWarm(string $lang = 'cy')
    {
        $settings = ModuleSettings::loadSettings();
        $source = $settings->sourceLanguage;
        $phrases = \humhub\modules\thiscoveryTranslate\services\UiAssistService::seedPhrases();
        $stats = (new \humhub\modules\thiscoveryTranslate\services\UiAssistService($settings))
            ->warm($phrases, $lang, $source);
        $this->stdout(json_encode($stats) . "\n");
        return ExitCode::OK;
    }

    /** Print translated site nav labels for a language. */
    public function actionNav(string $lang = 'hi')
    {
        \Yii::$app->language = $lang;
        $items = \humhub\modules\thiscoveryNavigation\models\NavItem::find()
            ->where(['scope' => 'site', 'enabled' => 1, 'parent_id' => null])
            ->orderBy(['sort_order' => SORT_ASC])
            ->all();
        foreach ($items as $item) {
            $this->stdout(sprintf(
                "%s [%s] raw=%s => %s\n",
                $item->menuId(),
                $item->type,
                $item->label !== '' ? $item->label : '(catalog)',
                $item->displayLabel()
            ));
        }
        return ExitCode::OK;
    }

    /** Re-run machine translation for a form (options + labels). */
    public function actionForm(int $id, string $lang = '')
    {
        $form = \humhub\modules\thiscoveryForms\models\CustomForm::findOne($id);
        if (!$form) {
            $this->stderr("Form {$id} not found\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $only = $lang !== '' ? $lang : null;
        $stats = (new \humhub\modules\thiscoveryTranslate\services\FormTranslateAdapter())
            ->translateForm($form, $only);
        $this->stdout('stats=' . json_encode($stats) . "\n");
        $langs = $only ? [$only] : array_values(array_filter(
            $form->getEnabledLanguages(),
            static fn($l) => $l !== $form->getSourceLanguage()
        ));
        foreach ($langs as $code) {
            $this->stdout("-- {$code} --\n");
            foreach ($form->fields as $field) {
                $hasChoices = \humhub\modules\thiscoveryForms\models\FormField::isChoiceType($field->type)
                    || str_contains((string)$field->options_json, '"rows"')
                    || str_contains((string)$field->options_json, '"options"');
                if (!$hasChoices) {
                    continue;
                }
                $row = \humhub\modules\thiscoveryForms\models\FormFieldI18n::findOne([
                    'field_id' => $field->id,
                    'language' => $code,
                ]);
                $opts = $row ? $row->getOptionsOverlay() : [];
                $this->stdout(sprintf(
                    "  #%d %s label=%s options=%s\n",
                    $field->id,
                    $field->type,
                    $row ? mb_substr((string)$row->label, 0, 40) : '(none)',
                    json_encode($opts['options'] ?? $opts['rows'] ?? null, JSON_UNESCAPED_UNICODE)
                ));
            }
        }
        return ExitCode::OK;
    }

    /** Translate engagement page block settings for a language (smoke). */
    public function actionPage(int $id = 6, string $lang = 'cy')
    {
        $settings = ModuleSettings::loadSettings();
        \Yii::$app->language = $lang;
        $page = \humhub\modules\thiscoveryPageBuilder\models\EngagementPage::findOne($id);
        if (!$page) {
            $this->stderr("Page {$id} not found\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $this->stdout("page={$page->id} slug={$page->slug}\n");
        foreach ($page->getSections() as $section) {
            $type = (string)($section['type'] ?? '');
            $raw = (array)($section['settings'] ?? []);
            $out = \humhub\modules\thiscoveryTranslate\services\PageBuilderHook::translateBlockSettings(
                $page,
                $type,
                $raw
            );
            $titleIn = (string)($raw['title'] ?? $raw['headline'] ?? '');
            $titleOut = (string)($out['title'] ?? $out['headline'] ?? '');
            if ($titleIn !== '' || $titleOut !== '') {
                $this->stdout("[{$type}] {$titleIn} => {$titleOut}\n");
            }
            if ($type === 'phases' && !empty($out['items'])) {
                foreach ($out['items'] as $i => $item) {
                    $this->stdout("  phase{$i}: " . ($item['label'] ?? '') . "\n");
                }
            }
            if ($type === 'updates') {
                $this->stdout('  intro=' . mb_substr((string)($out['intro'] ?? ''), 0, 80) . "\n");
                $this->stdout('  button=' . ($out['button_label'] ?? '') . "\n");
            }
        }
        return ExitCode::OK;
    }
}
