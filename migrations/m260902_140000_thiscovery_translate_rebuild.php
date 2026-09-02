<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use yii\db\Migration;

/**
 * Authoritative translation store, memory, terminology, usage.
 * Replaces thiscovery_translate_cache / thiscovery_translate_override.
 */
class m260902_140000_thiscovery_translate_rebuild extends Migration
{
    public function safeUp()
    {
        if ($this->db->getTableSchema('{{%thiscovery_translate_cache}}', true) !== null) {
            $this->dropTable('{{%thiscovery_translate_cache}}');
        }
        if ($this->db->getTableSchema('{{%thiscovery_translate_override}}', true) !== null) {
            $this->dropTable('{{%thiscovery_translate_override}}');
        }

        $this->createTable('{{%thiscovery_translation}}', [
            'id' => $this->primaryKey(),
            'object_type' => $this->string(64)->notNull(),
            'object_id' => $this->string(64)->notNull()->defaultValue(''),
            'field' => $this->string(128)->notNull()->defaultValue(''),
            'source_language' => $this->string(16)->notNull(),
            'target_language' => $this->string(16)->notNull(),
            'source_text' => $this->text()->notNull(),
            'source_hash' => $this->string(64)->notNull(),
            'translated_text' => $this->text()->notNull(),
            'translation_method' => $this->string(16)->notNull()->defaultValue('amazon'),
            'translation_status' => $this->string(32)->notNull()->defaultValue('machine'),
            'is_manual' => $this->boolean()->notNull()->defaultValue(false),
            'is_locked' => $this->boolean()->notNull()->defaultValue(false),
            'context' => $this->string(64)->null(),
            'terminology_version' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
            'translated_at' => $this->dateTime()->null(),
        ]);
        $this->createIndex(
            'idx_tt_lookup',
            '{{%thiscovery_translation}}',
            ['object_type', 'object_id', 'field', 'target_language', 'source_hash'],
            true
        );
        $this->createIndex('idx_tt_object', '{{%thiscovery_translation}}', ['object_type', 'object_id', 'target_language']);
        $this->createIndex('idx_tt_status', '{{%thiscovery_translation}}', ['translation_status', 'is_locked']);

        $this->createTable('{{%thiscovery_translation_memory}}', [
            'id' => $this->primaryKey(),
            'source_language' => $this->string(16)->notNull(),
            'target_language' => $this->string(16)->notNull(),
            'source_hash' => $this->string(64)->notNull(),
            'source_text' => $this->text()->notNull(),
            'translated_text' => $this->text()->notNull(),
            'context' => $this->string(64)->notNull()->defaultValue('generic'),
            'translation_method' => $this->string(16)->notNull()->defaultValue('amazon'),
            'is_verified' => $this->boolean()->notNull()->defaultValue(false),
            'usage_count' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex(
            'idx_ttm_lookup',
            '{{%thiscovery_translation_memory}}',
            ['source_language', 'target_language', 'source_hash', 'context'],
            true
        );

        $this->createTable('{{%thiscovery_translation_terminology}}', [
            'id' => $this->primaryKey(),
            'source_term' => $this->string(255)->notNull(),
            'target_language' => $this->string(16)->notNull()->defaultValue('*'),
            'preferred_translation' => $this->string(512)->null(),
            'do_not_translate' => $this->boolean()->notNull()->defaultValue(false),
            'description' => $this->string(512)->null(),
            'context' => $this->string(64)->null(),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex(
            'idx_ttt_term',
            '{{%thiscovery_translation_terminology}}',
            ['source_term', 'target_language'],
            true
        );

        $this->createTable('{{%thiscovery_translation_usage}}', [
            'id' => $this->bigPrimaryKey(),
            'created_at' => $this->dateTime()->notNull(),
            'module' => $this->string(64)->null(),
            'object_type' => $this->string(64)->null(),
            'source_language' => $this->string(16)->null(),
            'target_language' => $this->string(16)->null(),
            'character_count' => $this->integer()->notNull()->defaultValue(0),
            'provider' => $this->string(32)->notNull()->defaultValue('none'),
            'request_type' => $this->string(32)->notNull()->defaultValue('lookup'),
            'cache_hit' => $this->boolean()->notNull()->defaultValue(false),
            'hit_kind' => $this->string(32)->null(),
        ]);
        $this->createIndex('idx_ttu_created', '{{%thiscovery_translation_usage}}', ['created_at']);
        $this->createIndex('idx_ttu_provider', '{{%thiscovery_translation_usage}}', ['provider', 'created_at']);
    }

    public function safeDown()
    {
        $this->dropTable('{{%thiscovery_translation_usage}}');
        $this->dropTable('{{%thiscovery_translation_terminology}}');
        $this->dropTable('{{%thiscovery_translation_memory}}');
        $this->dropTable('{{%thiscovery_translation}}');
    }
}
