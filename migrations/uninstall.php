<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use yii\db\Migration;

class uninstall extends Migration
{
    public function up()
    {
        foreach ([
            '{{%thiscovery_translation_usage}}',
            '{{%thiscovery_translation_terminology}}',
            '{{%thiscovery_translation_memory}}',
            '{{%thiscovery_translation}}',
            '{{%thiscovery_translate_cache}}',
            '{{%thiscovery_translate_override}}',
        ] as $table) {
            if ($this->db->getTableSchema($table, true) !== null) {
                $this->dropTable($table);
            }
        }
    }

    public function down()
    {
        echo "uninstall does not support migration down.\n";
        return false;
    }
}
