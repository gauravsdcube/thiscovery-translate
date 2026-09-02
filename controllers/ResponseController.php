<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\controllers;

use humhub\components\Controller;
use humhub\modules\thiscoveryForms\models\FormAnswerField;
use humhub\modules\thiscoveryTranslate\models\ModuleSettings;
use humhub\modules\thiscoveryTranslate\services\ResponseTranslateService;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Optional evaluator translation of free-text form answers.
 */
class ResponseController extends Controller
{
    public function actionTranslate()
    {
        $this->forcePostRequest();
        if (Yii::$app->user->isGuest) {
            throw new ForbiddenHttpException();
        }
        if (!ModuleSettings::isFormsTranslateEnabled()) {
            return $this->asJson(['success' => false, 'error' => 'Forms translation disabled']);
        }

        $answerFieldId = (int)Yii::$app->request->post('answer_field_id', 0);
        $original = (string)Yii::$app->request->post('original_text', '');
        $responseLanguage = (string)Yii::$app->request->post('response_language', 'en-GB');
        $target = (string)Yii::$app->request->post('target_language', Yii::$app->language);
        if ($answerFieldId <= 0 || trim($original) === '') {
            return $this->asJson(['success' => false, 'error' => 'Missing fields']);
        }

        if (!class_exists(FormAnswerField::class)) {
            throw new NotFoundHttpException();
        }
        $af = FormAnswerField::findOne($answerFieldId);
        if (!$af || !$af->answer || !$af->answer->form) {
            throw new NotFoundHttpException();
        }
        if (!$af->answer->form->canManage()) {
            throw new ForbiddenHttpException();
        }

        $svc = new ResponseTranslateService();
        $translated = $svc->translateResponse($answerFieldId, $original, $responseLanguage, $target);
        return $this->asJson([
            'success' => true,
            'original' => $original,
            'translated' => $translated,
            'target_language' => $target,
        ]);
    }
}
