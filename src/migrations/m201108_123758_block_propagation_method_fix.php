<?php

namespace benf\neo\migrations;

use benf\neo\elements\Block;
use benf\neo\Field;
use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Queue;
use craft\queue\jobs\ApplyNewPropagationMethod;

/**
 * This migration used to fix Neo blocks that hadn't had their field's new propagation method applied.
 * This fix is now handled by the `craft neo/fields/reapply-propagation-method --by-block-structure` command.
 *
 * @package benf\neo\migrations
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 2.8.14
 */
class m201108_123758_block_propagation_method_fix extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Moved to a console command
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m201108_123758_block_propagation_method_fix cannot be reverted.\n";
        return false;
    }
}
