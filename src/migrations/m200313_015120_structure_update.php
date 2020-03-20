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
        // level not needed
        $newColumns = ['sortOrder'];
        
        foreach ($newColumns as $col) {
            $exists = $this->db->columnExists('{{%neoblocks}}', $col);
            
            if (!$exists) {
                $this->addColumn('{{%neoblocks}}', $col, $this->integer()->after('dateUpdated'));
            }
        }
        
        // set the order for the new columns
        
        $query = (new Query())
            ->select(['elements.id', 'elements.type', 'structureelements.id as sId', 'structureelements.lft'])
            ->from('{{%elements}} elements')
            ->leftJoin('{{%structureelements}} structureelements', '[[elements.id]] = [[structureelements.id]]')
            ->where(['elements.type' => Block::class])
            ->andWhere('structureelements.lft IS NOT NULL')
            ->limit(null);
        
        $elements = $query->all($this->db);
        
        foreach ($elements as $el) {
            $this->update('{{%neoblocks}}', ['sortOrder' => (int)$el['lft']],
                ['id' => (int)$el['sId']]);
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
