<?php
namespace Craft;

/**
 * Class NeoService
 *
 * @package Craft
 */
class NeoService extends BaseApplicationComponent
{
	// Private properties

	private $_blockTypesById;
	private $_groupsById;
	private $_blockTypesByFieldId;
	private $_groupsByFieldId;
	private $_fetchedAllBlockTypesForFieldId;
	private $_fetchedAllGroupsForFieldId;
	private $_blockTypeRecordsById;
	private $_blockRecordsById;
	private $_uniqueBlockTypeAndFieldHandles;


	// Public properties

	/**
	 * @var Neo_BlockTypeModel|null
	 */
	public $currentSavingBlockType;


	// Public methods

	/**
	 * Creates a Neo-specific element criteria model.
	 *
	 * @param mixed|null $attributes
	 * @return Neo_CriteriaModel
	 */
	public function getCriteria($attributes = null)
	{
		if($attributes instanceof ElementCriteriaModel)
		{
			$attributes = $attributes->getAttributes();
		}

		return new Neo_CriteriaModel($attributes);
	}

	/**
	 * Forces a plugin to exist by throwing an error if it doesn't.
	 * Used when supporting other plugins, such as Relabel and Reasons.
	 *
	 * @param $plugin
	 * @throws Exception
	 */
	public function requirePlugin($plugin)
	{
		if(!craft()->plugins->getPlugin($plugin))
		{
			$message = Craft::t("The plugin \"{plugin}\" is required for Neo to use this functionality.", [
				'plugin' => $plugin,
			]);

			throw new Exception($message);
		}
	}


	// -- Fields

	/**
	 * Saves a Neo field's value to the database.
	 *
	 * @param NeoFieldType $fieldType
	 * @throws \Exception
	 */
	public function saveFieldValue(NeoFieldType $fieldType)
	{
		$owner = $fieldType->element;
		$field = $fieldType->model;
		$blocks = $owner->getContent()->getAttribute($field->handle);

		if($blocks === null)
		{
			return;
		}

		$structure = new StructureModel();
		// $structure->maxLevels = ...->maxLevels; // This might be useful somebody. Keeping it around as a reminder.

		if(!is_array($blocks))
		{
			$blocks = [];
		}

		$transaction = $this->beginTransaction();
		try
		{
			// Make sure that the blocks for this field/owner respect the field's translation setting
			$this->_applyFieldTranslationSetting($owner, $field, $blocks);

			$this->saveStructure($structure, $fieldType);

			// Build the block structure by mapping block sort orders and levels to parent/child relationships
			$blockIds = [];
			$parentStack = [];

			foreach($blocks as $block)
			{
				$block->ownerId = $owner->id;
				$block->ownerLocale = $this->_getFieldLocale($fieldType);

				// Remove parent blocks until either empty or a parent block is only one level below this one (meaning
				// it'll be the parent of this block)
				while(!empty($parentStack) && $block->level <= $parentStack[count($parentStack) - 1]->level)
				{
					array_pop($parentStack);
				}

				$this->saveBlock($block, false);

				// If there are no blocks in our stack, it must be a root level block
				if(empty($parentStack))
				{
					craft()->structures->appendToRoot($structure->id, $block);
				}
				// Otherwise, the block at the top of the stack will be the parent
				else
				{
					$parentBlock = $parentStack[count($parentStack) - 1];
					craft()->structures->append($structure->id, $block, $parentBlock);
				}

				// The current block may potentially be a parent block as well, so save it to the stack
				array_push($parentStack, $block);

				$blockIds[] = $block->id;
			}

			// Get the IDs of blocks that are row deleted
			$deletedBlockConditions = ['and',
				'ownerId = :ownerId',
				'fieldId = :fieldId',
				['not in', 'id', $blockIds]
			];

			$deletedBlockParams = [
				':ownerId' => $owner->id,
				':fieldId' => $field->id
			];

			if($field->translatable)
			{
				$deletedBlockConditions[] = 'ownerLocale  = :ownerLocale';
				$deletedBlockParams[':ownerLocale'] = $owner->locale;
			}

			$deletedBlockIds = craft()->db->createCommand()
				->select('id')
				->from('neoblocks')
				->where($deletedBlockConditions, $deletedBlockParams)
				->queryColumn();

			$this->deleteBlockById($deletedBlockIds);

			$this->commitTransaction($transaction);
		}
		catch(\Exception $e)
		{
			$this->rollbackTransaction($transaction);

			throw $e;
		}
	}

	/**
	 * Deletes a Neo field from the database.
	 *
	 * @param FieldModel $neoField
	 * @throws \Exception
	 */
	public function deleteField(FieldModel $neoField)
	{
		$transaction = $this->beginTransaction();
		try
		{
			// Delete the block types
			$blockTypes = $this->getBlockTypesByFieldId($neoField->id);

			foreach($blockTypes as $blockType)
			{
				$this->deleteBlockType($blockType);
			}

			$this->commitTransaction($transaction);
		}
		catch(\Exception $e)
		{
			$this->rollbackTransaction($transaction);

			throw $e;
		}
	}


	// ---- Field settings

	/**
	 * Validates a field's settings, loading the settings and block type models with any error messages.
	 *
	 * @param Neo_SettingsModel $settings
	 * @return bool
	 */
	public function validateFieldSettings(Neo_SettingsModel $settings)
	{
		$validates = true;

		$this->_uniqueBlockTypeAndFieldHandles = [];

		$uniqueAttributes = ['name', 'handle'];
		$uniqueAttributeValues = [];

		foreach($settings->getBlockTypes() as $blockType)
		{
			if(!$this->validateBlockType($blockType, false))
			{
				// Don't break out of the loop because we still want to get validation errors for the remaining block
				// types.
				$validates = false;
			}

			// Do our own unique name/handle validation, since the DB-based validation can't be trusted when saving
			// multiple records at once
			foreach($uniqueAttributes as $attribute)
			{
				$value = $blockType->$attribute;

				if($value && (!isset($uniqueAttributeValues[$attribute]) || !in_array($value, $uniqueAttributeValues[$attribute])))
				{
					$uniqueAttributeValues[$attribute][] = $value;
				}
				else
				{
					$blockType->addError($attribute, Craft::t('{attribute} "{value}" has already been taken.', [
						'attribute' => $blockType->getAttributeLabel($attribute),
						'value' => HtmlHelper::encode($value)
					]));

					$validates = false;
				}
			}
		}

		return $validates;
	}

	/**
	 * Saves a field's settings to the database.
	 *
	 * @param Neo_SettingsModel $settings
	 * @param bool|true $validate
	 * @return bool
	 * @throws \Exception
	 */
	public function saveSettings(Neo_SettingsModel $settings, $validate = true)
	{
		if(!$validate || $this->validateFieldSettings($settings))
		{
			$transaction = $this->beginTransaction();
			try
			{
				$neoField = $settings->getField();

				// Delete the old block types and groups

				// Delete the old block types first, in case there's a handle conflict with one of the new ones
				$oldBlockTypes = $this->getBlockTypesByFieldId($neoField->id);
				$oldBlockTypesById = [];

				foreach($oldBlockTypes as $blockType)
				{
					$oldBlockTypesById[$blockType->id] = $blockType;
				}

				foreach($settings->getBlockTypes() as $blockType)
				{
					if(!$blockType->isNew())
					{
						unset($oldBlockTypesById[$blockType->id]);
					}
				}

				foreach($oldBlockTypesById as $blockType)
				{
					$this->deleteBlockType($blockType);
				}

				$this->deleteGroupsByFieldId($neoField->id);

				// Save the new block types and groups

				foreach($settings->getBlockTypes() as $blockType)
				{
					$blockType->fieldId = $neoField->id;
					$this->saveBlockType($blockType, false);
				}

				foreach($settings->getGroups() as $group)
				{
					$group->fieldId = $neoField->id;
					$this->saveGroup($group);
				}

				$this->commitTransaction($transaction);

				// Update our cache of this field's block types and groups
				$fieldId = $settings->getField()->id;
				$this->_blockTypesByFieldId[$fieldId] = $settings->getBlockTypes();
				$this->_groupsByFieldId[$fieldId] = $settings->getGroups();

				return true;
			}
			catch(\Exception $e)
			{
				$this->rollbackTransaction($transaction);

				throw $e;
			}
		}

		return false;
	}


	// -- Block Types

	/**
	 * Returns a list of block types associated with a field.
	 *
	 * @param $fieldId
	 * @param string|null $indexBy
	 * @return array
	 */
	public function getBlockTypesByFieldId($fieldId, $indexBy = null)
	{
		if(empty($this->_fetchedAllBlockTypesForFieldId[$fieldId]))
		{
			$this->_blockTypesByFieldId[$fieldId] = [];

			$results = $this->_createBlockTypeQuery()
				->where('fieldId = :fieldId', [':fieldId' => $fieldId])
				->queryAll();

			foreach($results as $result)
			{
				$blockType = new Neo_BlockTypeModel($result);
				$this->_blockTypesById[$blockType->id] = $blockType;
				$this->_blockTypesByFieldId[$fieldId][] = $blockType;
			}

			$this->_fetchedAllBlockTypesForFieldId[$fieldId] = true;
		}

		if($indexBy)
		{
			return $this->_indexBy($this->_blockTypesByFieldId[$fieldId], $indexBy);
		}

		return $this->_blockTypesByFieldId[$fieldId];
	}

	/**
	 * Finds a block type by it's ID.
	 *
	 * @param $blockTypeId
	 * @return Neo_BlockTypeModel
	 */
	public function getBlockTypeById($blockTypeId)
	{
		if(!isset($this->_blockTypesById) || !array_key_exists($blockTypeId, $this->_blockTypesById))
		{
			$result = $this->_createBlockTypeQuery()
				->where('id = :id', [':id' => $blockTypeId])
				->queryRow();

			if($result)
			{
				$blockType = new Neo_BlockTypeModel($result);
			}
			else
			{
				$blockType = null;
			}

			$this->_blockTypesById[$blockTypeId] = $blockType;
		}

		return $this->_blockTypesById[$blockTypeId];
	}

	/**
	 * Runs validation on a block type, and saves any errors to the block type.
	 *
	 * @param Neo_BlockTypeModel $blockType
	 * @param bool|true $validateUniques
	 * @return bool
	 * @throws Exception
	 */
	public function validateBlockType(Neo_BlockTypeModel $blockType, $validateUniques = true)
	{
		$validates = true;

		$blockTypeRecord = $this->_getBlockTypeRecord($blockType);

		$blockTypeRecord->fieldId = $blockType->fieldId;
		$blockTypeRecord->name = $blockType->name;
		$blockTypeRecord->handle = $blockType->handle;

		if(!$blockTypeRecord->validateUniques($validateUniques))
		{
			$validates = false;
			$blockType->addErrors($blockTypeRecord->getErrors());
		}

		return $validates;
	}

	/**
	 * Saves a block type to the database.
	 *
	 * @param Neo_BlockTypeModel $blockType
	 * @param bool|true $validate
	 * @return bool
	 * @throws \Exception
	 */
	public function saveBlockType(Neo_BlockTypeModel $blockType, $validate = true)
	{
		if(!$validate || $this->validateBlockType($blockType))
		{
			// Save this for use by plugins (eg. Reasons)
			$this->currentSavingBlockType = $blockType;

			$transaction = $this->beginTransaction();
			try
			{
				// Get the block type record
				$blockTypeRecord = $this->_getBlockTypeRecord($blockType);
				$isNewBlockType = $blockType->isNew();
				$oldBlockType = $isNewBlockType ? null : Neo_BlockTypeModel::populateModel($blockTypeRecord);

				// Is there a new field layout?
				$fieldLayout = $blockType->getFieldLayout();

				if(!$fieldLayout->id)
				{
					// Delete the old one
					if(!$isNewBlockType && $oldBlockType->fieldLayoutId)
					{
						craft()->fields->deleteLayoutById($oldBlockType->fieldLayoutId);
					}

					// Save the new one
					craft()->fields->saveLayout($fieldLayout);

					// Update the entry type record/model with the new layout ID
					$blockType->fieldLayoutId = $fieldLayout->id;
					$blockTypeRecord->fieldLayoutId = $fieldLayout->id;
				}

				// Set the basic info on the new block type record
				$blockTypeRecord->fieldId = $blockType->fieldId;
				$blockTypeRecord->name = $blockType->name;
				$blockTypeRecord->handle = $blockType->handle;
				$blockTypeRecord->sortOrder = $blockType->sortOrder;
				$blockTypeRecord->maxBlocks = $blockType->maxBlocks;
				$blockTypeRecord->childBlocks = $blockType->childBlocks;
				$blockTypeRecord->topLevel = $blockType->topLevel;

				// Save it, minus the field layout for now
				$blockTypeRecord->save(false);

				if($isNewBlockType)
				{
					// Set the new ID on the model
					$blockType->id = $blockTypeRecord->id;
				}

				// Update the block type with the field layout ID
				$blockTypeRecord->save(false);

				$this->commitTransaction($transaction);
			}
			catch(\Exception $e)
			{
				$this->rollbackTransaction($transaction);

				throw $e;
			}

			// Dereference the block type just for good measure
			$this->currentSavingBlockType = null;

			return true;
		}

		return false;
	}

	/**
	 * Deletes a block type from the database.
	 *
	 * @param Neo_BlockTypeModel $blockType
	 * @return bool
	 * @throws \Exception
	 */
	public function deleteBlockType(Neo_BlockTypeModel $blockType)
	{
		$transaction = $this->beginTransaction();
		try
		{
			// First delete the blocks of this type
			$blockIds = craft()->db->createCommand()
				->select('id')
				->from('neoblocks')
				->where(['typeId' => $blockType->id])
				->queryColumn();

			$this->deleteBlockById($blockIds);

			// Delete the field layout
			craft()->fields->deleteLayoutById($blockType->fieldLayoutId);

			// Finally delete the actual block type
			$affectedRows = craft()->db->createCommand()
				->delete('neoblocktypes', ['id' => $blockType->id]);

			$this->commitTransaction($transaction);

			return (bool) $affectedRows;
		}
		catch(\Exception $e)
		{
			$this->rollbackTransaction($transaction);

			throw $e;
		}
	}


	// -- Groups

	/**
	 * Returns a list of groups associated with a field.
	 *
	 * @param $fieldId
	 * @param string|null $indexBy
	 * @return array
	 */
	public function getGroupsByFieldId($fieldId, $indexBy = null)
	{
		if(empty($this->_fetchedAllGroupsForFieldId[$fieldId]))
		{
			$this->_groupsByFieldId[$fieldId] = [];

			$results = $this->_createGroupQuery()
				->where('fieldId = :fieldId', [':fieldId' => $fieldId])
				->queryAll();

			foreach($results as $result)
			{
				$group = new Neo_GroupModel($result);
				$this->_groupsById[$group->id] = $group;
				$this->_groupsByFieldId[$fieldId][] = $group;
			}

			$this->_fetchedAllGroupsForFieldId[$fieldId] = true;
		}

		if($indexBy)
		{
			return $this->_indexBy($this->_groupsByFieldId[$fieldId], $indexBy);
		}

		return $this->_groupsByFieldId[$fieldId];
	}

	/**
	 * Saves a group to the database.
	 *
	 * @param Neo_GroupModel $group
	 * @return bool
	 * @throws \Exception
	 */
	public function saveGroup(Neo_GroupModel $group)
	{
		$transaction = $this->beginTransaction();
		try
		{
			$groupRecord = new Neo_GroupRecord();
			$groupRecord->fieldId = $group->fieldId;
			$groupRecord->name = $group->name;
			$groupRecord->sortOrder = $group->sortOrder;

			$groupRecord->save(false);

			$group->id = $groupRecord->id;

			$this->commitTransaction($transaction);
		}
		catch(\Exception $e)
		{
			$this->rollbackTransaction($transaction);

			throw $e;
		}

		return true;
	}

	/**
	 * Deletes all groups associated with a field from the database.
	 *
	 * @param $fieldId
	 * @return bool
	 * @throws \Exception
	 */
	public function deleteGroupsByFieldId($fieldId)
	{
		$transaction = $this->beginTransaction();
		try
		{
			$affectedRows = craft()->db->createCommand()
				->delete('neogroups', ['fieldId' => $fieldId]);

			$this->commitTransaction($transaction);

			return (bool) $affectedRows;
		}
		catch(\Exception $e)
		{
			$this->rollbackTransaction($transaction);

			throw $e;
		}
	}


	// -- Blocks

	/**
	 * @param int $fieldId
	 * @param int $ownerId
	 * @param string|null $locale
	 * @return array
	 */
	public function getBlocks($fieldId, $ownerId, $locale = null)
	{
		$criteria = $this->getCriteria();

		$criteria->fieldId = $fieldId;
		$criteria->ownerId = $ownerId;
		$criteria->locale = $locale;
		$criteria->status = null;
		$criteria->localeEnabled = null;
		$criteria->limit = null;

		return $criteria->find();
	}

	/**
	 * Returns a block given it's ID.
	 *
	 * @param int $blockId
	 * @param int|null $localeId
	 * @return Neo_BlockModel
	 */
	public function getBlockById($blockId, $localeId = null)
	{
		return craft()->elements->getElementById($blockId, Neo_ElementType::NeoBlock, $localeId);
	}

	/**
	 * Runs validation on a block, and saves any errors to the block.
	 *
	 * @param Neo_BlockModel $block
	 * @return bool
	 * @throws Exception
	 */
	public function validateBlock(Neo_BlockModel $block)
	{
		$block->clearErrors();

		$blockRecord = $this->_getBlockRecord($block);

		$blockRecord->fieldId   = $block->fieldId;
		$blockRecord->ownerId   = $block->ownerId;
		$blockRecord->typeId    = $block->typeId;
		$blockRecord->collapsed = $block->collapsed;

		$blockRecord->validate();
		$block->addErrors($blockRecord->getErrors());

		if(!craft()->content->validateContent($block))
		{
			$block->addErrors($block->getContent()->getErrors());
		}

		return !$block->hasErrors();
	}

	/**
	 * Saves a block to the database.
	 *
	 * @param Neo_BlockModel $block
	 * @param bool|true $validate
	 * @return bool
	 * @throws Exception
	 * @throws \Exception
	 */
	public function saveBlock(Neo_BlockModel $block, $validate = true)
	{
		if($block->modified && (!$validate || $this->validateBlock($block)))
		{
			$blockRecord = $this->_getBlockRecord($block);
			$isNewBlock = $blockRecord->isNewRecord();

			$blockRecord->fieldId     = $block->fieldId;
			$blockRecord->ownerId     = $block->ownerId;
			$blockRecord->ownerLocale = $block->ownerLocale;
			$blockRecord->typeId      = $block->typeId;
			$blockRecord->collapsed   = $block->collapsed;

			$transaction = $this->beginTransaction();
			try
			{
				if(craft()->elements->saveElement($block, false))
				{
					if($isNewBlock)
					{
						$blockRecord->id = $block->id;
					}

					$blockRecord->save(false);

					$this->commitTransaction($transaction);

					return true;
				}
			}
			catch(\Exception $e)
			{
				$this->rollbackTransaction($transaction);

				throw $e;
			}
		}

		return false;
	}

	/**
	 * Saves a block's expansion state to the database.
	 * It bypasses the elements system and performs a direct database query. This is so it doesn't cause caches to be
	 * regenerated. It's also more performant, which comes in handy when this is called in AJAX requests.
	 *
	 * @param Neo_BlockModel $block
	 * @return bool
	 */
	public function saveBlockCollapse(Neo_BlockModel $block)
	{
		$tableName = (new Neo_BlockRecord())->getTableName();

		craft()->db->createCommand()->update(
			$tableName,
			['collapsed' => $block->collapsed ? 1 : 0],
			'id = :id',
			[':id' => $block->id]
		);

		return true;
	}

	/**
	 * Deletes a block (or series of blocks) by it's ID from the database.
	 *
	 * @param int,array $blockIds
	 * @return bool
	 */
	public function deleteBlockById($blockIds)
	{
		if(!$blockIds)
		{
			return false;
		}

		if(!is_array($blockIds))
		{
			$blockIds = [$blockIds];
		}

		// Pass this along to ElementsService for the heavy lifting
		return craft()->elements->deleteElementById($blockIds);
	}

	/**
	 * Builds the HTML for an individual block type.
	 * If you don't pass in a block along with the type, then it'll render a base template to build real blocks from.
	 * If you do pass in a block, then it's current field values will be rendered as well.
	 *
	 * @param Neo_BlockTypeModel $blockType
	 * @param Neo_BlockModel|null $block
	 * @param string $namespace
	 * @param bool|false $static
	 * @return array
	 */
	public function renderBlockTabs(Neo_BlockTypeModel $blockType, Neo_BlockModel $block = null, $namespace = '', $static = false)
	{
		$headHtml = craft()->templates->getHeadHtml();
		$footHtml = craft()->templates->getFootHtml();

		$oldNamespace = craft()->templates->getNamespace();
		$newNamespace = craft()->templates->namespaceInputName($namespace . '[__NEOBLOCK__][fields]', $oldNamespace);
		craft()->templates->setNamespace($newNamespace);

		$tabsHtml = [];

		$fieldLayout = $blockType->getFieldLayout();
		$fieldLayoutTabs = $fieldLayout->getTabs();

		foreach($fieldLayoutTabs as $fieldLayoutTab)
		{
			$tabHtml = [
				'name' => Craft::t($fieldLayoutTab->name),
				'headHtml' => '',
				'bodyHtml' => '',
				'footHtml' => '',
				'errors' => [],
			];

			$fieldLayoutFields = $fieldLayoutTab->getFields();

			foreach($fieldLayoutFields as $fieldLayoutField)
			{
				$field = $fieldLayoutField->getField();
				$fieldType = $field->getFieldType();

				if($fieldType)
				{
					$fieldType->element = $block;
					$fieldType->setIsFresh($block == null);

					if($block)
					{
						$fieldErrors = $block->getErrors($field->handle);

						if(!empty($fieldErrors))
						{
							$tabHtml['errors'] = array_merge($tabHtml['errors'], $fieldErrors);
						}
					}
				}
			}

			$tabHtml['bodyHtml'] = craft()->templates->namespaceInputs(craft()->templates->render('_includes/fields', [
				'namespace' => null,
				'element' => $block,
				'fields' => $fieldLayoutFields,
				'static' => $static,
			]));

			foreach($fieldLayoutFields as $fieldLayoutField)
			{
				$fieldType = $fieldLayoutField->getField()->getFieldType();

				if($fieldType)
				{
					$fieldType->setIsFresh(null);
				}
			}

			$tabHtml['headHtml'] = craft()->templates->getHeadHtml();
			$tabHtml['footHtml'] = craft()->templates->getFootHtml();

			$tabsHtml[] = $tabHtml;
		}

		craft()->templates->setNamespace($oldNamespace);

		craft()->templates->includeHeadHtml($headHtml);
		craft()->templates->includeFootHtml($footHtml);

		return $tabsHtml;
	}


	// ---- Block structures

	/**
	 * Returns the structure for a field.
	 *
	 * @param NeoFieldType $fieldType
	 * @return StructureModel|bool
	 */
	public function getStructure(NeoFieldType $fieldType)
	{
		$owner = $fieldType->element;
		$field = $fieldType->model;
		$locale = $this->_getFieldLocale($fieldType);

		$result = craft()->db->createCommand()
			->select('structureId')
			->from('neoblockstructures')
			->where('ownerId = :ownerId', [':ownerId' => $owner->id])
			->andWhere('fieldId = :fieldId', [':fieldId' => $field->id]);

		if($locale)
		{
			$result = $result->andWhere('ownerLocale = :ownerLocale', [':ownerLocale' => $locale]);
		}
		else
		{
			$result = $result->andWhere('ownerLocale IS NULL');
		}

		$result = $result->queryRow();

		if($result)
		{
			return craft()->structures->getStructureById($result['structureId']);
		}

		return false;
	}

	/**
	 * Saves the structure for a field to the database.
	 *
	 * @param StructureModel $structure
	 * @param NeoFieldType $fieldType
	 * @return bool
	 * @throws \Exception
	 */
	public function saveStructure(StructureModel $structure, NeoFieldType $fieldType)
	{
		$owner = $fieldType->element;
		$field = $fieldType->model;
		$locale = $this->_getFieldLocale($fieldType);

		$blockStructure = new Neo_BlockStructureRecord();

		$transaction = $this->beginTransaction();
		try
		{
			$this->deleteStructure($fieldType);

			craft()->structures->saveStructure($structure);

			$blockStructure->structureId = $structure->id;
			$blockStructure->ownerId = $owner->id;
			$blockStructure->ownerLocale = $locale;
			$blockStructure->fieldId = $field->id;
			$blockStructure->save(false);

			$this->commitTransaction($transaction);
		}
		catch(\Exception $e)
		{
			$this->rollbackTransaction($transaction);

			throw $e;
		}

		return true;
	}

	/**
	 * Deletes the structure for a field from the database.
	 *
	 * @param NeoFieldType $fieldType
	 * @return bool
	 * @throws \Exception
	 */
	public function deleteStructure(NeoFieldType $fieldType)
	{
		$owner = $fieldType->element;
		$field = $fieldType->model;
		$locale = $this->_getFieldLocale($fieldType);

		$transaction = $this->beginTransaction();
		try
		{
			$blockStructure = $this->getStructure($fieldType);

			if($blockStructure)
			{
				craft()->structures->deleteStructureById($blockStructure->id);
			}

			craft()->db->createCommand()
				->delete('neoblockstructures', [
					'ownerLocale' => $locale ? $locale : null,
					'ownerId' => $owner->id,
					'fieldId' => $field->id,
				]);

			$this->commitTransaction($transaction);
		}
		catch(\Exception $e)
		{
			$this->rollbackTransaction($transaction);

			throw $e;
		}

		return true;
	}

	
	// Protected methods

	/**
	 * Returns a new database transaction if one hasn't already been created.
	 *
	 * @return \CDbTransaction|null
	 */
	protected function beginTransaction()
	{
		return craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
	}

	/**
	 * Commits a database transaction if transaction is not `null`.
	 *
	 * @param \CDbTransaction $transaction
	 */
	protected function commitTransaction($transaction)
	{
		if($transaction)
		{
			$transaction->commit();
		}
	}

	/**
	 * Rolls back a database transaction if transaction is not `null`.
	 *
	 * @param \CDbTransaction $transaction
	 */
	protected function rollbackTransaction($transaction)
	{
		if($transaction)
		{
			$transaction->rollback();
		}
	}
	

	// Private methods

	/**
	 * Changes how an array is indexed based on it's containing objects properties.
	 * 
	 * @param $list
	 * @param $property
	 * @return array
	 */
	private function _indexBy($list, $property)
	{
		$newList = [];

		foreach($list as $item)
		{
			$newList[$item->$property] = $item;
		}

		return $newList;
	}

	/**
	 * Creates a base database query for all block types.
	 *
	 * @return mixed
	 */
	private function _createBlockTypeQuery()
	{
		return craft()->db->createCommand()
			->select('id, dateCreated, dateUpdated, fieldId, fieldLayoutId, name, handle, maxBlocks, childBlocks, topLevel, sortOrder')
			->from('neoblocktypes')
			->order('sortOrder');
	}

	/**
	 * Creates a base database query for all groups.
	 *
	 * @return mixed
	 */
	private function _createGroupQuery()
	{
		return craft()->db->createCommand()
			->select('id, fieldId, name, sortOrder')
			->from('neogroups')
			->order('sortOrder');
	}

	/**
	 * Returns the locale for a given field if it's set to be translatable, otherwise returns null.
	 *
	 * @param NeoFieldType $fieldType
	 * @return string|null
	 */
	private function _getFieldLocale(NeoFieldType $fieldType)
	{
		$owner = $fieldType->element;
		$field = $fieldType->model;

		return $field->translatable ? $owner->locale : null;
	}

	/**
	 * Finds and returns the block type record from a block type model.
	 * If the block type is just newly created, a fresh record will be returned.
	 *
	 * @param Neo_BlockTypeModel $blockType
	 * @return Neo_BlockTypeRecord
	 * @throws Exception
	 */
	private function _getBlockTypeRecord(Neo_BlockTypeModel $blockType)
	{
		if(!$blockType->isNew())
		{
			$blockTypeId = $blockType->id;

			if(!isset($this->_blockTypeRecordsById) || !array_key_exists($blockTypeId, $this->_blockTypeRecordsById))
			{
				$this->_blockTypeRecordsById[$blockTypeId] = Neo_BlockTypeRecord::model()->findById($blockTypeId);

				if(!$this->_blockTypeRecordsById[$blockTypeId])
				{
					throw new Exception(Craft::t('No block type exists with the ID “{id}”.', ['id' => $blockTypeId]));
				}
			}

			return $this->_blockTypeRecordsById[$blockTypeId];
		}

		return new Neo_BlockTypeRecord();
	}

	/**
	 * Finds and returns the block record from a block model.
	 * If the block is just newly created, a fresh record will be returned.
	 *
	 * @param Neo_BlockModel $block
	 * @return Neo_BlockRecord
	 * @throws Exception
	 */
	private function _getBlockRecord(Neo_BlockModel $block)
	{
		$blockId = $block->id;

		if($blockId)
		{
			if(!isset($this->_blockRecordsById) || !array_key_exists($blockId, $this->_blockRecordsById))
			{
				$this->_blockRecordsById[$blockId] = Neo_BlockRecord::model()->with('element')->findById($blockId);

				if(!$this->_blockRecordsById[$blockId])
				{
					throw new Exception(Craft::t('No block exists with the ID “{id}”.', ['id' => $blockId]));
				}
			}

			return $this->_blockRecordsById[$blockId];
		}

		return new Neo_BlockRecord();
	}

	/**
	 * Applies a field's translation setting to any existing values of the field.
	 * Manages migrating a field's values to/from a locale if required. Looks through all blocks associated with the
	 * field, and modifies their locale status appropriately.
	 *
	 * @param $owner
	 * @param $field
	 * @param $blocks
	 * @throws \Exception
	 */
	private function _applyFieldTranslationSetting($owner, $field, $blocks)
	{
		// Does it look like any work is needed here?
		$applyNewTranslationSetting = false;

		foreach($blocks as $block)
		{
			if($block->id && (
				($field->translatable && !$block->ownerLocale) ||
				(!$field->translatable && $block->ownerLocale)
			))
			{
				$applyNewTranslationSetting = true;
				break;
			}
		}

		if($applyNewTranslationSetting)
		{
			// Get all of the blocks for this field/owner that use the other locales, whose ownerLocale attribute is set
			// incorrectly
			$blocksInOtherLocales = [];

			$criteria = craft()->elements->getCriteria(Neo_ElementType::NeoBlock);
			$criteria->fieldId = $field->id;
			$criteria->ownerId = $owner->id;
			$criteria->status = null;
			$criteria->localeEnabled = null;
			$criteria->limit = null;

			if($field->translatable)
			{
				$criteria->ownerLocale = ':empty:';
			}

			foreach(craft()->i18n->getSiteLocaleIds() as $localeId)
			{
				if($localeId == $owner->locale)
				{
					continue;
				}

				$criteria->locale = $localeId;

				if(!$field->translatable)
				{
					$criteria->ownerLocale = $localeId;
				}

				$blocksInOtherLocale = $criteria->find();

				if($blocksInOtherLocale)
				{
					$blocksInOtherLocales[$localeId] = $blocksInOtherLocale;
				}
			}

			if($blocksInOtherLocales)
			{
				if($field->translatable)
				{
					$newBlockIds = [];

					// Duplicate the other-locale blocks so each locale has their own unique set of blocks
					foreach($blocksInOtherLocales as $localeId => $blocksInOtherLocale)
					{
						foreach($blocksInOtherLocale as $blockInOtherLocale)
						{
							$originalBlockId = $blockInOtherLocale->id;

							$blockInOtherLocale->id = null;
							$blockInOtherLocale->getContent()->id = null;
							$blockInOtherLocale->ownerLocale = $localeId;
							$this->saveBlock($blockInOtherLocale, false);

							$newBlockIds[$originalBlockId][$localeId] = $blockInOtherLocale->id;
						}
					}

					// Duplicate the relations, too.  First by getting all of the existing relations for the original
					// blocks
					$relations = craft()->db->createCommand()
						->select('fieldId, sourceId, sourceLocale, targetId, sortOrder')
						->from('relations')
						->where(['in', 'sourceId', array_keys($newBlockIds)])
						->queryAll();

					if($relations)
					{
						// Now duplicate each one for the other locales' new blocks
						$rows = [];

						foreach($relations as $relation)
						{
							$originalBlockId = $relation['sourceId'];

							// Just to be safe...
							if(isset($newBlockIds[$originalBlockId]))
							{
								foreach($newBlockIds[$originalBlockId] as $localeId => $newBlockId)
								{
									$rows[] = [$relation['fieldId'], $newBlockId, $relation['sourceLocale'], $relation['targetId'], $relation['sortOrder']];
								}
							}
						}

						craft()->db->createCommand()->insertAll('relations', ['fieldId', 'sourceId', 'sourceLocale', 'targetId', 'sortOrder'], $rows);
					}
				}
				else
				{
					// Delete all of these blocks
					$blockIdsToDelete = [];

					foreach($blocksInOtherLocales as $localeId => $blocksInOtherLocale)
					{
						foreach($blocksInOtherLocale as $blockInOtherLocale)
						{
							$blockIdsToDelete[] = $blockInOtherLocale->id;
						}
					}

					$this->deleteBlockById($blockIdsToDelete);
				}
			}
		}
	}
}
