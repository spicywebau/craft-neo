<?php

namespace benf\neo\migrations;

use craft\db\Migration;
use craft\db\Table;

/**
 * m240711_024245_block_type_entry_type migration.
 */
class m240711_024245_block_type_entry_type extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%neoblocktypes}}', 'entryTypeId', $this->integer()->after('groupId'));
        $this->addForeignKey(null, '{{%neoblocktypes}}', ['entryTypeId'], Table::ENTRYTYPES, ['id'], 'SET NULL', null);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240711_024245_block_type_entry_type cannot be reverted.\n";
        return false;
    }
}
