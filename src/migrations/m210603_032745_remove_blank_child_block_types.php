<?php

namespace benf\neo\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\Json;

/**
 * Removes empty strings from child block type arrays, which were caused by a Neo field configurator bug where the
 * handle of a new block type would not be updated in the child block type checkboxes if the handle was being auto-
 * updated as a result of the block type's name being updated.
 *
 * @package benf\neo\migrations
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 2.9.11
 */
class m210603_032745_remove_blank_child_block_types extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.neo.schemaVersion', true);

        if (!version_compare($schemaVersion, '2.9.11', '<')) {
            return true;
        }

        foreach (($projectConfig->get('neoBlockTypes') ?? []) as $uid => $data) {
            if (isset($data['childBlocks']) && !empty($data['childBlocks'])) {
                $childBlocks = Json::decodeIfJson($data['childBlocks']);

                if (!is_array($childBlocks)) {
                    continue;
                }

                $filteredChildBlocks = array_filter($childBlocks, function($cbHandle) {
                    return !empty($cbHandle);
                });

                if (count($filteredChildBlocks) < count($childBlocks)) {
                    $projectConfig->set("neoBlockTypes.$uid.childBlocks", array_values($filteredChildBlocks));
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210603_032745_remove_blank_child_block_types cannot be reverted.\n";
        return false;
    }
}
