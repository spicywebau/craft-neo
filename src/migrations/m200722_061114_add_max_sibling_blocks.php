<?php

namespace benf\neo\migrations;

use Craft;
use craft\db\Migration;

/**
 * m200722_061114_add_max_sibling_blocks migration.
 *
 * @package benf\neo\migrations
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 2.8.0
 */
class m200722_061114_add_max_sibling_blocks extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(
            '{{%neoblocktypes}}',
            'maxSiblingBlocks',
            $this->smallInteger()->unsigned()->defaultValue(0)->after('maxBlocks')
        );
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200722_061114_add_max_sibling_blocks cannot be reverted.\n";
        return false;
    }
}
