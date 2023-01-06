<?php

namespace benf\neo\migrations;

use Craft;
use craft\db\Migration;

/**
 * m221231_110307_add_block_type_icon_property migration.
 */
class m221231_110307_add_block_type_icon_property extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%neoblocktypes}}', 'iconId', $this->integer()->after('description'));
        $this->addForeignKey(null, '{{%neoblocktypes}}', ['iconId'], '{{%assets}}', ['id'], 'SET NULL', null);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m221231_110307_add_block_type_icon_property cannot be reverted.\n";
        return false;
    }
}
