<?php
namespace Craft;

class m160515_005002_Neo_UpdateBlockStructure extends BaseMigration
{
	public function safeUp()
	{
		// Create the craft_neoblockstructures table
		craft()->db->createCommand()->createTable('neoblockstructures', [
			'structureId' => ['column' => 'integer', 'required' => true],
			'ownerId'     => ['column' => 'integer', 'required' => true],
			'fieldId'     => ['column' => 'integer', 'required' => true],
		], null, true);

		// Add indexes to craft_neoblockstructures
		craft()->db->createCommand()->createIndex('neoblockstructures', 'structureId', false);
		craft()->db->createCommand()->createIndex('neoblockstructures', 'ownerId', false);
		craft()->db->createCommand()->createIndex('neoblockstructures', 'fieldId', false);

		// Add foreign keys to craft_neoblockstructures
		craft()->db->createCommand()->addForeignKey('neoblockstructures', 'structureId', 'structures', 'id', 'CASCADE', null);
		craft()->db->createCommand()->addForeignKey('neoblockstructures', 'ownerId', 'elements', 'id', 'CASCADE', null);
		craft()->db->createCommand()->addForeignKey('neoblockstructures', 'fieldId', 'fields', 'id', 'CASCADE', null);

		return true;
	}
}
