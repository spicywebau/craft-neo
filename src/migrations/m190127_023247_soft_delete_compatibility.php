<?php

namespace benf\neo\migrations;

use craft\db\Migration;

/**
 * m190127_023247_soft_delete_compatibility migration.
 *
 * @package benf\neo\migrations
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 2.2.0
 */
class m190127_023247_soft_delete_compatibility extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%neoblocks}}', 'deletedWithOwner', $this->boolean()->null()->after('typeId'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190127_023247_soft_delete_compatibility cannot be reverted.\n";

        return false;
    }
}
