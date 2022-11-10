<?php

namespace benf\neo\migrations;

use benf\neo\Plugin;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;

/**
 * m221110_132322_add_ignore_permissions_to_block_types migration.
 */
class m221110_132322_add_ignore_permissions_to_block_types extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(
            '{{%neoblocktypes}}',
            'ignorePermissions',
            $this->boolean()->defaultValue(true)->notNull()->after('topLevel')
        );

        foreach (Plugin::$plugin->blockTypes->getAllBlockTypes() as $blockType) {
            $editPermissionId = (new Query())
                ->select(['id'])
                ->from(Table::USERPERMISSIONS)
                ->where(['name' => "neo-editblocks:{$blockType->uid}"])
                ->scalar();
            if ($editPermissionId) {
                $anySetYet = (new Query())
                    ->from(Table::USERPERMISSIONS_USERS)
                    ->where(['permissionId' => $editPermissionId])
                    ->count() > 0 ||
                    (new Query())
                        ->from(Table::USERPERMISSIONS_USERGROUPS)
                        ->where(['permissionId' => $editPermissionId])
                        ->count() > 0;

                if ($anySetYet) {
                    $this->update('{{%neoblocktypes}}', ['ignorePermissions' => false], ['id' => $blockType->id]);
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
        echo "m221110_132322_add_ignore_permissions_to_block_types cannot be reverted.\n";
        return false;
    }
}
