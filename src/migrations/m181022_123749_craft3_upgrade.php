<?php

namespace benf\neo\migrations;

use benf\neo\elements\Block;
use benf\neo\Field;
use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;
use craft\helpers\MigrationHelper;

/**
 * m181022_123749_craft3_upgrade migration.
 *
 * @package benf\neo\migrations
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 2.0.0
 */
class m181022_123749_craft3_upgrade extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Due to an issue with Neo 2 installations from pre-release trying to run this migration on update to Neo 2.2,
        // we need to ensure that this is actually an upgrade from the latest release of Neo 1. We can determine this by
        // checking whether the `neoblocks` table has a `collapsed` column, since new installs on Craft 3 won't have it.
        $dbService = Craft::$app->getDb();
        $hasCollapsed = $dbService->getSchema()->getTableSchema('{{%neoblocks}}')->getColumn('collapsed') !== null;

        if (!$hasCollapsed) {
            return;
        }

        // Now, proceed with the upgrade
        $this->update('{{%elements}}', ['type' => Block::class], ['type' => 'Neo_Block']);
        $this->update('{{%fields}}', ['type' => Field::class], ['type' => 'Neo']);

        // Move the `neoblocks` table's `collapsed` column data to the cache and then drop the column
        $blocks = (new Query())
            ->select(['id', 'collapsed'])
            ->from(['{{%neoblocks}}'])
            ->all();

        foreach ($blocks as $block) {
            $cacheKey = "neoblock-" . $block['id'] . "-collapsed";

            if ($block['collapsed']) {
                Craft::$app->getCache()->add($cacheKey, 1);
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

        // Update Neo fields' propagation methods
        $fields = (new Query())
            ->select(['id', 'type', 'translationMethod', 'settings'])
            ->from(['{{%fields}}'])
            ->where(['type' => Field::class])
            ->all();

        foreach ($fields as $field) {
            $settings = Json::decodeIfJson($field['settings']);

            if (!is_array($settings)) {
                echo 'Field ' . $field['id'] . ' (' . $field['type'] . ') settings were invalid JSON: ' . $field['settings'] . "\n";
                $settings = [];
            }

            $settings['propagationMethod'] = $field['translationMethod'] === 'site' ? 'none' : 'all';

            $this->update(
                '{{%fields}}',
                ['translationMethod' => 'none', 'settings' => Json::encode($settings)],
                ['id' => $field['id']],
                [],
                false
            );
        }
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
