<?php

namespace benf\neo\migrations;

use Craft;
use craft\db\Migration;

use benf\neo\Field;
use benf\neo\Plugin as Neo;

/**
 * m200313_015120_structure_update migration.
 */
class m200313_015120_structure_update extends Migration
{
    /**
     * @inheritdoc
     * @throws \yii\base\NotSupportedException
     */
    public function safeUp()
    {
        // Place migration code here...
        $blockHasSortOrder = $this->db->columnExists('{{%neoblocks}}', 'sortOrder');
        $blockHasLevel = $this->db->columnExists('{{%neoblocks}}', 'level');
    
        // Create tables
    
        if (!$blockHasSortOrder)
        {
            $this->addColumn('{{%neoblocks}}', 'sortOrder', $this->integer()->notNull());
        }
    
        if (!$blockHasLevel)
        {
            $this->addColumn('{{%neoblocks}}', 'level', $this->integer()->notNull());
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200313_015120_structure_update cannot be reverted.\n";
        return false;
    }
}
