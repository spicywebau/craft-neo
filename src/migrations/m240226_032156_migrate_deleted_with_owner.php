<?php

namespace benf\neo\migrations;

use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;

/**
 * m240226_032156_migrate_deleted_with_owner migration.
 */
class m240226_032156_migrate_deleted_with_owner extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $blockTypeIds = (new Query())
            ->select('id')
            ->from(['neoblocktypes' => '{{%neoblocktypes}}'])
            ->column();

        foreach ([true, false] as $bool) {
            $this->update(
                Table::ELEMENTS,
                ['deletedWithOwner' => $bool],
                ['id' => (new Query())
                    ->select('id')
                    ->from(['neoblocks' => '{{%neoblocks}}'])
                    ->where([
                        'neoblocks.typeId' => $blockTypeIds,
                        'neoblocks.deletedWithOwner' => $bool,
                    ]),
                ],
            );
        }

        $this->dropColumn('{{%neoblocks}}', 'deletedWithOwner');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240226_032156_migrate_deleted_with_owner cannot be reverted.\n";
        return false;
    }
}
