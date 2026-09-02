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
use humhub\modules\thiscoveryTranslate\services\TranslationTransferService;
use humhub\modules\thiscoveryTranslate\services\ContentProtector;
use humhub\modules\thiscoveryTranslate\services\CostTracker;
use humhub\modules\thiscoveryTranslate\services\LocaleMap;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\Response;
use yii\web\UploadedFile;

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
        $req = Yii::$app->request;
        $sourceLang = trim((string)$req->get('source_language', ''));
        $targetLang = trim((string)$req->get('target_language', ''));
        $q = trim((string)$req->get('q', ''));
        $leaked = (string)$req->get('leaked', '') === '1';

        $query = TranslationMemoryEntry::find()->orderBy(['usage_count' => SORT_DESC, 'updated_at' => SORT_DESC]);
        if ($sourceLang !== '') {
            $query->andWhere(['source_language' => LocaleMap::toAmazon($sourceLang)]);
        }
        if ($targetLang !== '') {
            $query->andWhere(['target_language' => LocaleMap::toAmazon($targetLang)]);
        }
        if ($q !== '') {
            $query->andWhere([
                'or',
                ['like', 'source_text', $q],
                ['like', 'translated_text', $q],
                ['like', 'context', $q],
            ]);
        }
        if ($leaked) {
            $query->andWhere(['or',
                ['like', 'translated_text', 'ZZTT'],
                ['like', 'translated_text', 'ZTT'],
                ['like', 'translated_text', 'data-tth'],
            ]);
        }

        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 50],
        ]);
        return $this->render('memory', [
            'provider' => $provider,
            'activeTab' => 'memory',
            'filterSource' => $sourceLang,
            'filterTarget' => $targetLang,
            'filterQ' => $q,
            'filterLeaked' => $leaked,
            'languageOptions' => $this->filterLanguageOptions(),
        ]);
    }

    public function actionTranslations()
    {
        $req = Yii::$app->request;
        $sourceLang = trim((string)$req->get('source_language', ''));
        $targetLang = trim((string)$req->get('target_language', ''));
        $q = trim((string)$req->get('q', ''));
        $leaked = (string)$req->get('leaked', '') === '1';
        $objectType = trim((string)$req->get('object_type', ''));

        $query = Translation::find()->orderBy(['updated_at' => SORT_DESC]);
        if ($sourceLang !== '') {
            $query->andWhere(['source_language' => LocaleMap::toAmazon($sourceLang)]);
        }
        if ($targetLang !== '') {
            $query->andWhere(['target_language' => LocaleMap::toAmazon($targetLang)]);
        }
        if ($objectType !== '') {
            $query->andWhere(['object_type' => $objectType]);
        }
        if ($q !== '') {
            $query->andWhere([
                'or',
                ['like', 'source_text', $q],
                ['like', 'translated_text', $q],
                ['like', 'field', $q],
                ['like', 'object_id', $q],
            ]);
        }
        if ($leaked) {
            $query->andWhere(['or',
                ['like', 'translated_text', 'ZZTT'],
                ['like', 'translated_text', 'ZTT'],
                ['like', 'translated_text', 'data-tth'],
            ]);
        }

        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 50],
        ]);
        return $this->render('translations', [
            'provider' => $provider,
            'activeTab' => 'translations',
            'filterSource' => $sourceLang,
            'filterTarget' => $targetLang,
            'filterQ' => $q,
            'filterLeaked' => $leaked,
            'filterObjectType' => $objectType,
            'languageOptions' => $this->filterLanguageOptions(),
            'objectTypeOptions' => Translation::find()->select('object_type')->distinct()->orderBy(['object_type' => SORT_ASC])->column(),
        ]);
    }

    public function actionPurgeLeaked()
    {
        $this->forcePostRequest();
        $nTrans = 0;
        $nMem = 0;
        $nForms = 0;
        foreach (Translation::find()->each(100) as $row) {
            /** @var Translation $row */
            // Locked/manual rows that still contain protector placeholders are corrupt — delete so they regenerate.
            if (ContentProtector::looksLeaked((string)$row->translated_text)) {
                $row->delete();
                $nTrans++;
            }
        }
        foreach (TranslationMemoryEntry::find()->each(100) as $row) {
            /** @var TranslationMemoryEntry $row */
            if (ContentProtector::looksLeaked((string)$row->translated_text)) {
                $row->delete();
                $nMem++;
            }
        }
        if (class_exists(\humhub\modules\thiscoveryForms\models\FormI18n::class)) {
            foreach (\humhub\modules\thiscoveryForms\models\FormI18n::find()->each(100) as $row) {
                $blob = implode("\n", [
                    (string)$row->title,
                    (string)$row->description,
                    (string)$row->thank_you_content,
                ]);
                if (ContentProtector::looksLeaked($blob)) {
                    $row->delete();
                    $nForms++;
                }
            }
            foreach (\humhub\modules\thiscoveryForms\models\FormFieldI18n::find()->each(100) as $row) {
                $blob = implode("\n", [
                    (string)$row->label,
                    (string)$row->help_text,
                    (string)$row->options_json,
                ]);
                if (ContentProtector::looksLeaked($blob)) {
                    $row->delete();
                    $nForms++;
                }
            }
        }
        $this->view->success(Yii::t('ThiscoveryTranslateModule.base', 'Purged {t} leaked translations, {m} translation-memory rows, and {f} form i18n rows. Reload affected pages to regenerate.', [
            't' => $nTrans,
            'm' => $nMem,
            'f' => $nForms,
        ]));
        return $this->redirect(['maintenance']);
    }

    /**
     * @return array<string, string>
     */
    private function filterLanguageOptions(): array
    {
        $settings = ModuleSettings::loadSettings();
        $opts = ['' => Yii::t('ThiscoveryTranslateModule.base', 'All languages')];
        foreach ($settings->availableLanguages as $code) {
            $opts[$code] = (LocaleMap::labels()[$code] ?? $code) . ' (' . $code . ')';
        }
        // Also include Amazon codes already present in DB so filters work for historical rows.
        foreach (['en', 'hi', 'cy', 'fr', 'pa', 'gu', 'bn'] as $code) {
            if (!isset($opts[$code])) {
                $opts[$code] = $code;
            }
        }
        return $opts;
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

    public function actionTransfer()
    {
        return $this->render('transfer', ['activeTab' => 'transfer']);
    }

    public function actionExport()
    {
        $includeForms = (string)Yii::$app->request->get('forms', Yii::$app->request->post('forms', '')) === '1';
        $service = new TranslationTransferService();
        try {
            $json = $service->exportJson($includeForms);
        } catch (\Throwable $e) {
            $this->view->error(Yii::t('ThiscoveryTranslateModule.base', 'Export failed: {error}', [
                'error' => $e->getMessage(),
            ]));
            return $this->redirect(['transfer']);
        }

        $filename = 'thiscovery-translate-export-' . date('Ymd-His') . '.json';
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'application/json; charset=UTF-8');
        Yii::$app->response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        Yii::$app->response->headers->set('Content-Length', (string)strlen($json));
        return $json;
    }

    public function actionImport()
    {
        $this->forcePostRequest();
        $file = UploadedFile::getInstanceByName('importFile');
        if ($file === null || $file->error !== UPLOAD_ERR_OK) {
            $this->view->error(Yii::t('ThiscoveryTranslateModule.base', 'Please upload a valid JSON file.'));
            return $this->redirect(['transfer']);
        }

        $raw = @file_get_contents($file->tempName);
        if ($raw === false || $raw === '') {
            $this->view->error(Yii::t('ThiscoveryTranslateModule.base', 'Could not read uploaded file.'));
            return $this->redirect(['transfer']);
        }

        $mergeSettings = (string)Yii::$app->request->post('merge_settings', '') === '1';
        $service = new TranslationTransferService();
        try {
            $counts = $service->importJson($raw, $mergeSettings);
        } catch (\Throwable $e) {
            $this->view->error(Yii::t('ThiscoveryTranslateModule.base', 'Import failed: {error}', [
                'error' => $e->getMessage(),
            ]));
            return $this->redirect(['transfer']);
        }

        $parts = [];
        foreach ($counts as $k => $n) {
            $parts[] = $k . '=' . $n;
        }
        $this->view->success(Yii::t('ThiscoveryTranslateModule.base', 'Import completed: {counts}', [
            'counts' => implode(', ', $parts),
        ]));
        return $this->redirect(['transfer']);
    }


}
