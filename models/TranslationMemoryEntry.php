<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $source_language
 * @property string $target_language
 * @property string $source_hash
 * @property string $source_text
 * @property string $translated_text
 * @property string $context
 * @property string $translation_method
 * @property bool $is_verified
 * @property int $usage_count
 * @property string $created_at
 * @property string $updated_at
 */
class TranslationMemoryEntry extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%thiscovery_translation_memory}}';
    }

    public function rules()
    {
        return [
            [['source_language', 'target_language', 'source_hash', 'source_text', 'translated_text'], 'required'],
            [['source_text', 'translated_text'], 'string'],
            [['is_verified'], 'boolean'],
            [['usage_count'], 'integer'],
            [['source_language', 'target_language', 'source_hash', 'context', 'translation_method'], 'string', 'max' => 128],
        ];
    }
}
