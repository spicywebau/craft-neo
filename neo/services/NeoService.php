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
		$locale = $this->_getFieldLocale($fieldType);

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
			$this->_applyFieldTranslationSetting($fieldType, $blocks);

			$this->saveStructure($structure, $fieldType);

			foreach($blocks as $block)
			{
				$block->ownerId = $owner->id;
				$block->ownerLocale = $locale;
			}

			$this->saveBlocks($blocks, $structure);

			$blockIds = [];
			foreach($blocks as $block)
			{
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
				$blockTypeRecord->maxChildBlocks = $blockType->maxChildBlocks;
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
	 * @param int $ownerLocale
	 * @param string|null $locale
	 * @return array
	 */
	public function getBlocks($fieldId, $ownerId, $ownerLocale = null, $locale = null)
	{
		$criteria = $this->getCriteria();

		$criteria->fieldId = $fieldId;
		$criteria->ownerId = $ownerId;
		$criteria->locale = $locale;
		$criteria->ownerLocale = $ownerLocale;
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
	 * Generates the search keywords from a block.
	 *
	 * @param Neo_BlockModel $block
	 * @return string
	 * @throws Exception
	 */
	public function getBlockKeywords(Neo_BlockModel $block)
	{
		$keywords = [];

		$contentTable = craft()->content->contentTable;
		$fieldColumnPrefix = craft()->content->fieldColumnPrefix;
		$fieldContext = craft()->content->fieldContext;

		craft()->content->contentTable = $block->getContentTable();
		craft()->content->fieldColumnPrefix = $block->getFieldColumnPrefix();
		craft()->content->fieldContext = $block->getFieldContext();

		foreach(craft()->fields->getAllFields() as $field)
		{
			$fieldType = $field->getFieldType();

			if($fieldType)
			{
				$fieldType->element = $block;
				$handle = $field->handle;
				$keywords[] = $fieldType->getSearchKeywords($block->getFieldValue($handle));
			}
		}

		craft()->content->contentTable = $contentTable;
		craft()->content->fieldColumnPrefix = $fieldColumnPrefix;
		craft()->content->fieldContext = $fieldContext;

		return StringHelper::arrayToString($keywords, ' ');
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
	 * Saves a list of blocks to the database, and saves them to the structure.
	 *
	 * @param array $blocks
	 * @param StructureModel $structure
	 * @return bool
	 * @throws \Exception
	 */
	public function saveBlocks(array $blocks, StructureModel $structure)
	{
		$transaction = $this->beginTransaction();
		try
		{
			// Build the block structure by mapping block sort orders and levels to parent/child relationships
			$parentStack = [];

			foreach($blocks as $block)
			{
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
		}
		catch(\Exception $e)
		{
			$this->rollbackTransaction($transaction);

			throw $e;
		}

		return true;
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
		$isModified = (craft()->config->get('saveModifiedBlocksOnly', 'neo') ? $block->modified : true);
		$isValid = ($validate ? $this->validateBlock($block) : true);

		if($isModified && $isValid)
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
	 * @param $locale
	 * @return array
	 */
	public function renderBlockTabs(Neo_BlockTypeModel $blockType, Neo_BlockModel $block = null, $namespace = '', $static = false, $locale = null)
	{
		$oldNamespace = craft()->templates->getNamespace();
		$newNamespace = craft()->templates->namespaceInputName($namespace . '[__NEOBLOCK__][fields]', $oldNamespace);
		craft()->templates->setNamespace($newNamespace);

		$tabsHtml = [];

		$fieldLayout = $blockType->getFieldLayout();
		$fieldLayoutTabs = $fieldLayout->getTabs();
		$isFresh = !isset($block);

		if(!$block)
		{
			// Trick Craft into rendering fields of a block type with the correct locale (see below)
			$block = new Neo_BlockModel();
		}

		if($locale)
		{
			// Rendering the `_includes/fields` template doesn't take a `locale` parameter, even though individual
			// field templates do. If no locale is passed when rendering a field (which there won't be when using the
			// `fields` template) it defaults to the passed element's locale. The following takes advantage of this
			// by setting the locale on the block itself. In the event that only a block type is being rendered, the
			// above creates a dummy block to so the locale can be passed.
			$block->locale = $locale;
		}

		foreach($fieldLayoutTabs as $fieldLayoutTab)
		{
			craft()->templates->startJsBuffer();

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
					$fieldType->setIsFresh($isFresh);

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

			$tabHtml['footHtml'] = craft()->templates->clearJsBuffer();
			$tabsHtml[] = $tabHtml;
		}

		craft()->templates->setNamespace($oldNamespace);

		return $tabsHtml;
	}


	// ---- Block structures

	/**
	 * Returns the structure for a field.
	 *
	 * @param NeoFieldType $fieldType
	 * @param $locale
	 * @return StructureModel|bool
	 */
	public function getStructure(NeoFieldType $fieldType, $locale = false)
	{
		$owner = $fieldType->element;
		$field = $fieldType->model;

		if($locale === false)
		{
			$locale = $this->_getFieldLocale($fieldType);
		}

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
	 * @param $locale
	 * @return bool
	 * @throws \Exception
	 */
	public function saveStructure(StructureModel $structure, NeoFieldType $fieldType, $locale = false)
	{
		$owner = $fieldType->element;
		$field = $fieldType->model;

		if($locale === false)
		{
			$locale = $this->_getFieldLocale($fieldType);
		}

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
	 * @param $locale
	 * @return bool
	 * @throws \Exception
	 */
	public function deleteStructure(NeoFieldType $fieldType, $locale = false)
	{
		$owner = $fieldType->element;
		$field = $fieldType->model;

		if($locale === false)
		{
			$locale = $this->_getFieldLocale($fieldType);
		}

		$transaction = $this->beginTransaction();
		try
		{
			$blockStructure = $this->getStructure($fieldType, $locale);

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


	// ---- Helpers

	/**
	 * Checks the current route/environment to see if database calls for Neo blocks should be avoided.
	 * This is so that live preview and entry drafts can use their data instead.
	 *
	 * @return bool
	 */
	public function isPreviewMode()
	{
		if(craft()->request->isLivePreview())
		{
			return true;
		}

		$token = craft()->request->getParam('token');

		if($token)
		{
			$route = craft()->tokens->getTokenRoute($token);

			// If an entry draft is being previewed, use the content stored in the draft
			if($route && $route['action'] == 'entries/viewSharedEntry')
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Converts a Neo field into a Matrix one.
	 * WARNING: Calling this will replace the Neo field with a Matrix one, so use with caution. Performing this
	 * conversion cannot be undone.
	 *
	 * @param FieldModel $neoField
	 * @return bool
	 * @throws \Exception
	 */
	public function convertFieldToMatrix(FieldModel $neoField)
	{
		$neoFieldType = $neoField->getFieldType();
		$globalFields = craft()->fields->getAllFields('id');

		if($neoFieldType instanceof NeoFieldType)
		{
			$transaction = $this->beginTransaction();
			try
			{
				$neoSettings = $neoFieldType->getSettings();
				$matrixSettings = $this->convertSettingsToMatrix($neoSettings, $neoField);

				// Save a mapping of block type handles to their Neo ID for use later on when migrating content
				$neoBlockTypeIds = [];
				foreach($neoSettings->getBlockTypes() as $neoBlockType)
				{
					$neoBlockTypeIds[$neoBlockType->handle] = $neoBlockType->id;
				}

				$matrixField = $neoField->copy();
				$matrixField->type = 'Matrix';
				$matrixField->settings = $matrixSettings;

				$neoBlocks = [];
				$matrixBlocks = [];
				$matrixBlockTypeIdsByBlockId = [];
				$neoToMatrixBlockTypeIds = [];
				$neoToMatrixBlockIds = [];
				$matrixBlockTypeFieldIds = [];
				$newRelations = [];

				foreach(craft()->i18n->getSiteLocales() as $locale)
				{
					// Get all locale content variations of each block
					foreach($this->getBlocks($neoField->id, null, null, $locale->id) as $neoBlock)
					{
						$neoBlocks[] = $neoBlock;
					}

					// Make sure all owner localised blocks are retrieved as well
					foreach($this->getBlocks($neoField->id, null, $locale->id) as $neoBlock)
					{
						$neoBlocks[] = $neoBlock;
					}
				}

				foreach($neoBlocks as $neoBlock)
				{
					$matrixBlock = $this->convertBlockToMatrix($neoBlock);

					// This ID will be replaced with the Matrix block type ID after the field is saved. The Neo's block
					// type id is added here so it can be grabbed when looping over these Matrix blocks later on.
					$matrixBlock->id = $neoBlock->id;
					$matrixBlock->typeId = $neoBlock->typeId;

					$matrixBlocks[] = $matrixBlock;
				}

				$success = craft()->fields->saveField($matrixField, false);

				if(!$success)
				{
					throw new \Exception("Unable to save Matrix field");
				}

				// Create a mapping of Neo block type ID's to Matrix block type ID's. This is used below to set the
				// correct block type to a converted Matrix block.
				foreach($matrixSettings->getBlockTypes() as $matrixBlockType)
				{
					$neoBlockTypeId = $neoBlockTypeIds[$matrixBlockType->handle];
					$neoToMatrixBlockTypeIds[$neoBlockTypeId] = $matrixBlockType->id;

					// Create mapping from newly saved block type field handles to their ID's
					// This is so that relations can be updated later on with the new field ID
					$matrixFieldLayout = $matrixBlockType->getFieldLayout();
					$matrixFields = $matrixFieldLayout->getFields();

					$fieldIds = [];
					foreach($matrixFields as $matrixFieldLayoutField)
					{
						$matrixField = $matrixFieldLayoutField->getField();
						$fieldIds[$matrixField->handle] = $matrixField->id;
					}

					$matrixBlockTypeFieldIds[$matrixBlockType->id] = $fieldIds;
				}

				foreach($matrixBlocks as $matrixBlock)
				{
					$neoBlockId = $matrixBlock->id;

					// Assign the correct block type ID now that it exists (from saving the field above).
					$neoBlockTypeId = $matrixBlock->typeId;
					$matrixBlock->typeId = $neoToMatrixBlockTypeIds[$neoBlockTypeId];

					// Has this block already been saved before? (Happens when saving a block in multiple locales)
					if(array_key_exists($neoBlockId, $neoToMatrixBlockIds))
					{
						$matrixBlockContent = $matrixBlock->getContent();

						// Assign the new ID of the Matrix block as it has been saved before (for a different locale)
						$matrixBlock->id = $neoToMatrixBlockIds[$neoBlockId];
						$matrixBlockContent->elementId = $neoToMatrixBlockIds[$neoBlockId];

						// Saving the Matrix block for the first time causes it to copy it's content into all other
						// locales, meaning there should already exist a record for this block's content. In that case,
						// it's record ID needs to be retrieved so it can be updated correctly.
						$existingContent = craft()->content->getContent($matrixBlock);
						if($existingContent)
						{
							$matrixBlockContent->id = $existingContent->id;
						}
					}
					else
					{
						// Saving this block for the first time, so make sure it doesn't have an id
						$matrixBlock->id = null;
					}

					$success = craft()->matrix->saveBlock($matrixBlock, false);

					if(!$success)
					{
						throw new \Exception("Unable to save Matrix block");
					}

					// Save the new Matrix block ID in case it has different content locales to save
					$neoToMatrixBlockIds[$neoBlockId] = $matrixBlock->id;
					$matrixBlockTypeIdsByBlockId[$matrixBlock->id] = $matrixBlock->typeId;
				}

				// Update the relations with the new Matrix block ID's (sourceId) and Matrix field ID's
				$relations = craft()->db->createCommand()
					->select('fieldId, sourceId, sourceLocale, targetId, sortOrder')
					->from('relations')
					->where(['in', 'sourceId', array_keys($neoToMatrixBlockIds)])
					->queryAll();

				if($relations)
				{
					foreach($relations as $relation)
					{
						$neoBlockId = $relation['sourceId'];
						$matrixBlockId = $neoToMatrixBlockIds[$neoBlockId];
						$matrixBlockTypeId = $matrixBlockTypeIdsByBlockId[$matrixBlockId];

						$globalFieldId = $relation['fieldId'];
						$globalField = $globalFields[$globalFieldId];
						$matrixFieldIds = $matrixBlockTypeFieldIds[$matrixBlockTypeId];
						$matrixFieldId = $matrixFieldIds[$globalField->handle];

						$newRelations[] = [
							$matrixFieldId,
							$matrixBlockId,
							$relation['sourceLocale'],
							$relation['targetId'],
							$relation['sortOrder'],
						];
					}
				}

				craft()->db->createCommand()->insertAll('relations', [
					'fieldId',
					'sourceId',
					'sourceLocale',
					'targetId',
					'sortOrder',
				], $newRelations);

				$this->commitTransaction($transaction);

				return true;
			}
			catch(\Exception $e)
			{
				$this->rollbackTransaction($transaction);

				NeoPlugin::log("Couldn't convert Neo field '{$neoField->handle}' to Matrix: " . $e->getMessage(), LogLevel::Error);

				throw $e;
			}
		}

		return false;
	}

	/**
	 * Converts a Neo settings model to a Matrix settings model.
	 *
	 * @param Neo_SettingsModel $neoSettings
	 * @param FieldModel|null $field
	 * @return MatrixSettingsModel
	 */
	public function convertSettingsToMatrix(Neo_SettingsModel $neoSettings, FieldModel $field = null)
	{
		$matrixSettings = new MatrixSettingsModel($field);
		$matrixBlockTypes = [];

		$ids = 1;
		foreach($neoSettings->getBlockTypes() as $neoBlockType)
		{
			$matrixBlockType = $this->convertBlockTypeToMatrix($neoBlockType, $field);
			$matrixBlockType->id = 'new' . ($ids++);

			$matrixBlockTypes[] = $matrixBlockType;
		}

		$matrixSettings->setBlockTypes($matrixBlockTypes);
		$matrixSettings->maxBlocks = $neoSettings->maxBlocks;

		return $matrixSettings;
	}

	/**
	 * Converts a Neo block type model to a Matrix block type model.
	 *
	 * @param Neo_BlockTypeModel $neoBlockType
	 * @param FieldModel|null $field
	 * @return MatrixBlockTypeModel
	 */
	public function convertBlockTypeToMatrix(Neo_BlockTypeModel $neoBlockType, FieldModel $field = null)
	{
		$matrixBlockType = new MatrixBlockTypeModel();
		$matrixBlockType->fieldId = $field ? $field->id : $neoBlockType->fieldId;
		$matrixBlockType->name = $neoBlockType->name;
		$matrixBlockType->handle = $neoBlockType->handle;

		$neoFieldLayout = $neoBlockType->getFieldLayout();
		$neoFields = $neoFieldLayout->getFields();
		$matrixFields = [];

		// Find all relabels for this block type to use when converting
		$relabels = [];
		if(craft()->plugins->getPlugin('relabel'))
		{
			foreach(craft()->relabel->getLabels($neoFieldLayout->id) as $relabel)
			{
				$relabels[$relabel->fieldId] = [
					'name' => $relabel->name,
					'instructions' => $relabel->instructions,
				];
			}
		}

		$ids = 1;
		foreach($neoFields as $neoFieldLayoutField)
		{
			$neoField = $neoFieldLayoutField->getField();

			if(!in_array($neoField->type, ['Matrix', 'Neo']))
			{
				$matrixField = $neoField->copy();
				$matrixField->id = 'new' . ($ids++);
				$matrixField->groupId = null;
				$matrixField->required = (bool) $neoFieldLayoutField->required;

				// Force disable translation on fields if the Neo field was also translatable
				if($field && $field->translatable)
				{
					$matrixField->translatable = false;
				}

				// Use the relabel name and instructions if they are set for this field
				if(array_key_exists($neoField->id, $relabels))
				{
					foreach($relabels[$neoField->id] as $property => $value)
					{
						if($value)
						{
							$matrixField->$property = $value;
						}
					}
				}

				$matrixFields[] = $matrixField;
			}
		}

		$matrixBlockType->setFields($matrixFields);

		return $matrixBlockType;
	}

	/**
	 * Converts a Neo block model to a Matrix block model, retaining all content.
	 *
	 * @param Neo_BlockModel $neoBlock
	 * @param MatrixBlockTypeModel|null $matrixBlockType
	 * @return MatrixBlockModel
	 */
	public function convertBlockToMatrix(Neo_BlockModel $neoBlock, MatrixBlockTypeModel $matrixBlockType = null)
	{
		$blockContent = $neoBlock->getContent()->copy();
		$blockContent->id = null;
		$blockContent->elementId = null;

		$matrixBlock = new MatrixBlockModel();
		$matrixBlock->setContent($blockContent);

		$matrixBlock->ownerId = $neoBlock->ownerId;
		$matrixBlock->fieldId = $neoBlock->fieldId;
		$matrixBlock->locale = $neoBlock->locale;
		$matrixBlock->ownerLocale = $neoBlock->ownerLocale;
		$matrixBlock->sortOrder = $neoBlock->lft;
		$matrixBlock->collapsed = $neoBlock->collapsed;

		if($matrixBlockType)
		{
			$matrixBlock->typeId = $matrixBlockType->id;
		}

		return $matrixBlock;
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
			->select('id, dateCreated, dateUpdated, fieldId, fieldLayoutId, name, handle, maxBlocks, maxChildBlocks, childBlocks, topLevel, sortOrder')
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
	 * @param $fieldType
	 * @param $blocks
	 * @throws \Exception
	 */
	private function _applyFieldTranslationSetting(NeoFieldType $fieldType, $blocks)
	{
		$owner = $fieldType->element;
		$field = $fieldType->model;

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
			// Clear the existing structure so it can be regenerated with correct locale settings
			$this->deleteStructure($fieldType, $field->translatable ? null : $owner->locale);

			foreach($blocks as $block)
			{
				$block->modified = true;
			}

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
						$newBlocks = [];
						$newStructure = new StructureModel();

						$this->saveStructure($newStructure, $fieldType, $localeId);

						foreach($blocksInOtherLocale as $blockInOtherLocale)
						{
							$originalBlockId = $blockInOtherLocale->id;

							$blockInOtherLocale->id = null;
							$blockInOtherLocale->modified = true;
							$blockInOtherLocale->getContent()->id = null;
							$blockInOtherLocale->ownerLocale = $localeId;

							$newBlocks[] = $blockInOtherLocale;
							$newBlockIds[$originalBlockId][$localeId] = $blockInOtherLocale;
						}

						$this->saveBlocks($newBlocks, $newStructure);
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
								foreach($newBlockIds[$originalBlockId] as $localeId => $newBlock)
								{
									$newBlockId = $newBlock->id;
									$rows[] = [
										$relation['fieldId'],
										$newBlockId,
										$relation['sourceLocale'],
										$relation['targetId'],
										$relation['sortOrder'],
									];
								}
							}
						}

						craft()->db->createCommand()->insertAll('relations', [
							'fieldId',
							'sourceId',
							'sourceLocale',
							'targetId',
							'sortOrder',
						], $rows);
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

						$this->deleteStructure($fieldType, $localeId);
					}

					$this->deleteBlockById($blockIdsToDelete);
				}
			}
		}
	}
}
