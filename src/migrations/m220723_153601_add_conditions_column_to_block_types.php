<?php

namespace benf\neo\migrations;

use craft\db\Migration;

/**
 * m220723_153601_add_conditions_column_to_block_types migration.
 */
class m220723_153601_add_conditions_column_to_block_types extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(
            '{{%neoblocktypes}}',
            'conditions',
            $this->text()->after('sortOrder'),
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220723_153601_add_conditions_column_to_block_types cannot be reverted.\n";
        return false;
    }
}
