<?php

namespace benf\neo\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;

/**
 * m200313_015120_structure_update migration.
 *
 * @package benf\neo\migrations
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 2.7.0
 */
class m200313_015120_structure_update extends Migration
{
    /**
     * @inheritdoc
     * @throws \yii\base\NotSupportedException
     */
    public function safeUp()
    {
        // Set up the new `sortOrder` column
        if (!$this->db->columnExists('{{%neoblocks}}', 'sortOrder')) {
            $this->addColumn('{{%neoblocks}}', 'sortOrder', $this->integer()->after('dateUpdated'));
        }

        // Set all blocks' `sortOrder`, based on their current position in the block structure they belong to
        $blocks = (new Query())
            ->select(['neoblocks.id', 'structureelements.elementId', 'structureelements.lft'])
            ->from('{{%neoblocks}} neoblocks')
            ->leftJoin('{{%structureelements}} structureelements', '[[neoblocks.id]] = [[structureelements.elementId]]')
            ->where('structureelements.lft IS NOT NULL')
            ->limit(null)
            ->all($this->db);

        foreach ($blocks as $block) {
            $this->update('{{%neoblocks}}', ['sortOrder' => (int)$block['lft']], ['id' => (int)$block['id']]);
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
