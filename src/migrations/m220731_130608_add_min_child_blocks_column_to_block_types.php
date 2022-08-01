<?php

namespace benf\neo\migrations;

use Craft;
use craft\db\Migration;

/**
 * m220731_130608_add_min_child_blocks_column_to_block_types migration.
 */
class m220731_130608_add_min_child_blocks_column_to_block_types extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(
            '{{%neoblocktypes}}',
            'minChildBlocks',
            $this->smallInteger()->unsigned()->defaultValue(0)->after('maxSiblingBlocks')
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220731_130608_add_min_child_blocks_column_to_block_types cannot be reverted.\n";
        return false;
    }
}
