<?php

namespace benf\neo\migrations;

use Craft;
use craft\db\Migration;
use craft\elements\Asset;

/**
 * m230202_000653_convert_project_config_icon_data migration.
 */
class m230202_000653_convert_project_config_icon_data extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $elementsService = Craft::$app->getElements();
        $projectConfig = Craft::$app->getProjectConfig();

        if (version_compare($projectConfig->get('plugins.neo.schemaVersion', true), '3.6.0', '==')) {
            $blockTypes = $projectConfig->get('neoBlockTypes');

            foreach ($blockTypes ?? [] as $uid => $data) {
                if (isset($data['icon']) && is_string($data['icon'])) {
                    $icon = $elementsService->getElementByUid($data['icon'], Asset::class);

                    if ($icon) {
                        $iconData = [
                            'volume' => $icon->getVolume()->uid,
                            'folderPath' => $icon->getFolder()->path,
                            'filename' => $icon->getFilename(),
                        ];
                    } else {
                        $iconData = null;
                    }

                    $projectConfig->set("neoBlockTypes.$uid.icon", $iconData);
                }
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230202_000653_convert_project_config_icon_data cannot be reverted.\n";
        return false;
    }
}
