<?php

namespace benf\neo\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\fields\Matrix;

/**
 * m220511_054742_delete_converted_field_block_types_and_groups migration.
 */
class m220511_054742_delete_converted_field_block_types_and_groups extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $allMatrixFields = (new Query())
            ->select(['fields.id'])
            ->from(['fields' => Table::FIELDS])
            ->where(['fields.type' => Matrix::class])
            ->column();
        $blockTypeUids = (new Query())
            ->select(['nbt.uid'])
            ->from(['nbt' => '{{%neoblocktypes}}'])
            ->where(['nbt.fieldId' => $allMatrixFields])
            ->column();
        $blockTypeGroupUids = (new Query())
            ->select(['nbtg.uid'])
            ->from(['nbtg' => '{{%neoblocktypegroups}}'])
            ->where(['nbtg.fieldId' => $allMatrixFields])
            ->column();

        foreach ($blockTypeUids as $blockTypeUid) {
            if ($projectConfig->get("neoBlockTypes.$blockTypeUid", true) !== null) {
                $projectConfig->remove("neoBlockTypes.$blockTypeUid");
            }
        }

        foreach ($blockTypeGroupUids as $blockTypeGroupUid) {
            if ($projectConfig->get("neoBlockTypeGroups.$blockTypeGroupUid", true) !== null) {
                $projectConfig->remove("neoBlockTypeGroups.$blockTypeGroupUid");
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220511_054742_delete_converted_field_block_types_and_groups cannot be reverted.\n";
        return false;
    }
}
