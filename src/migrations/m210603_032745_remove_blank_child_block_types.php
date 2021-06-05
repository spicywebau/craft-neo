<?php

namespace benf\neo\migrations;

use Craft;
use craft\db\Migration;

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
                $childBlocks = array_filter($data['childBlocks'], function($cbHandle) {
                    return !empty($cbHandle);
                });

                if (count($childBlocks) < count($data['childBlocks'])) {
                    $projectConfig->set("neoBlockTypes.$uid.childBlocks", array_values($childBlocks));
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
