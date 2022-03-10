<?php

namespace benf\neo\migrations;

use benf\neo\models\BlockStructure;
use benf\neo\Plugin as Neo;
use Craft;
use craft\db\Migration;
use craft\db\Query;

/**
 * m210812_052349_fix_single_site_block_structures migration.
 *
 * @package benf\neo\migrations
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 2.11.6
 */
class m210812_052349_fix_single_site_block_structures extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Not single-site, nothing to do
        if (Craft::$app->getIsMultiSite()) {
            return true;
        }

        // Get all block structures with a null ownerSiteId, where another block structure exists with the same fieldId
        // and ownerId but a non-null ownerSiteId
        $haveNullSiteId = (new Query())
            ->select([
                '[[del.id]] as id',
                '[[del.structureId]] as structureId',
                '[[del.ownerSiteId]] as ownerSiteId',
                '[[del.ownerId]] as ownerId',
                '[[del.fieldId]] as fieldId',
            ])
            ->from(['{{%neoblockstructures}} del'])
            ->innerJoin(
                ['keep' => '{{%neoblockstructures}}'],
                [
                    'and',
                    '[[del.fieldId]] = [[keep.fieldId]]',
                    '[[del.ownerId]] = [[keep.ownerId]]',
                    '[[del.id]] <> [[keep.id]]',
                ]
            )
            ->where(['[[del.ownerSiteId]]' => null])
            ->all();

        // Delete block structures with null ownerSiteId where the other block structure exists
        foreach ($haveNullSiteId as $row) {
            Neo::$plugin->blocks->deleteStructure(new BlockStructure($row));
        }

        // For any block structures left with null ownerSiteId, update them with the primary site ID
        Craft::$app->getDb()
            ->createCommand()
            ->update('{{%neoblockstructures}}', [
                'ownerSiteId' => Craft::$app->getSites()->getCurrentSite()->id,
            ], ['ownerSiteId' => null], [], false)
            ->execute();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210812_052349_fix_single_site_block_structures cannot be reverted.\n";
        return false;
    }
}
