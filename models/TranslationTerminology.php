<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $source_term
 * @property string $target_language
 * @property string|null $preferred_translation
 * @property bool $do_not_translate
 * @property string|null $description
 * @property string|null $context
 * @property bool $is_active
 * @property string $created_at
 * @property string $updated_at
 */
class TranslationTerminology extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%thiscovery_translation_terminology}}';
    }

    public function rules()
    {
        return [
            [['source_term'], 'required'],
            [['do_not_translate', 'is_active'], 'boolean'],
            [['source_term'], 'string', 'max' => 255],
            [['preferred_translation', 'description'], 'string', 'max' => 512],
            [['target_language', 'context'], 'string', 'max' => 64],
        ];
    }
}
