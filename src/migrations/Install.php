<?php

namespace benf\neo\migrations;

use Craft;
use craft\db\Migration;

use benf\neo\Field;
use benf\neo\Plugin as Neo;

/**
 * Class Install
 *
 * @package benf\neo\migrations
 * @author Spicy Web <craft@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.0.0
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $hasBlocksTable = $this->db->tableExists('{{%neoblocks}}');
        $hasBlockStructuresTable = $this->db->tableExists('{{%neoblockstructures}}');
        $hasBlockTypesTable = $this->db->tableExists('{{%neoblocktypes}}');
        $hasBlockTypeGroupsTable = $this->db->tableExists('{{%neoblocktypegroups}}');
        
        // Create tables
        
        if (!$hasBlocksTable) {
            $this->createTable('{{%neoblocks}}', [
                'id' => $this->integer()->notNull(),
                'ownerId' => $this->integer()->notNull(),
                'ownerSiteId' => $this->integer(),
                'fieldId' => $this->integer()->notNull(),
                'typeId' => $this->integer()->notNull(),
                'sortOrder' => $this->smallInteger()->unsigned(),
                // 'level' => $this->smallInteger()->unsigned(),
                'deletedWithOwner' => $this->boolean()->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
                'PRIMARY KEY([[id]])',
            ]);
        }
        
        if (!$hasBlockStructuresTable) {
            $this->createTable('{{%neoblockstructures}}', [
                'id' => $this->primaryKey(),
                'structureId' => $this->integer()->notNull(),
                'ownerId' => $this->integer()->notNull(),
                'ownerSiteId' => $this->integer(),
                'fieldId' => $this->integer()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }
        
        if (!$hasBlockTypesTable) {
            $this->createTable('{{%neoblocktypes}}', [
                'id' => $this->primaryKey(),
                'fieldId' => $this->integer()->notNull(),
                'fieldLayoutId' => $this->integer(),
                'name' => $this->string()->notNull(),
                'handle' => $this->string()->notNull(),
                'maxBlocks' => $this->smallInteger()->unsigned(),
                'maxSiblingBlocks' => $this->smallInteger()->unsigned()->defaultValue(0),
                'maxChildBlocks' => $this->smallInteger()->unsigned(),
                'childBlocks' => $this->text(),
                'topLevel' => $this->boolean()->defaultValue(true)->notNull(),
                'sortOrder' => $this->smallInteger()->unsigned(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }
        
        if (!$hasBlockTypeGroupsTable) {
            $this->createTable('{{%neoblocktypegroups}}', [
                'id' => $this->primaryKey(),
                'fieldId' => $this->integer()->notNull(),
                'name' => $this->string()->notNull(),
                'sortOrder' => $this->smallInteger()->unsigned(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }
        
        // Create indexes
        
        if (!$hasBlocksTable) {
            $this->createIndex(null, '{{%neoblocks}}', ['ownerId'], false);
            $this->createIndex(null, '{{%neoblocks}}', ['ownerSiteId'], false);
            $this->createIndex(null, '{{%neoblocks}}', ['fieldId'], false);
            $this->createIndex(null, '{{%neoblocks}}', ['typeId'], false);
        }
        
        if (!$hasBlockStructuresTable) {
            $this->createIndex(null, '{{%neoblockstructures}}', ['structureId'], false);
            $this->createIndex(null, '{{%neoblockstructures}}', ['ownerId'], false);
            $this->createIndex(null, '{{%neoblockstructures}}', ['ownerSiteId'], false);
            $this->createIndex(null, '{{%neoblockstructures}}', ['fieldId'], false);
        }
        
        if (!$hasBlockTypesTable) {
            $this->createIndex(null, '{{%neoblocktypes}}', ['name', 'fieldId'], false);
            $this->createIndex(null, '{{%neoblocktypes}}', ['handle', 'fieldId'], true);
            $this->createIndex(null, '{{%neoblocktypes}}', ['fieldId'], false);
            $this->createIndex(null, '{{%neoblocktypes}}', ['fieldLayoutId'], false);
        }
        
        if (!$hasBlockTypeGroupsTable) {
            $this->createIndex(null, '{{%neoblocktypegroups}}', ['name', 'fieldId'], false);
            $this->createIndex(null, '{{%neoblocktypegroups}}', ['fieldId'], false);
        }
        
        // Add foreign keys
        
        if (!$hasBlocksTable) {
            $this->addForeignKey(null, '{{%neoblocks}}', ['fieldId'], '{{%fields}}', ['id'], 'CASCADE', null);
            $this->addForeignKey(null, '{{%neoblocks}}', ['id'], '{{%elements}}', ['id'], 'CASCADE', null);
            $this->addForeignKey(null, '{{%neoblocks}}', ['ownerId'], '{{%elements}}', ['id'], 'CASCADE', null);
            $this->addForeignKey(null, '{{%neoblocks}}', ['ownerSiteId'], '{{%sites}}', ['id'], 'CASCADE', 'CASCADE');
            $this->addForeignKey(null, '{{%neoblocks}}', ['typeId'], '{{%neoblocktypes}}', ['id'], 'CASCADE', null);
        }
        
        if (!$hasBlockStructuresTable) {
            $this->addForeignKey(null, '{{%neoblockstructures}}', ['structureId'], '{{%structures}}', ['id'], 'CASCADE',
                null);
            $this->addForeignKey(null, '{{%neoblockstructures}}', ['fieldId'], '{{%fields}}', ['id'], 'CASCADE', null);
            $this->addForeignKey(null, '{{%neoblockstructures}}', ['ownerId'], '{{%elements}}', ['id'], 'CASCADE',
                null);
            $this->addForeignKey(null, '{{%neoblockstructures}}', ['ownerSiteId'], '{{%sites}}', ['id'], 'CASCADE',
                'CASCADE');
        }
        
        if (!$hasBlockTypesTable) {
            $this->addForeignKey(null, '{{%neoblocktypes}}', ['fieldId'], '{{%fields}}', ['id'], 'CASCADE', null);
            $this->addForeignKey(null, '{{%neoblocktypes}}', ['fieldLayoutId'], '{{%fieldlayouts}}', ['id'], 'SET NULL',
                null);
        }
        
        if (!$hasBlockTypeGroupsTable) {
            $this->addForeignKey(null, '{{%neoblocktypegroups}}', ['fieldId'], '{{%fields}}', ['id'], 'CASCADE', null);
        }
        
        return true;
    }
    
    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        // Convert Neo fields to Matrix fields
        $fields = Craft::$app->getFields()->getAllFields();
        
        foreach ($fields as $field) {
            if ($field instanceof Field) {
                Neo::$plugin->conversion->convertFieldToMatrix($field);
            }
        }
        
        // Drop Neo tables
        $this->dropTableIfExists('{{%neoblocks}}');
        $this->dropTableIfExists('{{%neoblockstructures}}');
        $this->dropTableIfExists('{{%neoblocktypes}}');
        $this->dropTableIfExists('{{%neoblocktypegroups}}');
        
        return true;
    }
}
