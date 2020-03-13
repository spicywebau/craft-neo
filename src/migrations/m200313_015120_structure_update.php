<?php

namespace benf\neo\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;

use benf\neo\Field;
use benf\neo\Plugin as Neo;
use benf\neo\elements\Block;

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
        // setup new columns
        $newColumns = ['sortOrder', 'level'];
        
        foreach ($newColumns as $col) {
            $exists = $this->db->columnExists('{{%neoblocks}}', $col);
    
            if (!$exists)
            {
                $this->addColumn('{{%neoblocks}}', $col, $this->integer()->notNull()->after('dateUpdated'));
            }
        }
        
        // set the level and order for the new columns
        $elements = (new Query())
            ->select(['id'])
            ->from('{{%elements}}')
            ->where(['type' => 'benf\neo\elements\Block'])
            ->limit(null)
            ->all($this->db);
        
        foreach($elements as $el) {
            $structureElement = (new Query())
                ->select(['id', 'lft', 'level'])
                ->from('{{%structureelements}}')
                ->where(['elementId' => $el['id']])
                ->one($this->db);
            
            if ($structureElement) {
                $this->update('{{%neoblocks}}', ['sortOrder' => $structureElement['lft']], ['id' => $structureElement['id']]);
                $this->update('{{%neoblocks}}', ['level' => $structureElement['level']], ['id' => $structureElement['id']]);
            }
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
