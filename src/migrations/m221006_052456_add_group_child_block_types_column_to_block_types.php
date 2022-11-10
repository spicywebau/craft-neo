<?php

namespace benf\neo\migrations;

use craft\db\Migration;

/**
 * m221006_052456_add_group_child_block_types_column_to_block_types migration.
 */
class m221006_052456_add_group_child_block_types_column_to_block_types extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(
            '{{%neoblocktypes}}',
            'groupChildBlockTypes',
            $this->boolean()->defaultValue(true)->notNull()->after('maxChildBlocks')
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m221006_052456_add_group_child_block_types_column_to_block_types cannot be reverted.\n";
        return false;
    }
}
