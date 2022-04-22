<?php

namespace benf\neo\migrations;

use benf\neo\elements\Block;
use benf\neo\Plugin as Neo;
use Craft;
use craft\helpers\StringHelper;
use craft\db\Migration;
use craft\models\FieldLayout;

/**
 * m220421_105948_resave_shared_field_layouts migration.
 */
class m220421_105948_resave_shared_field_layouts extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // The bug this is fixing only occurred on Neo 3 betas 1 and 2, which had schema version 3.0.0
        if (version_compare(Craft::$app->getProjectConfig()->get('plugins.neo.schemaVersion', true), '3.0.0', '==')) {
            $fieldsService = Craft::$app->getFields();
            $setFieldLayoutIds = [];
            $blockTypes = array_filter(Neo::$plugin->blockTypes->getAllBlockTypes(), fn($bt) => $bt->fieldLayoutId !== null);

            foreach ($blockTypes as $blockType) {
                if (isset($setFieldLayoutIds[$blockType->fieldLayoutId])) {
                    $layoutConfig = $fieldsService->getLayoutById($blockType->fieldLayoutId)->getConfig();

                    // Reset the tab UIDs
                    foreach ($layoutConfig['tabs'] as &$tab) {
                        $tab['uid'] = StringHelper::UUID();
                    }

                    // Create the new layout from the modified old one
                    $newLayout = FieldLayout::createFromConfig($layoutConfig);
                    $newLayout->type = Block::class;
                    $newLayout->uid = StringHelper::UUID();
                    $fieldsService->saveLayout($newLayout);

                    // Update the block type with the new layout ID
                    $this->update('{{%neoblocktypes}}', ['fieldLayoutId' => $newLayout->id], ['id' => $blockType->id]);
                } else {
                    $setFieldLayoutIds[$blockType->fieldLayoutId] = true;
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
        echo "m220421_105948_resave_shared_field_layouts cannot be reverted.\n";
        return false;
    }
}
