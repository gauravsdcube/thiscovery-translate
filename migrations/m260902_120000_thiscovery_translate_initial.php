<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use yii\db\Migration;

class m260902_120000_thiscovery_translate_initial extends Migration
{
    public function safeUp()
    {
        $this->createTable('thiscovery_translate_cache', [
            'id' => $this->primaryKey(),
            'source_hash' => $this->string(64)->notNull(),
            'source_lang' => $this->string(16)->notNull(),
            'target_lang' => $this->string(16)->notNull(),
            'text_type' => $this->string(16)->notNull()->defaultValue('plain'),
            'translated' => $this->text()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex(
            'ux_tt_cache_lookup',
            'thiscovery_translate_cache',
            ['source_hash', 'source_lang', 'target_lang', 'text_type'],
            true
        );

        $this->createTable('thiscovery_translate_override', [
            'id' => $this->primaryKey(),
            'category' => $this->string(128)->notNull()->defaultValue(''),
            'message' => $this->text()->notNull(),
            'language' => $this->string(16)->notNull(),
            'translation' => $this->text()->notNull(),
        ]);
        $this->createIndex('ix_tt_override_lang', 'thiscovery_translate_override', ['language']);
    }

    public function safeDown()
    {
        $this->dropTable('thiscovery_translate_override');
        $this->dropTable('thiscovery_translate_cache');
    }
}
