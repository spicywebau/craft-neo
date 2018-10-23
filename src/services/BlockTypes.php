<?php
namespace benf\neo\services;

use yii\base\Component;

use Craft;
use craft\db\Query;

use benf\neo\Plugin as Neo;
use benf\neo\elements\Block;
use benf\neo\models\BlockType;
use benf\neo\models\BlockTypeGroup;
use benf\neo\records\BlockType as BlockTypeRecord;
use benf\neo\records\BlockTypeGroup as BlockTypeGroupRecord;
use benf\neo\errors\BlockTypeNotFoundException;
use benf\neo\helpers\Memoize;

/**
 * Class BlockTypes
 *
 * @package benf\neo
 * @author Spicy Web <craft@spicyweb.com.au>
 * @since 2.0.0
 */
class BlockTypes extends Component
{
	/**
	 * Gets a Neo block type given its ID.
	 *
	 * @param $id The block type ID to check.
	 * @return BlockType|null
	 */
	public function getById($id)
	{
		$blockType = null;

		if (isset(Memoize::$blockTypesById[$id]))
		{
			$blockType = Memoize::$blockTypesById[$id];
		}
		else
		{
			$result = $this->_createQuery()
				->where(['id' => $id])
				->one();

			if ($result)
			{
				$blockType = new BlockType($result);
				Memoize::$blockTypesById[$id] = $blockType;
			}
		}

		return $blockType;
	}

	/**
	 * Gets block types associated with a given field ID.
	 *
	 * @param $fieldId The field ID to check for block types.
	 * @return array The block types.
	 */
	public function getByFieldId($fieldId): array
	{
		$blockTypes = [];

		if (isset(Memoize::$blockTypesByFieldId[$fieldId]))
		{
			$blockTypes = Memoize::$blockTypesByFieldId[$fieldId];
		}
		else
		{
			$results = $this->_createQuery()
				->where(['fieldId' => $fieldId])
				->all();

			foreach ($results as $result)
			{
				$blockType = new BlockType($result);
				$blockTypes[] = $blockType;
				Memoize::$blockTypesById[$blockType->id] = $blockType;
			}

			Memoize::$blockTypesByFieldId[$fieldId] = $blockTypes;
		}

		return $blockTypes;
	}

	/**
	 * Gets block type groups associated with a given field ID.
	 *
	 * @param $fieldId The field ID to check for block type groups.
	 * @return array The block type groups.
	 */
	public function getGroupsByFieldId($fieldId): array
	{
		$blockTypeGroups = [];

		if (isset(Memoize::$blockTypeGroupsByFieldId[$fieldId]))
		{
			$blockTypeGroups = Memoize::$blockTypeGroupsByFieldId[$fieldId];
		}
		else
		{
			$results = $this->_createGroupQuery()
				->where(['fieldId' => $fieldId])
				->all();

			foreach ($results as $result)
			{
				$blockTypeGroup = new BlockTypeGroup($result);
				$blockTypeGroups[] = $blockTypeGroup;
				Memoize::$blockTypeGroupsById[$blockTypeGroup->id] = $blockTypeGroup;
			}

			Memoize::$blockTypeGroupsByFieldId[$fieldId] = $blockTypeGroups;
		}

		return $blockTypeGroups;
	}

	/**
	 * Performs validation on a given Neo block type.
	 *
	 * @param BlockType $blockType The block type to perform validation on.
	 * @param bool $validateUniques Whether to ensure that the block type's handle is unique.
	 * @return bool Whether validation was successful.
	 */
	public function validate(BlockType $blockType, bool $validateUniques = true): bool
	{
		$record = $this->_getRecord($blockType);

		$record->fieldId = $blockType->fieldId;
		$record->fieldLayoutId = $blockType->fieldLayoutId;
		$record->name = $blockType->name;
		$record->handle = $blockType->handle;
		$record->sortOrder = $blockType->sortOrder;
		$record->maxBlocks = $blockType->maxBlocks;
		$record->maxChildBlocks = $blockType->maxChildBlocks;
		$record->childBlocks = $blockType->childBlocks;
		$record->topLevel = $blockType->topLevel;

		$record->validateUniques = $validateUniques;
		$isValid = (bool)$record->validate();

		if (!$isValid)
		{
			$blockType->addErrors($record->getErrors());
		}

		return $isValid;
	}

	/**
	 * Saves a Neo block type.
	 *
	 * @param BlockType $blockType The block type to save.
	 * @param bool $validate Whether to perform validation on the block type.
	 * @return bool Whether saving the block type was successful.
	 * @throws \Throwable
	 */
	public function save(BlockType $blockType, bool $validate = true): bool
	{
		$dbService = Craft::$app->getDb();
		$fieldsService = Craft::$app->getFields();

		$isValid = $validate || $this->validate($blockType);

		if ($isValid)
		{
			$transaction = $dbService->beginTransaction();
			try
			{
				$record = $this->_getRecord($blockType);
				$isNew = $blockType->getIsNew();

				$fieldLayout = $blockType->getFieldLayout();

				if (!$fieldLayout->id)
				{
					if (!$isNew)
					{
						$result = $this->_createQuery()
							->where(['id' => $blockType->id])
							->one();

						$oldBlockType = new BlockType($result);
					
						if ($oldBlockType->fieldLayoutId)
						{
							$fieldsService->deleteLayoutById($oldBlockType->fieldLayoutId);
						}
					}

					$fieldsService->saveLayout($fieldLayout);

					$blockType->fieldLayoutId = $fieldLayout->id;
					$record->fieldLayoutId = $fieldLayout->id;
				}

				$record->fieldId = $blockType->fieldId;
				$record->name = $blockType->name;
				$record->handle = $blockType->handle;
				$record->sortOrder = $blockType->sortOrder;
				$record->maxBlocks = $blockType->maxBlocks;
				$record->maxChildBlocks = $blockType->maxChildBlocks;
				$record->childBlocks = $blockType->childBlocks;
				$record->topLevel = $blockType->topLevel;

				$record->save(false);

				if ($isNew)
				{
					$blockType->id = $record->id;
				}

				$transaction->commit();
			}
			catch (\Throwable $e)
			{
				$transaction->rollBack();

				throw $e;
			}
		}

		return $isValid;
	}

	/**
	 * Saves a Neo block type group.
	 *
	 * @param BlockTypeGroup $blockTypeGroup The block type group to save.
	 * @return bool Whether saving the block type group was successful.
	 * @throws \Throwable
	 */
	public function saveGroup(BlockTypeGroup $blockTypeGroup): bool
	{
		$dbService = Craft::$app->getDb();

		$transaction = $dbService->beginTransaction();
		try
		{
			$record = new BlockTypeGroupRecord();
			$record->fieldId = $blockTypeGroup->fieldId;
			$record->name = $blockTypeGroup->name;
			$record->sortOrder = $blockTypeGroup->sortOrder;

			$record->save(false);

			$blockTypeGroup->id = $record->id;

			$transaction->commit();
		}
		catch(\Throwable $e)
		{
			$transaction->rollBack();

			throw $e;
		}

		return true;
	}

	/**
	 * Deletes a Neo block type and all associated Neo blocks.
	 *
	 * @param BlockType $blockType The block type to delete.
	 * @return bool Whether deleting the block type was successful.
	 * @throws \Throwable
	 */
	public function delete(BlockType $blockType): bool
	{
		$dbService = Craft::$app->getDb();
		$sitesService = Craft::$app->getSites();
		$elementsService = Craft::$app->getElements();
		$fieldsService = Craft::$app->getFields();

		$success = false;

		$transaction = $dbService->beginTransaction();
		try
		{
			// Delete all blocks of this type
			foreach ($sitesService->getAllSiteIds() as $siteId)
			{
				$blocks = Block::find()
					->siteId($siteId)
					->typeId($blockType->id)
					->all();

				foreach ($blocks as $block)
				{
					$elementsService->deleteElement($block);
				}
			}

			// Delete the block types field layout
			$fieldsService->deleteLayoutById($blockType->fieldLayoutId);

			// Delete the block type
			$affectedRows = $dbService->createCommand()
				->delete('{{%neoblocktypes}}', ['id' => $blockType->id])
				->execute();

			$transaction->commit();

			$success = (bool)$affectedRows;
		}
		catch (\Throwable $e)
		{
			$transaction->rollBack();

			throw $e;
		}

		return $success;
	}

	/**
	 * Deletes Neo block type groups associated with a given field ID.
	 *
	 * @param int $fieldId The field ID having its associated block type groups deleted.
	 * @return bool Whether deleting the block type groups was successful.
	 * @throws \Throwable
	 */
	public function deleteGroupsByFieldId($fieldId): bool
	{
		$dbService = Craft::$app->getDb();

		$success = false;

		$transaction = $dbService->beginTransaction();
		try
		{
			$affectedRows = $dbService->createCommand()
				->delete('{{%neoblocktypegroups}}', ['fieldId' => $fieldId])
				->execute();

			$transaction->commit();

			$success = (bool)$affectedRows;
		}
		catch (\Throwable $e)
		{
			$transaction->rollBack();

			throw $e;
		}

		return $success;
	}

	/**
	 * Renders a Neo block type's tabs.
	 *
	 * @param Block $block The Neo block type having its tabs rendered.
	 * @param bool $static Whether to generate static tab content.
	 * @param string|null $namespace
	 * @return array The tabs data.
	 */
	public function renderTabs(BlockType $blockType, bool $static = false, $namespace = null): array
	{
		$block = new Block();
		$block->typeId = $blockType->id;

		return Neo::$plugin->blocks->renderTabs($block, $static, $namespace);
	}

	/**
	 * Creates a basic Neo block type query.
	 *
	 * @return Query
	 */
	private function _createQuery(): Query
	{
		return (new Query())
			->select([
				'id',
				'fieldId',
				'fieldLayoutId',
				'name',
				'handle',
				'maxBlocks',
				'maxChildBlocks',
				'childBlocks',
				'topLevel',
				'sortOrder',
			])
			->from(['{{%neoblocktypes}}'])
			->orderBy(['sortOrder' => SORT_ASC]);
	}

	/**
	 * Creates a basic Neo block type group query.
	 *
	 * @return Query
	 */
	private function _createGroupQuery(): Query
	{
		return (new Query())
			->select([
				'id',
				'fieldId',
				'name',
				'sortOrder',
			])
			->from(['{{%neoblocktypegroups}}'])
			->orderBy(['sortOrder' => SORT_ASC]);
	}

	/**
	 * Gets the block type record associated with the given block type.
	 *
	 * @param BlockType The Neo block type.
	 * @return BlockTypeRecord The block type record associated with the given block type.
	 * @throws BlockTypeNotFoundException if the given block type has an invalid ID.
	 */
	private function _getRecord(BlockType $blockType): BlockTypeRecord
	{
		$record = null;

		if ($blockType->getIsNew())
		{
			$record = new BlockTypeRecord();
		}
		else
		{
			$id = $blockType->id;

			if (isset(Memoize::$blockTypeRecordsById[$id]))
			{
				$record = Memoize::$blockTypeRecordsById[$id];
			}
			else
			{
				$record = BlockTypeRecord::findOne($id);

				if (!$record)
				{
					throw new BlockTypeNotFoundException("Invalid Neo block type ID: $id");
				}

				Memoize::$blockTypeRecordsById[$id] = $record;
			}
		}

		return $record;
	}
}
