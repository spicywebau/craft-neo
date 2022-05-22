<?php

namespace benf\neo\migrations;

use craft\db\Migration;
use craft\db\Table;

/**
 * m220522_122113_add_blocktype_icon migration.
 */
class m220522_122113_add_blocktype_icon extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%neoblocktypes}}', 'icon', $this->string()->after('description'));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220522_122113_add_blocktype_icon cannot be reverted.\n";
        return false;
    }
}
