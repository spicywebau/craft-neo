<?php

namespace benf\neo\migrations;

use benf\neo\elements\Block;
use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Db;

/**
 * Deletes any Neo blocks that do not have a `sortOrder`.
 *
 * The `sortOrder` property was added in Neo 2.7.0, and Neo blocks were assigned a `sortOrder` from their associated
 * `lft` value in the `structureelements` table.  Any Neo blocks created prior to Neo 2.7.0, not associated with a block
 * structure and yet have remained in the system, will have a `sortOrder` of `null`.  These blocks can cause errors with
 * the updates in Neo 2.8.14 to apply field propagation methods to their blocks; since these blocks don't belong to any
 * block structure, they can be safely deleted.
 *
 * @package benf\neo\migrations
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 2.8.15
 * @see m200313_015120_structure_update
 * @see m201108_123758_block_propagation_method_fix
 */
class m201208_110049_delete_blocks_without_sort_order extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $elementsService = Craft::$app->getElements();
        $neoBlockIds = (new Query())
            ->select(['id'])
            ->from('{{%neoblocks}}')
            ->where('sortOrder IS NULL')
            ->column();
        $neoBlocks = Block::find()
            ->id($neoBlockIds)
            ->siteId('*')
            ->unique()
            ->anyStatus()
            ->all();

        foreach ($neoBlocks as $neoBlock) {
            $elementsService->deleteElement($neoBlock, false);
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
