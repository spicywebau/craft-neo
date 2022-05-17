<?php

namespace benf\neo\migrations;

use craft\db\Migration;
use craft\db\Table;

/**
 * m220516_124013_add_section_description migration.
 */
class m220516_124013_add_blocktype_description extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%neoblocktypes}}', 'description', $this->string()->after('handle'));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220516_124013_add_blocktype_description cannot be reverted.\n";
        return false;
    }
}
