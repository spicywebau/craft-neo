<?php

namespace benf\neo\migrations;

use craft\db\Migration;

/**
 * m231005_132818_add_block_type_icon_filename_property migration.
 */
class m231005_132818_add_block_type_icon_filename_property extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%neoblocktypes}}', 'iconFilename', $this->string()->after('description'));
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m231005_132818_add_block_type_icon_filename_property cannot be reverted.\n";
        return false;
    }
}
