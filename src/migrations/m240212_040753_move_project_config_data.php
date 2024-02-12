<?php

namespace benf\neo\migrations;

use Craft;
use craft\db\Migration;

/**
 * m240212_040753_move_project_config_data migration.
 */
class m240212_040753_move_project_config_data extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $blockTypes = $projectConfig->get('neoBlockTypes');
        $blockTypeGroups = $projectConfig->get('neoBlockTypeGroups');
        $muteEvents = $projectConfig->muteEvents;
        $projectConfig->muteEvents = true;
        $projectConfig->set('neo.blockTypes', $blockTypes);
        $projectConfig->set('neo.blockTypeGroups', $blockTypeGroups);
        $projectConfig->remove('neoBlockTypes');
        $projectConfig->remove('neoBlockTypeGroups');
        $projectConfig->muteEvents = $muteEvents;

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240212_040753_move_project_config_data cannot be reverted.\n";
        return false;
    }
}
