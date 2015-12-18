<?php
namespace Craft;

class NeoService extends BaseApplicationComponent
{
	private $_blockTypesById;
	private $_blockTypesByFieldId;
	private $_fetchedAllBlockTypesForFieldId;
	private $_blockTypeRecordsById;
	private $_blockRecordsById;
	private $_uniqueBlockTypeAndFieldHandles;
	private $_parentNeoFields;

	public function getBlockTypesByFieldId($fieldId, $indexBy = null)
	{
		if(empty($this->_fetchedAllBlockTypesForFieldId[$fieldId]))
		{
			$this->_blockTypesByFieldId[$fieldId] = array();

			$results = $this->_createBlockTypeQuery()
				->where('fieldId = :fieldId', array(':fieldId' => $fieldId))
				->queryAll();

			foreach($results as $result)
			{
				$blockType = new NeoBlockTypeModel($result);
				$this->_blockTypesById[$blockType->id] = $blockType;
				$this->_blockTypesByFieldId[$fieldId][] = $blockType;
			}

			$this->_fetchedAllBlockTypesForFieldId[$fieldId] = true;
		}

		if(!$indexBy)
		{
			return $this->_blockTypesByFieldId[$fieldId];
		}

		$blockTypes = array();

		foreach($this->_blockTypesByFieldId[$fieldId] as $blockType)
		{
			$blockTypes[$blockType->$indexBy] = $blockType;
		}

		return $blockTypes;
	}

	public function getBlockTypeById($blockTypeId)
	{
		if(!isset($this->_blockTypesById) || !array_key_exists($blockTypeId, $this->_blockTypesById))
		{
			$result = $this->_createBlockTypeQuery()
				->where('id = :id', array(':id' => $blockTypeId))
				->queryRow();

			if($result)
			{
				$blockType = new NeoBlockTypeModel($result);
			}
			else
			{
				$blockType = null;
			}

			$this->_blockTypesById[$blockTypeId] = $blockType;
		}

		return $this->_blockTypesById[$blockTypeId];
	}

	public function validateBlockType(NeoBlockTypeModel $blockType, $validateUniques = true)
	{
		$validates = true;

		$blockTypeRecord = $this->_getBlockTypeRecord($blockType);

		$blockTypeRecord->fieldId = $blockType->fieldId;
		$blockTypeRecord->name    = $blockType->name;
		$blockTypeRecord->handle  = $blockType->handle;

		$blockTypeRecord->validateUniques = $validateUniques;

		if(!$blockTypeRecord->validate())
		{
			$validates = false;
			$blockType->addErrors($blockTypeRecord->getErrors());
		}

		$blockTypeRecord->validateUniques = true;

		// Can't validate multiple new rows at once so we'll need to give these temporary context to avoid false unique
		// handle validation errors, and just validate those manually. Also apply the future fieldColumnPrefix so that
		// field handle validation takes its length into account.
		$contentService = craft()->content;
		$originalFieldContext      = $contentService->fieldContext;
		$originalFieldColumnPrefix = $contentService->fieldColumnPrefix;

		$contentService->fieldContext      = StringHelper::randomString(10);
		$contentService->fieldColumnPrefix = 'field_' . $blockType->handle . '_';

		foreach($blockType->getFields() as $field)
		{
			// Hack to allow blank field names
			if(!$field->name)
			{
				$field->name = '__blank__';
			}

			craft()->fields->validateField($field);

			// Make sure the block type handle + field handle combo is unique for the whole field. This prevents us from
			// worrying about content column conflicts like "a" + "b_c" == "a_b" + "c".
			if($blockType->handle && $field->handle)
			{
				$blockTypeAndFieldHandle = $blockType->handle . '_' . $field->handle;

				if(in_array($blockTypeAndFieldHandle, $this->_uniqueBlockTypeAndFieldHandles))
				{
					// This error *might* not be entirely accurate, but it's such an edge case that it's probably better
					// for the error to be worded for the common problem (two duplicate handles within the same block
					// type).
					$error = Craft::t('{attribute} "{value}" has already been taken.', array(
						'attribute' => Craft::t('Handle'),
						'value' => $field->handle
					));

					$field->addError('handle', $error);
				}
				else
				{
					$this->_uniqueBlockTypeAndFieldHandles[] = $blockTypeAndFieldHandle;
				}
			}

			if($field->hasErrors() || $field->hasSettingErrors())
			{
				$blockType->hasFieldErrors = true;
				$validates = false;
			}
		}

		$contentService->fieldContext      = $originalFieldContext;
		$contentService->fieldColumnPrefix = $originalFieldColumnPrefix;

		return $validates;
	}

	public function saveBlockType(NeoBlockTypeModel $blockType, $validate = true)
	{
		if(!$validate || $this->validateBlockType($blockType))
		{
			$transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;

			try
			{
				$contentService = craft()->content;
				$fieldsService  = craft()->fields;

				$originalFieldContext         = $contentService->fieldContext;
				$originalFieldColumnPrefix    = $contentService->fieldColumnPrefix;
				$originalOldFieldColumnPrefix = $fieldsService->oldFieldColumnPrefix;

				// Get the block type record
				$blockTypeRecord = $this->_getBlockTypeRecord($blockType);
				$isNewBlockType = $blockType->isNew();

				if(!$isNewBlockType)
				{
					// Get the old block type fields
					$oldBlockTypeRecord = NeoBlockTypeRecord::model()->findById($blockType->id);
					$oldBlockType = NeoBlockTypeModel::populateModel($oldBlockTypeRecord);

					$contentService->fieldContext        = 'global';
					$contentService->fieldColumnPrefix   = 'field_' . $oldBlockType->handle . '_';
					$fieldsService->oldFieldColumnPrefix = 'field_' . $oldBlockType->handle . '_';

					// Refresh the schema cache
					craft()->db->getSchema()->refresh();
				}

				// Set the basic info on the new block type record
				$blockTypeRecord->fieldId   = $blockType->fieldId;
				$blockTypeRecord->name      = $blockType->name;
				$blockTypeRecord->handle    = $blockType->handle;
				$blockTypeRecord->sortOrder = $blockType->sortOrder;

				// Save it, minus the field layout for now
				$blockTypeRecord->save(false);

				if($isNewBlockType)
				{
					// Set the new ID on the model
					$blockType->id = $blockTypeRecord->id;
				}

				// Save the fields and field layout
				// -------------------------------------------------------------

				$fieldLayoutFields = array();
				$sortOrder = 0;

				// Resetting the fieldContext here might be redundant if this isn't a new blocktype but whatever
				$contentService->fieldContext      = 'global';
				$contentService->fieldColumnPrefix = 'field_' . $blockType->handle . '_';

				foreach($blockType->getFields() as $field)
				{
					// Hack to allow blank field names
					if(!$field->name)
					{
						$field->name = '__blank__';
					}

					$fieldLayoutField = new FieldLayoutFieldModel();
					$fieldLayoutField->fieldId = $field->id;
					$fieldLayoutField->required = $field->required;
					$fieldLayoutField->sortOrder = ++$sortOrder;

					$fieldLayoutFields[] = $fieldLayoutField;
				}

				$contentService->fieldContext        = $originalFieldContext;
				$contentService->fieldColumnPrefix   = $originalFieldColumnPrefix;
				$fieldsService->oldFieldColumnPrefix = $originalOldFieldColumnPrefix;

				$fieldLayoutTab = new FieldLayoutTabModel();
				$fieldLayoutTab->name = 'Content';
				$fieldLayoutTab->sortOrder = 1;
				$fieldLayoutTab->setFields($fieldLayoutFields);

				$fieldLayout = new FieldLayoutModel();
				$fieldLayout->type = NeoElementType::NeoBlock;
				$fieldLayout->setTabs(array($fieldLayoutTab));
				$fieldLayout->setFields($fieldLayoutFields);

				$fieldsService->saveLayout($fieldLayout);

				// Update the block type model & record with our new field layout ID
				$blockType->setFieldLayout($fieldLayout);
				$blockType->fieldLayoutId = $fieldLayout->id;
				$blockTypeRecord->fieldLayoutId = $fieldLayout->id;

				// Update the block type with the field layout ID
				$blockTypeRecord->save(false);

				if(!$isNewBlockType)
				{
					// Delete the old field layout
					$fieldsService->deleteLayoutById($oldBlockType->fieldLayoutId);
				}

				if($transaction !== null)
				{
					$transaction->commit();
				}
			}
			catch(\Exception $e)
			{
				if($transaction !== null)
				{
					$transaction->rollback();
				}

				throw $e;
			}

			return true;
		}

		return false;
	}

	public function deleteBlockType(NeoBlockTypeModel $blockType)
	{
		$transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;

		try
		{
			// First delete the blocks of this type
			$blockIds = craft()->db->createCommand()
				->select('id')
				->from('neoblocks')
				->where(array('typeId' => $blockType->id))
				->queryColumn();

			$this->deleteBlockById($blockIds);

			// Delete the field layout
			craft()->fields->deleteLayoutById($blockType->fieldLayoutId);

			// Delete the actual block type
			$affectedRows = craft()->db->createCommand()->delete('neoblocktypes', array('id' => $blockType->id));

			if($transaction !== null)
			{
				$transaction->commit();
			}

			return (bool) $affectedRows;
		}
		catch(\Exception $e)
		{
			if($transaction !== null)
			{
				$transaction->rollback();
			}

			throw $e;
		}
	}

	public function validateFieldSettings(NeoSettingsModel $settings)
	{
		$validates = true;

		$this->_uniqueBlockTypeAndFieldHandles = array();

		$uniqueAttributes = array('name', 'handle');
		$uniqueAttributeValues = array();

		foreach($settings->getBlockTypes() as $blockType)
		{
			if (!$this->validateBlockType($blockType, false))
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
					$blockType->addError($attribute, Craft::t('{attribute} "{value}" has already been taken.', array(
						'attribute' => $blockType->getAttributeLabel($attribute),
						'value'     => HtmlHelper::encode($value)
					)));

					$validates = false;
				}
			}
		}

		return $validates;
	}

	public function saveSettings(NeoSettingsModel $settings, $validate = true)
	{
		if(!$validate || $this->validateFieldSettings($settings))
		{
			$transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;

			try
			{
				$neoField = $settings->getField();

				// Create the content table first since the block type fields will need it
				$oldContentTable = $this->getContentTableName($neoField, true);
				$newContentTable = $this->getContentTableName($neoField);

				// Do we need to create/rename the content table?
				if(!craft()->db->tableExists($newContentTable))
				{
					if($oldContentTable && craft()->db->tableExists($oldContentTable))
					{
						MigrationHelper::renameTable($oldContentTable, $newContentTable);
					}
					else
					{
						$this->_createContentTable($newContentTable);
					}
				}

				// Delete the old block types first, in case there's a handle conflict with one of the new ones
				$oldBlockTypes = $this->getBlockTypesByFieldId($neoField->id);
				$oldBlockTypesById = array();

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

				// Save the new ones
				$sortOrder = 0;

				$originalContentTable = craft()->content->contentTable;
				craft()->content->contentTable = $newContentTable;

				foreach($settings->getBlockTypes() as $blockType)
				{
					$sortOrder++;
					$blockType->fieldId = $neoField->id;
					$blockType->sortOrder = $sortOrder;
					$this->saveBlockType($blockType, false);
				}

				craft()->content->contentTable = $originalContentTable;

				if($transaction !== null)
				{
					$transaction->commit();
				}

				// Update our cache of this field's block types
				$this->_blockTypesByFieldId[$settings->getField()->id] = $settings->getBlockTypes();

				return true;
			}
			catch(\Exception $e)
			{
				if($transaction !== null)
				{
					$transaction->rollback();
				}

				throw $e;
			}
		}

		return false;
	}

	public function deleteNeoField(FieldModel $neoField)
	{
		$transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;

		try
		{
			$originalContentTable = craft()->content->contentTable;
			$contentTable = $this->getContentTableName($neoField);
			craft()->content->contentTable = $contentTable;

			// Delete the block types
			$blockTypes = $this->getBlockTypesByFieldId($neoField->id);

			foreach($blockTypes as $blockType)
			{
				$this->deleteBlockType($blockType);
			}

			// Drop the content table
			craft()->db->createCommand()->dropTable($contentTable);

			craft()->content->contentTable = $originalContentTable;

			if($transaction !== null)
			{
				$transaction->commit();
			}

			return true;
		}
		catch(\Exception $e)
		{
			if($transaction !== null)
			{
				$transaction->rollback();
			}

			throw $e;
		}
	}

	public function getContentTableName(FieldModel $neoField, $useOldHandle = false)
	{
		$name = '';

		do
		{
			if($useOldHandle)
			{
				if(!$neoField->oldHandle)
				{
					return false;
				}

				$handle = $neoField->oldHandle;
			}
			else
			{
				$handle = $neoField->handle;
			}

			$name = '_' . StringHelper::toLowerCase($handle) . $name;
		}
		while ($neoField = $this->getParentNeoField($neoField));

		return 'neocontent' . $name;
	}

	public function getBlockById($blockId, $localeId = null)
	{
		return craft()->elements->getElementById($blockId, NeoElementType::NeoBlock, $localeId);
	}

	public function validateBlock(NeoBlockModel $block)
	{
		$block->clearErrors();

		$blockRecord = $this->_getBlockRecord($block);

		$blockRecord->fieldId   = $block->fieldId;
		$blockRecord->ownerId   = $block->ownerId;
		$blockRecord->typeId    = $block->typeId;
		$blockRecord->sortOrder = $block->sortOrder;

		$blockRecord->validate();
		$block->addErrors($blockRecord->getErrors());

		$originalFieldContext = craft()->content->fieldContext;
		craft()->content->fieldContext = 'global';

		if(!craft()->content->validateContent($block))
		{
			$block->addErrors($block->getContent()->getErrors());
		}

		craft()->content->fieldContext = $originalFieldContext;

		return !$block->hasErrors();
	}

	public function saveBlock(NeoBlockModel $block, $validate = true)
	{
		if(!$validate || $this->validateBlock($block))
		{
			$blockRecord = $this->_getBlockRecord($block);
			$isNewBlock = $blockRecord->isNewRecord();

			$blockRecord->fieldId     = $block->fieldId;
			$blockRecord->ownerId     = $block->ownerId;
			$blockRecord->ownerLocale = $block->ownerLocale;
			$blockRecord->typeId      = $block->typeId;
			$blockRecord->sortOrder   = $block->sortOrder;

			$transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;

			try
			{
				if(craft()->elements->saveElement($block, false))
				{
					if($isNewBlock)
					{
						$blockRecord->id = $block->id;
					}

					$blockRecord->save(false);

					if($transaction !== null)
					{
						$transaction->commit();
					}

					return true;
				}
			}
			catch(\Exception $e)
			{
				if($transaction !== null)
				{
					$transaction->rollback();
				}

				throw $e;
			}
		}

		return false;
	}

	public function deleteBlockById($blockIds)
	{
		if(!$blockIds)
		{
			return false;
		}

		if(!is_array($blockIds))
		{
			$blockIds = array($blockIds);
		}

		// Tell the browser to forget about these
		craft()->userSession->addJsResourceFlash('neo/js/NeoInput.js');

		foreach($blockIds as $blockId)
		{
			craft()->userSession->addJsFlash('NeoInput.forgetCollapsedBlockId(' . $blockId . ');');
		}

		// Pass this along to ElementsService for the heavy lifting
		return craft()->elements->deleteElementById($blockIds);
	}

	public function saveField(NeoFieldType $fieldType)
	{
		$owner = $fieldType->element;
		$field = $fieldType->model;
		$blocks = $owner->getContent()->getAttribute($field->handle);

		if($blocks === null)
		{
			return true;
		}

		if(!is_array($blocks))
		{
			$blocks = array();
		}

		$transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
		try
		{
			// First thing's first. Let's make sure that the blocks for this field/owner respect the field's translation
			// setting
			$this->_applyFieldTranslationSetting($owner, $field, $blocks);

			$blockIds = array();
			$collapsedBlockIds = array();

			foreach($blocks as $block)
			{
				$block->ownerId = $owner->id;
				$block->ownerLocale = ($field->translatable ? $owner->locale : null);

				$this->saveBlock($block, false);

				$blockIds[] = $block->id;

				// Tell the browser to collapse this block?
				if($block->collapsed)
				{
					$collapsedBlockIds[] = $block->id;
				}
			}

			// Get the IDs of blocks that are row deleted
			$deletedBlockConditions = array('and',
				'ownerId = :ownerId',
				'fieldId = :fieldId',
				array('not in', 'id', $blockIds)
			);

			$deletedBlockParams = array(
				':ownerId' => $owner->id,
				':fieldId' => $field->id
			);

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

			if($transaction !== null)
			{
				$transaction->commit();
			}
		}
		catch(\Exception $e)
		{
			if($transaction !== null)
			{
				$transaction->rollback();
			}

			throw $e;
		}

		// Tell the browser to collapse any new block IDs
		if($collapsedBlockIds)
		{
			craft()->userSession->addJsResourceFlash('neo/js/NeoInput.js');

			foreach($collapsedBlockIds as $blockId)
			{
				craft()->userSession->addJsFlash('NeoInput.rememberCollapsedBlockId(' . $blockId . ');');
			}
		}

		return true;
	}

	public function getParentNeoField(FieldModel $neoField)
	{
		if(!isset($this->_parentNeoFields) || !array_key_exists($neoField->id, $this->_parentNeoFields))
		{
			// Does this Neo field belong to another one?
			$parentNeoFieldId = craft()->db->createCommand()
				->select('fields.id')
				->from('fields fields')
				->join('neoblocktypes blocktypes', 'blocktypes.fieldId = fields.id')
				->join('fieldlayoutfields fieldlayoutfields', 'fieldlayoutfields.layoutId = blocktypes.fieldLayoutId')
				->where('fieldlayoutfields.fieldId = :neoFieldId', array(':neoFieldId' => $neoField->id))
				->queryScalar();

			if($parentNeoFieldId)
			{
				$this->_parentNeoFields[$neoField->id] = craft()->fields->getFieldById($parentNeoFieldId);
			}
			else
			{
				$this->_parentNeoFields[$neoField->id] = null;
			}
		}

		return $this->_parentNeoFields[$neoField->id];
	}

	private function _createBlockTypeQuery()
	{
		return craft()->db->createCommand()
			->select('id, fieldId, fieldLayoutId, name, handle, sortOrder')
			->from('neoblocktypes')
			->order('sortOrder');
	}

	private function _getBlockTypeRecord(NeoBlockTypeModel $blockType)
	{
		if(!$blockType->isNew())
		{
			$blockTypeId = $blockType->id;

			if(!isset($this->_blockTypeRecordsById) || !array_key_exists($blockTypeId, $this->_blockTypeRecordsById))
			{
				$this->_blockTypeRecordsById[$blockTypeId] = NeoBlockTypeRecord::model()->findById($blockTypeId);

				if(!$this->_blockTypeRecordsById[$blockTypeId])
				{
					throw new Exception(Craft::t('No block type exists with the ID “{id}”.', array('id' => $blockTypeId)));
				}
			}

			return $this->_blockTypeRecordsById[$blockTypeId];
		}

		return new NeoBlockTypeRecord();
	}

	private function _getBlockRecord(NeoBlockModel $block)
	{
		$blockId = $block->id;

		if($blockId)
		{
			if(!isset($this->_blockRecordsById) || !array_key_exists($blockId, $this->_blockRecordsById))
			{
				$this->_blockRecordsById[$blockId] = NeoBlockRecord::model()->with('element')->findById($blockId);

				if(!$this->_blockRecordsById[$blockId])
				{
					throw new Exception(Craft::t('No block exists with the ID “{id}”.', array('id' => $blockId)));
				}
			}

			return $this->_blockRecordsById[$blockId];
		}

		return new MatrixBlockRecord();
	}

	private function _createContentTable($name)
	{
		craft()->db->createCommand()->createTable($name, array(
			'elementId' => array('column' => ColumnType::Int, 'null' => false),
			'locale'    => array('column' => ColumnType::Locale, 'null' => false)
		));

		craft()->db->createCommand()->createIndex($name, 'elementId,locale', true);
		craft()->db->createCommand()->addForeignKey($name, 'elementId', 'elements', 'id', 'CASCADE', null);
		craft()->db->createCommand()->addForeignKey($name, 'locale', 'locales', 'locale', 'CASCADE', 'CASCADE');
	}

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
			$blocksInOtherLocales = array();

			$criteria = craft()->elements->getCriteria(NeoElementType::NeoBlock);
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
					$newBlockIds = array();

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
						->where(array('in', 'sourceId', array_keys($newBlockIds)))
						->queryAll();

					if($relations)
					{
						// Now duplicate each one for the other locales' new blocks
						$rows = array();

						foreach($relations as $relation)
						{
							$originalBlockId = $relation['sourceId'];

							// Just to be safe...
							if(isset($newBlockIds[$originalBlockId]))
							{
								foreach ($newBlockIds[$originalBlockId] as $localeId => $newBlockId)
								{
									$rows[] = array($relation['fieldId'], $newBlockId, $relation['sourceLocale'], $relation['targetId'], $relation['sortOrder']);
								}
							}
						}

						craft()->db->createCommand()->insertAll('relations', array('fieldId', 'sourceId', 'sourceLocale', 'targetId', 'sortOrder'), $rows);
					}
				}
				else
				{
					// Delete all of these blocks
					$blockIdsToDelete = array();

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
