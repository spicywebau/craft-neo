<?php

namespace benf\neo\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\MigrationHelper;

/**
 * m240722_092833_revert_index_change migration.
 */
class m240722_092833_revert_index_change extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (version_compare(Craft::$app->getProjectConfig()->get('plugins.neo.schemaVersion', true), '5.1.0', '=')) {
            MigrationHelper::dropIndex('{{%neoblocktypes}}', ['handle', 'fieldId', 'entryTypeId'], true, $this);
            $this->createIndex(null, '{{%neoblocktypes}}', ['handle', 'fieldId'], true);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240722_092833_revert_index_change cannot be reverted.\n";
        return false;
    }
}
