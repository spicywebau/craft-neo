<?php

namespace benf\neo\migrations;

use craft\db\Migration;
use craft\db\Table;

/**
 * m240224_024030_migrate_owners_table migration.
 */
class m240224_024030_migrate_owners_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->execute(sprintf(
            <<<SQL
INSERT INTO %s
SELECT * FROM %s
SQL,
            Table::ELEMENTS_OWNERS,
            '{{%neoblocks_owners}}',
        ));
        $this->dropAllForeignKeysToTable('{{%neoblocks_owners}}');
        $this->dropTable('{{%neoblocks_owners}}');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240224_024030_migrate_owners_table cannot be reverted.\n";
        return false;
    }
}
