<?php

namespace benf\neo\migrations;

use benf\neo\elements\Block;
use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;

/**
 * Deletes any Neo blocks with an `ownerId` that doesn't exist in the `elements` table.
 *
 * @package benf\neo\migrations
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 2.8.16
 * @see m201108_123758_block_propagation_method_fix
 */
class m201223_024137_delete_blocks_with_invalid_owner extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $elementsService = Craft::$app->getElements();
        $neoBlockIds = (new Query())
            ->select(['id'])
            ->from('{{%neoblocks}} nb')
            ->where('(SELECT COUNT(*) FROM ' . Table::ELEMENTS . ' [[el]] WHERE [[el.id]] = [[nb.ownerId]]) = 0')
            ->column();
        $neoBlocks = Block::find()
            ->id($neoBlockIds)
            ->siteId('*')
            ->unique()
            ->trashed(null)
            ->status(null)
            ->all();

        foreach ($neoBlocks as $neoBlock) {
            $elementsService->deleteElement($neoBlock, true);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m201208_110049_delete_blocks_without_sort_order cannot be reverted.\n";
        return false;
    }
}
