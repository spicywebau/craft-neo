<?php

namespace benf\neo\migrations;

use craft\db\Migration;

/**
 * m220428_060316_add_group_dropdown_setting migration.
 */
class m220428_060316_add_group_dropdown_setting extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%neoblocktypegroups}}', 'alwaysShowDropdown', $this->boolean()->after('sortOrder'));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220428_060316_add_group_dropdown_setting cannot be reverted.\n";
        return false;
    }
}
