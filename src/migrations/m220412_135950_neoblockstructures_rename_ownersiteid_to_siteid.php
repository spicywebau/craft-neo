<?php

namespace benf\neo\migrations;

use Craft;
use craft\db\Migration;

/**
 * m220412_135950_neoblockstructures_rename_ownersiteid_to_siteid migration.
 */
class m220412_135950_neoblockstructures_rename_ownersiteid_to_siteid extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->renameColumn('{{%neoblockstructures}}', 'ownerSiteId', 'siteId');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220412_135950_neoblockstructures_rename_ownersiteid_to_siteid cannot be reverted.\n";
        return false;
    }
}
