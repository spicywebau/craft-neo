<?php

namespace benf\neo\migrations;

use craft\db\Migration;
use craft\db\Table;

/**
 * m220409_142203_neoblocks_owners_table migration.
 */
class m220409_142203_neoblocks_owners_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $blocksTable = '{{%neoblocks}}';
        $elementsTable = Table::ELEMENTS;
        $ownersTable = '{{%neoblocks_owners}}';

        $this->dropTableIfExists($ownersTable);

        $this->createTable($ownersTable, [
            'blockId' => $this->integer()->notNull(),
            'ownerId' => $this->integer()->notNull(),
            'sortOrder' => $this->smallInteger()->unsigned()->notNull(),
            'PRIMARY KEY([[blockId]], [[ownerId]])',
        ]);

        $this->addForeignKey(null, $ownersTable, ['blockId'], $blocksTable, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, $ownersTable, ['ownerId'], Table::ELEMENTS, ['id'], 'CASCADE', null);

        // Delete orphaned block rows
        if ($this->db->getIsMysql()) {
            $this->execute(<<<SQL
DELETE [[b]].* FROM $blocksTable [[b]]
LEFT JOIN $elementsTable [[e]] ON [[e.id]] = [[b.ownerId]]
WHERE [[e.id]] IS NULL
SQL);
        } else {
            $this->execute(<<<SQL
DELETE FROM $blocksTable
USING $blocksTable [[b]]
LEFT JOIN $elementsTable [[e]] ON [[e.id]] = [[b.ownerId]]
WHERE
  $blocksTable.[[id]] = [[b.id]] AND
  [[e.id]] IS NULL
SQL);
        }

        $this->execute(<<<SQL
INSERT INTO $ownersTable ([[blockId]], [[ownerId]], [[sortOrder]]) 
SELECT [[id]], [[ownerId]], COALESCE([[sortOrder]], 1) 
FROM $blocksTable
SQL);

        $this->dropIndexIfExists($blocksTable, ['sortOrder'], false);
        $this->dropColumn($blocksTable, 'sortOrder');
        $this->renameColumn($blocksTable, 'ownerId', 'primaryOwnerId');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220409_142203_neoblocks_owners_table cannot be reverted.\n";
        return false;
    }
}
