<?php

namespace benf\neo\migrations;

use craft\db\Migration;

/**
 * m220812_115137_add_enabled_column_to_block_types migration.
 */
class m220812_115137_add_enabled_column_to_block_types extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(
            '{{%neoblocktypes}}',
            'enabled',
            $this->boolean()->defaultValue(true)->notNull()->after('description')
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220812_115137_add_enabled_column_to_block_types cannot be reverted.\n";
        return false;
    }
}
