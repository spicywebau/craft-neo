<?php
namespace benf\neo\services;

use yii\base\Component;

use Craft;
use craft\db\Query;
use craft\events\ConfigEvent;
use craft\helpers\Db;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;

use benf\neo\Plugin as Neo;
use benf\neo\elements\Block;
use benf\neo\events\BlockTypeEvent;
use benf\neo\models\BlockType;
use benf\neo\models\BlockTypeGroup;
use benf\neo\records\BlockType as BlockTypeRecord;
use benf\neo\records\BlockTypeGroup as BlockTypeGroupRecord;
use benf\neo\errors\BlockTypeNotFoundException;
use benf\neo\helpers\Memoize;

/**
 * Class BlockTypes
 *
 * @package benf\neo\services
 * @author Spicy Web <craft@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.0.0
 */
class BlockTypes extends Component
{
	/**
	 * @event BlockTypeEvent The event that is triggered before saving a block type.
	 * @since 2.3.0
	 */
	const EVENT_BEFORE_SAVE_BLOCK_TYPE = 'beforeSaveNeoBlockType';

	/**
	 * @event BlockTypeEvent The event that is triggered after saving a block type.
	 * @since 2.3.0
	 */
	const EVENT_AFTER_SAVE_BLOCK_TYPE = 'afterSaveNeoBlockType';

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
		// Ensure that the block type passes validation or that validation is disabled
		if ($validate && !$this->validate($blockType))
		{
			return false;
		}

		$projectConfigService = Craft::$app->getProjectConfig();
		$fieldsService = Craft::$app->getFields();
		$field = $fieldsService->getFieldById($blockType->fieldId);
		$fieldLayout = $blockType->getFieldLayout();
		$fieldLayoutConfig = $fieldLayout->getConfig();
		$isNew = $blockType->getIsNew();

		if ($isNew)
		{
			$blockType->uid = StringHelper::UUID();
		}

		if ($blockType->uid === null)
		{
			$blockType->uid = Db::uidById('{{%neoblocktypes}}', $blockType->id);
		}

		$data = [
			'field' => $field->uid,
			'name' => $blockType->name,
			'handle' => $blockType->handle,
			'sortOrder' => (int)$blockType->sortOrder,
			'maxBlocks' => (int)$blockType->maxBlocks,
			'maxChildBlocks' => (int)$blockType->maxChildBlocks,
			'childBlocks' => $blockType->childBlocks,
			'topLevel' => (bool)$blockType->topLevel,
		];

		// No need to bother with the field layout if it has no tabs
		if ($fieldLayoutConfig !== null)
		{
			$fieldLayoutUid = $fieldLayout->uid ?? ($fieldLayout->id ? Craft::$app->getFields()->getLayoutById($fieldLayout->id)->uid : false) ?? StringHelper::UUID();

			if (!$fieldLayout->uid)
			{
				$fieldLayout->uid = $fieldLayoutUid;
			}

			$data['fieldLayouts'] = [
				$fieldLayoutUid => $fieldLayoutConfig,
			];
		}

		$event = new BlockTypeEvent([
			'blockType' => $blockType,
			'isNew' => $isNew,
		]);

		$this->trigger(self::EVENT_BEFORE_SAVE_BLOCK_TYPE, $event);

		$path = 'neoBlockTypes.' . $blockType->uid;
		$projectConfigService->set($path, $data);

		return true;
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
		$projectConfigService = Craft::$app->getProjectConfig();
		$fieldsService = Craft::$app->getFields();
		$field = $fieldsService->getFieldById($blockTypeGroup->fieldId);

		if ($blockTypeGroup->getIsNew())
		{
			$blockTypeGroup->uid = StringHelper::UUID();
		}

		$data = [
			'field' => $field->uid,
			'name' => $blockTypeGroup->name,
			'sortOrder' => $blockTypeGroup->sortOrder,
		];

		$path = 'neoBlockTypeGroups.' . $blockTypeGroup->uid;
		$projectConfigService->set($path, $data);

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
		Craft::$app->getProjectConfig()->remove('neoBlockTypes.' . $blockType->uid);

		return true;
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
		$projectConfigService = Craft::$app->getProjectConfig();
		$fieldsService = Craft::$app->getFields();
		$field = $fieldsService->getFieldById($fieldId);

		foreach ($field->getGroups() as $group)
		{
			$projectConfigService->remove('neoBlockTypeGroups.' . $group->uid);
		}

		return true;
	}

	/**
	 * Handles a Neo block type change.
	 *
	 * @param ConfigEvent $event
	 * @throws \Throwable
	 */
	public function handleChangedBlockType(ConfigEvent $event)
	{
		$dbService = Craft::$app->getDb();
		$fieldsService = Craft::$app->getFields();
		$projectConfigService = Craft::$app->getProjectConfig();
		$uid = $event->tokenMatches[0];
		$data = $event->newValue;

		// Make sure the fields have been synced
		ProjectConfigHelper::ensureAllFieldsProcessed();

		$fieldId = Db::idByUid('{{%fields}}', $data['field']);

		$transaction = $dbService->beginTransaction();

		try
		{
			$record = $this->_getRecordByUid($uid);
			$fieldLayoutConfig = isset($data['fieldLayouts']) ? reset($data['fieldLayouts']) : null;
			$fieldLayout = null;
			$isNew = false;
			$blockType = null;

			if ($record->id !== null)
			{
				$result = $this->_createQuery()
					->where(['id' => $record->id])
					->one();

				$blockType = new BlockType($result);
			} else {
				$blockType = new BlockType();
				$isNew = true;
			}

			if ($fieldLayoutConfig === null || !isset($fieldLayoutConfig['id']))
			{
				if ($record->id !== null)
				{	
					if ($blockType->fieldLayoutId)
					{
						$fieldsService->deleteLayoutById($blockType->fieldLayoutId);
					}
				}
			}

			if ($fieldLayoutConfig !== null)
			{
				$fieldLayout = FieldLayout::createFromConfig($fieldLayoutConfig);

				// Ensure that blank tabs are retained, since createFromConfig() strips them out
				$oldTabs = $fieldLayout->getTabs();
				$configTabs = $fieldLayoutConfig['tabs'];

				if (count($configTabs) > count($oldTabs))
				{
					$newTabs = [];
					$tabCount = 0;

					foreach ($oldTabs as $oldTab)
					{
						// Was a blank tab supposed to be here?
						while ($oldTab->sortOrder != $tabCount + 1)
						{
							$newTabData = $configTabs[$tabCount++];
							$newTabs[] = new FieldLayoutTab($newTabData);
						}

						$newTabs[] = $oldTab;
						$tabCount++;
					}

					// Check for any blank tab(s) at the end of the layout
					$endBlankTabData = array_slice($configTabs, count($newTabs));

					foreach ($endBlankTabData as $tabData)
					{
						$newTabs[] = new FieldLayoutTab($tabData);
					}

					// Re-set the field layout tabs if any blank tabs were added
					if (count($oldTabs) !== count($newTabs))
					{
						$fieldLayout->setTabs($newTabs);
					}
				}

				// Now we can save the field layout
				$fieldLayout->id = $record->fieldLayoutId;
				$fieldLayout->type = Block::class;
				$fieldLayout->uid = key($data['fieldLayouts']);

				$fieldsService->saveLayout($fieldLayout);
			}

			$record->fieldId = $fieldId;
			$record->name = $data['name'];
			$record->handle = $data['handle'];
			$record->sortOrder = $data['sortOrder'];
			$record->maxBlocks = $data['maxBlocks'];
			$record->maxChildBlocks = $data['maxChildBlocks'];
			$record->childBlocks = $data['childBlocks'];
			$record->topLevel = $data['topLevel'];
			$record->uid = $uid;
			$record->fieldLayoutId = $fieldLayout ? $fieldLayout->id : null;
			$record->save(false);

			$blockType->id = $record->id;
			$blockType->fieldId = $fieldId;
			$blockType->name = $data['name'];
			$blockType->handle = $data['handle'];
			$blockType->sortOrder = $data['sortOrder'];
			$blockType->maxBlocks = $data['maxBlocks'];
			$blockType->maxChildBlocks = $data['maxChildBlocks'];
			$blockType->childBlocks = $data['childBlocks'];
			$blockType->topLevel = $data['topLevel'];
			$blockType->uid = $uid;
			$blockType->fieldLayoutId = $fieldLayout ? $fieldLayout->id : null;

			$event = new BlockTypeEvent([
				'blockType' => $blockType,
				'isNew' => $isNew,
			]);

			$this->trigger(self::EVENT_AFTER_SAVE_BLOCK_TYPE, $event);

			$transaction->commit();
		}
		catch (\Throwable $e)
		{
			$transaction->rollBack();

			throw $e;
		}
	}

	/**
	 * Handles deleting a Neo block type and all associated Neo blocks.
	 *
	 * @param ConfigEvent $event
	 * @throws \Throwable
	 */
	public function handleDeletedBlockType(ConfigEvent $event)
	{
		$uid = $event->tokenMatches[0];
		$record = $this->_getRecordByUid($uid);
		
		if ($record->id === null)
		{
			return;
		}
		
		$dbService = Craft::$app->getDb();
		$transaction = $dbService->beginTransaction();
		
		try
		{
			$blockType = $this->getById($record->id);
			
			if ($blockType === null)
			{
				return;
			}
			
			$sitesService = Craft::$app->getSites();
			$elementsService = Craft::$app->getElements();
			$fieldsService = Craft::$app->getFields();
			
			// Delete all blocks of this type
			foreach ($sitesService->getAllSiteIds() as $siteId)
			{
				$blocks = Block::find()
					->siteId($siteId)
					->typeId($blockType->id)
					->inReverse()
					->all();
				
				foreach ($blocks as $block)
				{
					$elementsService->deleteElement($block);
				}
			}
			// Delete the block type's field layout
			$fieldsService->deleteLayoutById($blockType->fieldLayoutId);
			
			// Delete the block type
			$affectedRows = $dbService->createCommand()
				->delete('{{%neoblocktypes}}', ['id' => $blockType->id])
				->execute();
			
			$transaction->commit();
		}
		catch (\Throwable $e)
		{
			$transaction->rollBack();
			throw $e;
		}
	}

	/**
	 * Handles a Neo block type group change.
	 *
	 * @param ConfigEvent $event
	 * @throws \Throwable
	 */
	public function handleChangedBlockTypeGroup(ConfigEvent $event)
	{
		$uid = $event->tokenMatches[0];
		$data = $event->newValue;
		$dbService = Craft::$app->getDb();
		$transaction = $dbService->beginTransaction();

		try
		{
			$record = BlockTypeGroupRecord::findOne(['uid' => $uid]);

			if ($record === null) {
				$record = new BlockTypeGroupRecord();
			}
			
			$record->fieldId = Db::idByUid('{{%fields}}', $data['field']);
			$record->name = $data['name'];
			$record->sortOrder = $data['sortOrder'];
			$record->uid = $uid;
			$record->save(false);

			$transaction->commit();
		}
		catch(\Throwable $e)
		{
			$transaction->rollBack();

			throw $e;
		}
	}

	/**
	 * Handles deleting a Neo block type group.
	 *
	 * @param ConfigEvent $event
	 * @throws \Throwable
	 */
	public function handleDeletedBlockTypeGroup(ConfigEvent $event)
	{
		$uid = $event->tokenMatches[0];
		$dbService = Craft::$app->getDb();
		$transaction = $dbService->beginTransaction();

		try
		{
			$affectedRows = $dbService->createCommand()
				->delete('{{%neoblocktypegroups}}', ['uid' => $uid])
				->execute();

			$transaction->commit();
		}
		catch (\Throwable $e)
		{
			$transaction->rollBack();

			throw $e;
		}
	}

	/**
	 * Renders a Neo block type's tabs.
	 *
	 * @param Block $block The Neo block type having its tabs rendered.
	 * @param bool $static Whether to generate static tab content.
	 * @param string|null $namespace
	 * @param int|null $siteId
	 * @return array The tabs data.
	 */
	public function renderTabs(BlockType $blockType, bool $static = false, $namespace = null, int $siteId = null): array
	{
		$block = new Block();
		$block->typeId = $blockType->id;

		// Ensure that the passed site ID is valid before applying it
		// If the site ID is not passed or is invalid, the block will default to the primary site
		if ($siteId !== null && Craft::$app->getSites()->getSiteById($siteId) !== null)
		{
			$block->siteId = $siteId;
		}

		return Neo::$plugin->blocks->renderTabs($block, $static, $namespace);
	}
	
	/**
	 * Returns all the block types.
	 */
	public function getAllBlockTypes(): array
	{
		$results = $this->_createQuery()
			->all();
		
		foreach ($results as $key => $result) {
			$results[$key] = new BlockType($result);
		}
		
		return $results;
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
				'uid',
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
				'uid',
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

	/**
	 * Returns the block type record with the given UUID, if it exists; otherwise returns a new block type record.
	 *
	 * @param string $uid
	 * @return BlockTypeRecord
	 */
	private function _getRecordByUid(string $uid): BlockTypeRecord
	{
		$record = BlockTypeRecord::findOne(['uid' => $uid]);

		if ($record !== null)
		{
			Memoize::$blockTypeRecordsById[$record->id] = $record;
		}
		else
		{
			$record = new BlockTypeRecord();
		}

		return $record;
	}
}
