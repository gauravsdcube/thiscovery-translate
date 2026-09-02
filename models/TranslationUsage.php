<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $created_at
 * @property string|null $module
 * @property string|null $object_type
 * @property string|null $source_language
 * @property string|null $target_language
 * @property int $character_count
 * @property string $provider
 * @property string $request_type
 * @property bool $cache_hit
 * @property string|null $hit_kind
 */
class TranslationUsage extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%thiscovery_translation_usage}}';
    }
}
