<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\admin\permissions\ManageModules;
use humhub\modules\thiscoveryTranslate\models\ModuleSettings;
use humhub\modules\thiscoveryTranslate\models\Translation;
use humhub\modules\thiscoveryTranslate\models\TranslationMemoryEntry;
use humhub\modules\thiscoveryTranslate\models\TranslationTerminology;
use humhub\modules\thiscoveryTranslate\models\TranslationUsage;
use humhub\modules\thiscoveryTranslate\services\AmazonTranslateProvider;
use humhub\modules\thiscoveryTranslate\services\CostTracker;
use humhub\modules\thiscoveryTranslate\services\LocaleMap;
use Yii;
use yii\data\ActiveDataProvider;

class AdminController extends Controller
{
    public $adminOnly = false;
    /** Keep HumHub admin left navigation (do not replace with a full-page layout). */
    public $subLayout = '@humhub/modules/admin/views/layouts/main';

    protected function getAccessRules()
    {
        return [
            ['permissions' => ManageModules::class],
        ];
    }

    public function actionIndex()
    {
        $model = ModuleSettings::loadSettings();
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $this->view->success(Yii::t('ThiscoveryTranslateModule.base', 'Settings saved.'));
            return $this->redirect(['index']);
        }
        return $this->render('index', [
            'model' => $model,
            'languageCatalog' => LocaleMap::labels(),
            'activeTab' => 'settings',
        ]);
    }

    public function actionHelp()
    {
        return $this->render('help', [
            'activeTab' => 'help',
        ]);
    }

    public function actionTest()
    {
        $this->forcePostRequest();
        $settings = ModuleSettings::loadSettings();
        try {
            $client = new AmazonTranslateProvider($settings);
            $out = $client->translate('Hello', 'en', 'cy', 'text');
            $this->view->success(Yii::t('ThiscoveryTranslateModule.base', 'Translate test OK: {result}', ['result' => $out]));
        } catch (\Throwable $e) {
            $this->view->error(Yii::t('ThiscoveryTranslateModule.base', 'Translate test failed: {error}', [
                'error' => $e->getMessage(),
            ]));
        }
        return $this->redirect(['index']);
    }

    public function actionLanguages()
    {
        return $this->render('languages', [
            'model' => ModuleSettings::loadSettings(),
            'catalog' => LocaleMap::catalog(),
            'natives' => LocaleMap::nativeLabels(),
            'activeTab' => 'languages',
        ]);
    }

    public function actionTerminology()
    {
        $model = new TranslationTerminology();
        if ($model->load(Yii::$app->request->post())) {
            $now = date('Y-m-d H:i:s');
            $model->created_at = $now;
            $model->updated_at = $now;
            if ($model->target_language === '') {
                $model->target_language = '*';
            }
            if ($model->is_active === null || $model->is_active === '') {
                $model->is_active = true;
            }
            if ($model->save()) {
                $this->view->success(Yii::t('ThiscoveryTranslateModule.base', 'Terminology saved.'));
                return $this->redirect(['terminology']);
            }
        }
        $model->is_active = true;
        $provider = new ActiveDataProvider([
            'query' => TranslationTerminology::find()->orderBy(['source_term' => SORT_ASC]),
            'pagination' => ['pageSize' => 50],
        ]);
        return $this->render('terminology', [
            'provider' => $provider,
            'model' => $model,
            'activeTab' => 'terminology',
        ]);
    }

    public function actionTerminologyDelete($id)
    {
        $this->forcePostRequest();
        $row = TranslationTerminology::findOne((int)$id);
        if ($row) {
            $row->delete();
            $this->view->success(Yii::t('ThiscoveryTranslateModule.base', 'Terminology deleted.'));
        }
        return $this->redirect(['terminology']);
    }

    public function actionMemory()
    {
        $provider = new ActiveDataProvider([
            'query' => TranslationMemoryEntry::find()->orderBy(['usage_count' => SORT_DESC, 'updated_at' => SORT_DESC]),
            'pagination' => ['pageSize' => 50],
        ]);
        return $this->render('memory', [
            'provider' => $provider,
            'activeTab' => 'memory',
        ]);
    }

    public function actionMemoryVerify($id)
    {
        $this->forcePostRequest();
        $row = TranslationMemoryEntry::findOne((int)$id);
        if ($row) {
            $row->is_verified = true;
            $row->updated_at = date('Y-m-d H:i:s');
            $row->save(false);
            $this->view->success(Yii::t('ThiscoveryTranslateModule.base', 'Translation memory entry verified.'));
        }
        return $this->redirect(['memory']);
    }

    public function actionTranslations()
    {
        $provider = new ActiveDataProvider([
            'query' => Translation::find()->orderBy(['updated_at' => SORT_DESC]),
            'pagination' => ['pageSize' => 50],
        ]);
        return $this->render('translations', [
            'provider' => $provider,
            'activeTab' => 'translations',
        ]);
    }

    public function actionTranslationLock($id)
    {
        $this->forcePostRequest();
        $row = Translation::findOne((int)$id);
        if ($row) {
            $text = (string)Yii::$app->request->post('translated_text', $row->translated_text);
            $row->translated_text = $text;
            $row->is_manual = true;
            $row->is_locked = true;
            $row->translation_method = Translation::METHOD_MANUAL;
            $row->translation_status = Translation::STATUS_VERIFIED;
            $row->updated_at = date('Y-m-d H:i:s');
            $row->save(false);
            $this->view->success(Yii::t('ThiscoveryTranslateModule.base', 'Translation locked.'));
        }
        return $this->redirect(['translations']);
    }

    public function actionUsage()
    {
        $settings = ModuleSettings::loadSettings();
        $cost = new CostTracker();
        $monthStart = gmdate('Y-m-01 00:00:00');
        $total = (int)TranslationUsage::find()->where(['>=', 'created_at', $monthStart])->count();
        $aws = (int)TranslationUsage::find()->where(['>=', 'created_at', $monthStart])->andWhere(['provider' => 'amazon'])->count();
        $hits = (int)TranslationUsage::find()->where(['>=', 'created_at', $monthStart])->andWhere(['cache_hit' => true])->count();
        $chars = (int)TranslationUsage::find()->where(['>=', 'created_at', $monthStart])->andWhere(['provider' => 'amazon'])->sum('character_count');
        $hitRate = $total > 0 ? round(($hits / $total) * 100, 1) : 0.0;
        $est = ($chars / 1000000) * $settings->estimatedCostPerMillion;

        return $this->render('usage', [
            'activeTab' => 'usage',
            'monthlyChars' => $cost->monthlyCharsUsed(),
            'requests' => $total,
            'awsRequests' => $aws,
            'avoided' => max(0, $total - $aws),
            'hitRate' => $hitRate,
            'awsChars' => $chars,
            'estimatedCost' => $est,
            'settings' => $settings,
            'pastWarning' => $cost->pastWarning($settings),
        ]);
    }

    public function actionMaintenance()
    {
        return $this->render('maintenance', ['activeTab' => 'maintenance']);
    }

    public function actionClearMachine()
    {
        $this->forcePostRequest();
        $n = Translation::deleteAll(['is_locked' => false, 'is_manual' => false, 'translation_method' => Translation::METHOD_AMAZON]);
        $this->view->success(Yii::t('ThiscoveryTranslateModule.base', 'Deleted {n} machine translations.', ['n' => $n]));
        return $this->redirect(['maintenance']);
    }

    public function actionBumpTerminology()
    {
        $this->forcePostRequest();
        $settings = ModuleSettings::loadSettings();
        $settings->terminologyVersion = max(1, $settings->terminologyVersion) + 1;
        $settings->save();
        $this->view->success(Yii::t('ThiscoveryTranslateModule.base', 'Terminology version is now {v}. Use regenerate actions to refresh affected content.', [
            'v' => $settings->terminologyVersion,
        ]));
        return $this->redirect(['maintenance']);
    }

    public function actionRegenerateStale()
    {
        $this->forcePostRequest();
        Yii::$app->queue->push(new \humhub\modules\thiscoveryTranslate\jobs\RetranslateChangedContentJob());
        $this->view->success(Yii::t('ThiscoveryTranslateModule.base', 'Queued regeneration of stale (unlocked) translations.'));
        return $this->redirect(['maintenance']);
    }
}
