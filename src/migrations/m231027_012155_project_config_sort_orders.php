<?php

namespace benf\neo\migrations;

use benf\neo\models\BlockType;
use benf\neo\Plugin as Neo;
use Craft;
use craft\db\Migration;

/**
 * m231027_012155_project_config_sort_orders migration.
 */
class m231027_012155_project_config_sort_orders extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();

        if (version_compare($projectConfig->get('plugins.neo.schemaVersion', true), '3.10.0.1', '<')) {
            foreach (Neo::$plugin->fields->getNeoFields() as $neoField) {
                $itemOrder = [];

                foreach ($neoField->getItems() as $item) {
                    if ($item instanceof BlockType) {
                        $itemOrder[$item->sortOrder - 1] = "blockType:$item->uid";
                        $projectConfig->remove("neoBlockTypes.$item->uid.sortOrder");
                    } else {
                        $itemOrder[$item->sortOrder - 1] = "blockTypeGroup:$item->uid";
                        $projectConfig->remove("neoBlockTypeGroups.$item->uid.sortOrder");
                    }
                }

                $projectConfig->set("neo.orders.$neoField->uid", $itemOrder);
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m231027_012155_project_config_sort_orders cannot be reverted.\n";
        return false;
    }
}
