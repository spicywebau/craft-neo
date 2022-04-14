<?php

namespace benf\neo\migrations;

use craft\db\Migration;

/**
 * m220411_111523_remove_ownersiteid_and_uid_neoblocks_columns migration.
 */
class m220411_111523_remove_ownersiteid_and_uid_neoblocks_columns extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->dropForeignKeyIfExists('{{%neoblocks}}', ['ownerSiteId']);
        $this->dropIndexIfExists('{{%neoblocks}}', ['ownerSiteId'], false);
        $this->dropColumn('{{%neoblocks}}', 'ownerSiteId');
        $this->dropColumn('{{%neoblocks}}', 'uid');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220411_111523_remove_ownersiteid_and_uid_neoblocks_columns cannot be reverted.\n";
        return false;
    }
}
