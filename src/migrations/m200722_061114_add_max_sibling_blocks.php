<?php

namespace benf\neo\migrations;

use Craft;
use craft\db\Migration;

/**
 * Adds the `maxSiblingBlocks` column to the `neoblocktypes` table.
 */
class m200722_061114_add_max_sibling_blocks extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(
            '{{%neoblocktypes}}',
            'maxSiblingBlocks',
            $this->smallInteger()->unsigned()->defaultValue(0)->after('maxBlocks')
        );
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200722_061114_add_max_sibling_blocks cannot be reverted.\n";
        return false;
    }
}
