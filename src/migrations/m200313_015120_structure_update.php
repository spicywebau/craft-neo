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
            ->select(['neoblocks.id', 'structureelements.elementId', 'structureelements.lft'])
            ->from('{{%neoblocks}} neoblocks')
            ->leftJoin('{{%structureelements}} structureelements', '[[neoblocks.id]] = [[structureelements.elementId]]')
            ->where('structureelements.lft IS NOT NULL')
            ->limit(null);
        
        $raw = $query->getRawSql();
    
        $blocks = $query->all($this->db);
    
        if (count($blocks) > 0) {
            foreach ($blocks as $block) {
                $this->update('{{%neoblocks}}', ['sortOrder' => (int)$block['lft']],
                    ['id' => (int)$block['id']]);
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
