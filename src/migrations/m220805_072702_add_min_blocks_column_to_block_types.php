<?php

namespace benf\neo\migrations;

use Craft;
use craft\db\Migration;

/**
 * m220805_072702_add_min_blocks_column_to_block_types migration.
 */
class m220805_072702_add_min_blocks_column_to_block_types extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(
            '{{%neoblocktypes}}',
            'minBlocks',
            $this->smallInteger()->unsigned()->defaultValue(0)->after('description')
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220805_072702_add_min_blocks_column_to_block_types cannot be reverted.\n";
        return false;
    }
}
