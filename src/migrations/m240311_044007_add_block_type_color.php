<?php

namespace benf\neo\migrations;

use craft\db\Migration;

/**
 * m240311_044007_add_block_type_color migration.
 */
class m240311_044007_add_block_type_color extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%neoblocktypes}}', 'color', $this->string()->after('iconId'));
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240311_044007_add_block_type_color cannot be reverted.\n";
        return false;
    }
}
