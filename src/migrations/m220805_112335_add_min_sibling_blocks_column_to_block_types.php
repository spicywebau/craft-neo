<?php

namespace benf\neo\migrations;

use craft\db\Migration;

/**
 * m220805_112335_add_min_sibling_blocks_column_to_block_types migration.
 */
class m220805_112335_add_min_sibling_blocks_column_to_block_types extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(
            '{{%neoblocktypes}}',
            'minSiblingBlocks',
            $this->smallInteger()->unsigned()->defaultValue(0)->after('maxBlocks')
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220805_112335_add_min_sibling_blocks_column_to_block_types cannot be reverted.\n";
        return false;
    }
}
