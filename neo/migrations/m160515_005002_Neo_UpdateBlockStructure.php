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
		$structures = [];
		$parentStacks = [];
		$rawBlocks = craft()->db->createCommand()
			->select('*')
			->from('neoblocks')
			->order('sortOrder asc')
			->queryAll();

		if($rawBlocks)
		{
			foreach($rawBlocks as $rawBlock)
			{
				$block = new Neo_BlockModel_1_0_2();
				$block->id = intval($rawBlock['id']);
				$block->ownerId = intval($rawBlock['ownerId']);
				$block->fieldId = intval($rawBlock['fieldId']);
				$block->typeId = intval($rawBlock['typeId']);
				$block->level = intval($rawBlock['level']);

				$key = $block->ownerId . ':' . $block->fieldId;

				if(!isset($structures[$key]))
				{
					$structures[$key] = new StructureModel();
					$parentStacks[$key] = [];

					craft()->structures->saveStructure($structures[$key]);

					$this->insert('neoblockstructures', [
						'structureId' => $structures[$key]->id,
						'ownerId' => $block->ownerId,
						'fieldId' => $block->fieldId,
					]);
				}

				$structure = $structures[$key];
				$parentStack = $parentStacks[$key];

				while(!empty($parentStack) && $block->level <= $parentStack[count($parentStack) - 1]->level)
				{
					array_pop($parentStack);
				}

				if(empty($parentStack))
				{
					craft()->structures->appendToRoot($structure->id, $block);
				}
				else
				{
					$parentBlock = $parentStack[count($parentStack) - 1];
					craft()->structures->append($structure->id, $block, $parentBlock);
				}

				array_push($parentStack, $block);

				$structures[$key] = $structure;
				$parentStacks[$key] = $parentStack;
			}
		}

		// Remove columns from craft_neoblocks
		$this->dropColumn('neoblocks', 'sortOrder');
		$this->dropColumn('neoblocks', 'level');

		return true;
	}
}

class Neo_BlockModel_1_0_2 extends BaseElementModel
{
	protected $elementType = 'Neo_Block';

	protected function defineAttributes()
	{
		return array_merge(parent::defineAttributes(), [
			'fieldId' => AttributeType::Number,
			'ownerId' => AttributeType::Number,
			'typeId' => AttributeType::Number,
		]);
	}
}
