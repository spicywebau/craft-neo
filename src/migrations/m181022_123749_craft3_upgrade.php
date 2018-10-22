<?php

namespace benf\neo\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\MigrationHelper;

use benf\neo\Field;
use benf\neo\elements\Block;

/**
 * m181022_123749_craft3_upgrade migration.
 */
class m181022_123749_craft3_upgrade extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
		$cacheService = Craft::$app->getCache();

		$this->update('{{%elements}}', [
			'type' => Block::class
		], ['type' => 'Neo_Block']);

		$this->update('{{%fields}}', [
			'type' => Field::class
		], ['type' => 'Neo']);

		// Move the `neoblocks` table's `collapsed` column data to the cache and then drop the column
		$blocks = (new Query())
			->select(['id', 'collapsed'])
			->from(['{{%neoBlocks}}'])
			->all();

		foreach ($blocks as $block)
		{
			$cacheKey = "neoblock-" . $block['id'] . "-collapsed";

			if ($block['collapsed'])
			{
				$cacheService->add($cacheKey, 1);
			}
		}

		$this->dropColumn('{{%neoblocks}}', 'collapsed');

		// Rename `ownerLocale__siteId` columns to `ownerSiteId` and drop old `ownerLocale` columns
		MigrationHelper::renameColumn('{{%neoblocks}}', 'ownerLocale__siteId', 'ownerSiteId', $this);
		$this->dropColumn('{{%neoblocks}}', 'ownerLocale');
		MigrationHelper::renameColumn('{{%neoblockstructures}}', 'ownerLocale__siteId', 'ownerSiteId', $this);
		$this->dropColumn('{{%neoblockstructures}}', 'ownerLocale');

		// Rename `neogroups` table to `neoblocktypegroups`
		MigrationHelper::renameTable('{{%neogroups}}', '{{%neoblocktypegroups}}', $this);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m181022_123749_craft3_upgrade cannot be reverted.\n";
        return false;
    }
}
