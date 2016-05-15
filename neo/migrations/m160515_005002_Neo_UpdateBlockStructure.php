<?php
namespace Craft;

class m160515_005002_Neo_UpdateBlockStructure extends BaseMigration
{
	public function safeUp()
	{
		// Create the craft_neoblockstructures table
		$this->createTable('neoblockstructures', [
			'structureId' => ['column' => 'integer', 'required' => true],
			'ownerId'     => ['column' => 'integer', 'required' => true],
			'fieldId'     => ['column' => 'integer', 'required' => true],
		], null, true);

		// Add indexes to craft_neoblockstructures
		$this->createIndex('neoblockstructures', 'structureId', false);
		$this->createIndex('neoblockstructures', 'ownerId', false);
		$this->createIndex('neoblockstructures', 'fieldId', false);

		// Add foreign keys to craft_neoblockstructures
		$this->addForeignKey('neoblockstructures', 'structureId', 'structures', 'id', 'CASCADE', null);
		$this->addForeignKey('neoblockstructures', 'ownerId', 'elements', 'id', 'CASCADE', null);
		$this->addForeignKey('neoblockstructures', 'fieldId', 'fields', 'id', 'CASCADE', null);

		// Migrate existing fields
		//$this->select('neoblocks');

		$this->dropColumn('neoblocks', 'sortOrder');
		$this->dropColumn('neoblocks', 'level');

		return true;
	}
}
