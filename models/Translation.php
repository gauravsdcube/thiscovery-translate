<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $object_type
 * @property string $object_id
 * @property string $field
 * @property string $source_language
 * @property string $target_language
 * @property string $source_text
 * @property string $source_hash
 * @property string $translated_text
 * @property string $translation_method
 * @property string $translation_status
 * @property bool $is_manual
 * @property bool $is_locked
 * @property string|null $context
 * @property int $terminology_version
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $translated_at
 */
class Translation extends ActiveRecord
{
    public const METHOD_NATIVE = 'native';
    public const METHOD_AMAZON = 'amazon';
    public const METHOD_MEMORY = 'memory';
    public const METHOD_MANUAL = 'manual';

    public const STATUS_MACHINE = 'machine';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_NEEDS_UPDATE = 'needs_update';
    public const STATUS_FAILED = 'failed';

    public static function tableName()
    {
        return '{{%thiscovery_translation}}';
    }

    public function rules()
    {
        return [
            [['object_type', 'source_language', 'target_language', 'source_text', 'source_hash', 'translated_text'], 'required'],
            [['source_text', 'translated_text'], 'string'],
            [['is_manual', 'is_locked'], 'boolean'],
            [['terminology_version'], 'integer'],
            [['object_type', 'object_id', 'field', 'source_language', 'target_language', 'source_hash', 'translation_method', 'translation_status', 'context'], 'string', 'max' => 128],
        ];
    }
}
