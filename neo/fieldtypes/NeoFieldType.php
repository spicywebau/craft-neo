<?php
namespace Craft;

/**
 * Class NeoFieldType
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2014, Pixel & Tonic, Inc.
 * @license   http://craftcms.com/license Craft License Agreement
 * @see       http://craftcms.com
 * @package   craft.app.fieldtypes
 * @since     1.3
 */
class NeoFieldType extends BaseFieldType implements IEagerLoadingFieldType
{
	// Public Methods
	// =========================================================================

	/**
	 * @inheritDoc IComponentType::getName()
	 *
	 * @return string
	 */
	public function getName()
	{
		return Craft::t('Neo');
	}

	/**
	 * @inheritDoc IFieldType::defineContentAttribute()
	 *
	 * @return mixed
	 */
	public function defineContentAttribute()
	{
		return false;
	}

	/**
	 * @inheritDoc ISavableComponentType::getSettingsHtml()
	 *
	 * @return string|null
	 */
	public function getSettingsHtml()
	{
		if($this->_getNamespaceDepth() > 2)
		{
			return '<span class="error">' . Craft::t("Unable to nest Neo fields.") . '</span>';
		}

		$settings = $this->getSettings();
		$jsBlockTypes = array();
		$jsGroups = array();

		foreach($settings->getBlockTypes() as $blockType)
		{
			$fieldLayout = $blockType->getFieldLayout();
			$fieldLayoutTabs = $fieldLayout->getTabs();

			$jsFieldLayout = array();

			foreach($fieldLayoutTabs as $tab)
			{
				$tabFields = $tab->getFields();
				$jsTabFields = array();

				foreach($tabFields as $field)
				{
					$jsTabFields[] = array(
						'id' => $field->fieldId,
						'required' => $field->required,
					);
				}

				$jsFieldLayout[] = array(
					'name' => $tab->name,
					'fields' => $jsTabFields,
				);
			}

			$jsBlockTypes[] = array(
				'id' => $blockType->id,
				'sortOrder' => $blockType->sortOrder,
				'name' => $blockType->name,
				'handle' => $blockType->handle,
				'maxBlocks' => $blockType->maxBlocks,
				'errors' => $blockType->getErrors(),
				'fieldLayout' => $jsFieldLayout,
			);
		}

		foreach($settings->getGroups() as $group)
		{
			$jsGroups[] = array(
				'id' => $group->id,
				'sortOrder' => $group->sortOrder,
				'name' => $group->name,
			);
		}

		craft()->templates->startJsBuffer();

		$fieldLayoutHtml = craft()->templates->render('_includes/fieldlayoutdesigner', array(
			'fieldLayout' => false,
			'instructions' => '',
		));

		craft()->templates->clearJsBuffer();

		$jsSettings = array(
			'namespace' => craft()->templates->getNamespace(),
			'blockTypes' => $jsBlockTypes,
			'groups' => $jsGroups,
			'fieldLayoutHtml' => $fieldLayoutHtml,
		);

		craft()->templates->includeJsResource('neo/main.js');
		craft()->templates->includeJs('new Neo.Configurator(' . JsonHelper::encode($jsSettings) . ')');

		return craft()->templates->render('neo/_fieldtype/settings', array(
			'settings' => $this->getSettings(),
		));
	}

	/**
	 * @inheritDoc ISavableComponentType::prepSettings()
	 *
	 * @param array $settings
	 *
	 * @return array
	 */
	public function prepSettings($settings)
	{
		if ($settings instanceof Neo_SettingsModel)
		{
			return $settings;
		}

		$neoSettings = new Neo_SettingsModel($this->model);
		$blockTypes = array();
		$groups = array();

		if(!empty($settings['blockTypes']))
		{
			foreach($settings['blockTypes'] as $blockTypeId => $blockTypeSettings)
			{
				$blockType = new Neo_BlockTypeModel();
				$blockType->id        = $blockTypeId;
				$blockType->fieldId   = $this->model->id;
				$blockType->name      = $blockTypeSettings['name'];
				$blockType->handle    = $blockTypeSettings['handle'];
				$blockType->maxBlocks = $blockTypeSettings['maxBlocks'];
				$blockType->sortOrder = $blockTypeSettings['sortOrder'];

				if(!empty($blockTypeSettings['fieldLayout']))
				{
					$fieldLayoutPost = $blockTypeSettings['fieldLayout'];
					$requiredFieldPost = empty($blockTypeSettings['requiredFields']) ? array() : $blockTypeSettings['requiredFields'];

					$fieldLayout = craft()->fields->assembleLayout($fieldLayoutPost, $requiredFieldPost);
					$fieldLayout->type = Neo_ElementType::NeoBlock;
					$blockType->setFieldLayout($fieldLayout);
				}

				$blockTypes[] = $blockType;
			}
		}

		if(!empty($settings['groups']))
		{
			$names = $settings['groups']['name'];
			$sortOrders = $settings['groups']['sortOrder'];

			for($i = 0; $i < count($names); $i++)
			{
				$group = new Neo_GroupModel();
				$group->name = $names[$i];
				$group->sortOrder = $sortOrders[$i];

				$groups[] = $group;
			}
		}

		$neoSettings->setBlockTypes($blockTypes);
		$neoSettings->setGroups($groups);

		if(!empty($settings['maxBlocks']))
		{
			$neoSettings->maxBlocks = $settings['maxBlocks'];
		}

		return $neoSettings;
	}

	/**
	 * @inheritDoc IFieldType::onAfterSave()
	 *
	 * @return null
	 */
	public function onAfterSave()
	{
		craft()->neo->saveSettings($this->getSettings(), false);
	}

	/**
	 * @inheritDoc IFieldType::onBeforeDelete()
	 *
	 * @return null
	 */
	public function onBeforeDelete()
	{
		craft()->neo->deleteNeoField($this->model);
	}

	/**
	 * @inheritDoc IFieldType::prepValue()
	 *
	 * @param mixed $value
	 *
	 * @return ElementCriteriaModel
	 */
	public function prepValue($value)
	{
		$criteria = craft()->elements->getCriteria(Neo_ElementType::NeoBlock);

		// Existing element?
		if (!empty($this->element->id))
		{
			$criteria->ownerId = $this->element->id;
		}
		else
		{
			$criteria->id = false;
		}

		$criteria->fieldId = $this->model->id;
		$criteria->locale = $this->element->locale;

		// Set the initially matched elements if $value is already set, which is the case if there was a validation
		// error or we're loading an entry revision.
		if (is_array($value) || $value === '')
		{
			$criteria->status = null;
			$criteria->localeEnabled = null;
			$criteria->limit = null;

			if (is_array($value))
			{
				$prevElement = null;

				foreach ($value as $element)
				{
					if ($prevElement)
					{
						$prevElement->setNext($element);
						$element->setPrev($prevElement);
					}

					$prevElement = $element;
				}

				$criteria->setMatchedElements($value);
			}
			else if ($value === '')
			{
				// Means there were no blocks
				$criteria->setMatchedElements(array());
			}
		}

		return $criteria;
	}

	/**
	 * @inheritDoc IFieldType::modifyElementsQuery()
	 *
	 * @param DbCommand $query
	 * @param mixed     $value
	 *
	 * @return null|false
	 */
	public function modifyElementsQuery(DbCommand $query, $value)
	{
		if ($value == 'not :empty:')
		{
			$value = ':notempty:';
		}

		if ($value == ':notempty:' || $value == ':empty:')
		{
			$alias = 'neoblocks_'.$this->model->handle;
			$operator = ($value == ':notempty:' ? '!=' : '=');

			$query->andWhere(
				"(select count({$alias}.id) from {{neoblocks}} {$alias} where {$alias}.ownerId = elements.id and {$alias}.fieldId = :fieldId) {$operator} 0",
				array(':fieldId' => $this->model->id)
			);
		}
		else if ($value !== null)
		{
			return false;
		}
	}

	/**
	 * @inheritDoc IFieldType::getInputHtml()
	 *
	 * @param string $name
	 * @param mixed  $value
	 *
	 * @return string
	 */
	public function getInputHtml($name, $value)
	{
		if($this->_getNamespaceDepth() > 1)
		{
			return '<span class="error">' . Craft::t("Unable to nest Neo fields.") . '</span>';
		}

		$id = craft()->templates->formatInputId($name);
		$settings = $this->getSettings();

		if ($value instanceof ElementCriteriaModel)
		{
			$value->limit = null;
			$value->status = null;
			$value->localeEnabled = null;
		}
		else if(!$value)
		{
			$value = array();
		}


		$html = craft()->templates->render('neo/_fieldtype/input', array(
			'id'         => $id,
			'name'       => $name,
			'blockTypes' => $settings->getBlockTypes(),
			'blocks'     => $value,
			'static'     => false
		));

		$blockTypeInfo = array();
		foreach($settings->getBlockTypes() as $blockType)
		{
			$blockTypeInfo[] = array(
				'id'        => $blockType->id,
				'sortOrder' => $blockType->sortOrder,
				'handle'    => $blockType->handle,
				'name'      => Craft::t($blockType->name),
				'maxBlocks' => $blockType->maxBlocks,
				'tabs'      => $this->_getBlockTypeHtml($blockType, null, $name),
			);
		}

		$groupInfo = array();
		foreach($settings->getGroups() as $group)
		{
			$groupInfo[] = array(
				'sortOrder' => $group->sortOrder,
				'name' => $group->name,
			);
		}

		$blockInfo = array();
		foreach($value as $block)
		{
			$blockInfo[] = array(
				'id'        => $block->id,
				'blockType' => $block->getType()->handle,
				'sortOrder' => $block->sortOrder,
				'collapsed' => (bool) $block->collapsed,
				'enabled'   => (bool) $block->enabled,
				'tabs'      => $this->_getBlockHtml($block, $name),
			);
		}

		$jsSettings = array(
			'namespace'  => craft()->templates->namespaceInputName($name),
			'blockTypes' => $blockTypeInfo,
			'groups'     => $groupInfo,
			'inputId'    => craft()->templates->namespaceInputId($id),
			'maxBlocks'  => $settings->maxBlocks,
			'blocks'     => $blockInfo,
		);

		craft()->templates->includeJsResource('neo/main.js');
		craft()->templates->includeJs('new Neo.Input(' . JsonHelper::encode($jsSettings) . ')');

		craft()->templates->includeTranslations(
			'Actions',
			'Add a block',
			'Add {type} above',
			'Are you sure you want to delete the selected blocks?',
			'Collapse',
			'Disable',
			'Disabled',
			'Enable',
			'Expand'
		);

		return $html;
	}

	/**
	 * @inheritDoc IFieldType::prepValueFromPost()
	 *
	 * @param mixed $data
	 *
	 * @return Neo_BlockModel[]
	 */
	public function prepValueFromPost($data)
	{
		// Get the possible block types for this field
		$blockTypes = craft()->neo->getBlockTypesByFieldId($this->model->id, 'handle');

		if (!is_array($data))
		{
			return array();
		}

		$oldBlocksById = array();

		// Get the old blocks that are still around
		if (!empty($this->element->id))
		{
			$ownerId = $this->element->id;

			$ids = array();

			foreach (array_keys($data) as $blockId)
			{
				if (is_numeric($blockId) && $blockId != 0)
				{
					$ids[] = $blockId;
				}
			}

			if ($ids)
			{
				$criteria = craft()->elements->getCriteria(Neo_ElementType::NeoBlock);
				$criteria->fieldId = $this->model->id;
				$criteria->ownerId = $ownerId;
				$criteria->id = $ids;
				$criteria->limit = null;
				$criteria->status = null;
				$criteria->localeEnabled = null;
				$criteria->locale = $this->element->locale;
				$oldBlocks = $criteria->find();

				// Index them by ID
				foreach ($oldBlocks as $oldBlock)
				{
					$oldBlocksById[$oldBlock->id] = $oldBlock;
				}
			}
		}
		else
		{
			$ownerId = null;
		}

		$blocks = array();
		$sortOrder = 0;

		foreach ($data as $blockId => $blockData)
		{
			if (!isset($blockData['type']) || !isset($blockTypes[$blockData['type']]))
			{
				continue;
			}

			$blockType = $blockTypes[$blockData['type']];

			// Is this new? (Or has it been deleted?)
			if (strncmp($blockId, 'new', 3) === 0 || !isset($oldBlocksById[$blockId]))
			{
				$block = new Neo_BlockModel();
				$block->fieldId = $this->model->id;
				$block->typeId  = $blockType->id;
				$block->ownerId = $ownerId;
				$block->locale  = $this->element->locale;
			}
			else
			{
				$block = $oldBlocksById[$blockId];
			}

			$block->setOwner($this->element);
			$block->enabled = (isset($blockData['enabled']) ? (bool) $blockData['enabled'] : true);
			$block->collapsed = (isset($blockData['collapsed']) ? (bool) $blockData['collapsed'] : false);

			// Set the content post location on the block if we can
			$ownerContentPostLocation = $this->element->getContentPostLocation();

			if ($ownerContentPostLocation)
			{
				$block->setContentPostLocation("{$ownerContentPostLocation}.{$this->model->handle}.{$blockId}.fields");
			}

			if (isset($blockData['fields']))
			{
				$block->setContentFromPost($blockData['fields']);
			}

			$sortOrder++;
			$block->sortOrder = $sortOrder;

			$blocks[] = $block;
		}

		return $blocks;
	}

	/**
	 * @inheritDoc IFieldType::validate()
	 *
	 * @param array $blocks
	 *
	 * @return true|string|array
	 */
	public function validate($blocks)
	{
		$errors = array();
		$blocksValidate = true;

		foreach ($blocks as $block)
		{
			if (!craft()->neo->validateBlock($block))
			{
				$blocksValidate = false;
			}
		}

		if (!$blocksValidate)
		{
			$errors[] = Craft::t('Correct the errors listed above.');
		}

		$maxBlocks = $this->getSettings()->maxBlocks;

		if ($maxBlocks && count($blocks) > $maxBlocks)
		{
			if ($maxBlocks == 1)
			{
				$errors[] = Craft::t('There can’t be more than one block.');
			}
			else
			{
				$errors[] = Craft::t('There can’t be more than {max} blocks.', array('max' => $maxBlocks));
			}
		}

		// TODO validate individual blocktype max blocks

		if ($errors)
		{
			return $errors;
		}
		else
		{
			return true;
		}
	}

	/**
	 * @inheritDoc IFieldType::getSearchKeywords()
	 *
	 * @param mixed $value
	 *
	 * @return string
	 */
	public function getSearchKeywords($value)
	{
		$keywords = array();
		$contentService = craft()->content;

		foreach ($value as $block)
		{
			$originalContentTable      = $contentService->contentTable;
			$originalFieldColumnPrefix = $contentService->fieldColumnPrefix;
			$originalFieldContext      = $contentService->fieldContext;

			$contentService->contentTable      = $block->getContentTable();
			$contentService->fieldColumnPrefix = $block->getFieldColumnPrefix();
			$contentService->fieldContext      = $block->getFieldContext();

			foreach (craft()->fields->getAllFields() as $field)
			{
				$fieldType = $field->getFieldType();

				if ($fieldType)
				{
					$fieldType->element = $block;
					$handle = $field->handle;
					$keywords[] = $fieldType->getSearchKeywords($block->getFieldValue($handle));
				}
			}

			$contentService->contentTable      = $originalContentTable;
			$contentService->fieldColumnPrefix = $originalFieldColumnPrefix;
			$contentService->fieldContext      = $originalFieldContext;
		}

		return parent::getSearchKeywords($keywords);
	}

	/**
	 * @inheritDoc IFieldType::onAfterElementSave()
	 *
	 * @return null
	 */
	public function onAfterElementSave()
	{
		craft()->neo->saveField($this);
	}

	/**
	 * @inheritDoc IFieldType::getStaticHtml()
	 *
	 * @param mixed $value
	 *
	 * @return string
	 */
	public function getStaticHtml($value)
	{
		if ($value)
		{
			$settings = $this->getSettings();
			$id = StringHelper::randomString();

			return craft()->templates->render('neo/_fieldtype/input', array(
				'id' => $id,
				'name' => $id,
				'blockTypes' => $settings->getBlockTypes(),
				'blocks' => $value,
				'static' => true
			));
		}
		else
		{
			return '<p class="light">'.Craft::t('No blocks.').'</p>';
		}
	}

	public function getEagerLoadingMap($sourceElements)
	{
		// Get the source element IDs
		$sourceElementIds = array();

		foreach($sourceElements as $sourceElement)
		{
			$sourceElementIds[] = $sourceElement->id;
		}

		// Return any relation data on these elements, defined with this field
		$map = craft()->db->createCommand()
			->select('ownerId as source, id as target')
			->from('neo_blocks')
			->where(
				array('and', 'fieldId=:fieldId', array('in', 'ownerId', $sourceElementIds)),
				array(':fieldId' => $this->model->id)
			)
			->order('sortOrder')
			->queryAll();

		return array(
			'elementType' => Neo_ElementType::NeoBlock,
			'map' => $map,
			'criteria' => array('fieldId' => $this->model->id),
		);
	}

	// Protected Methods
	// =========================================================================

	/**
	 * @inheritDoc BaseSavableComponentType::getSettingsModel()
	 *
	 * @return BaseModel
	 */
	protected function getSettingsModel()
	{
		return new Neo_SettingsModel($this->model);
	}

	// Private Methods
	// =========================================================================

	private function _getNamespaceDepth()
	{
		$namespace = craft()->templates->getNamespace();

		return preg_match_all('/\\bfields\\b/', $namespace);
	}

	private function _getBlockTypeHtml(Neo_BlockTypeModel $blockType, Neo_BlockModel $block = null, $namespace = null)
	{
		$oldNamespace = craft()->templates->getNamespace();
		$newNamespace = craft()->templates->namespaceInputName($namespace . '[__NEOBLOCK__][fields]', $oldNamespace);
		craft()->templates->setNamespace($newNamespace);

		$tabsHtml = array();

		$fieldLayout = $blockType->getFieldLayout();
		$fieldLayoutTabs = $fieldLayout->getTabs();

		foreach($fieldLayoutTabs as $fieldLayoutTab)
		{
			$tabHtml = array(
				'name' => Craft::t($fieldLayoutTab->name),
				'bodyHtml' => '',
				'footHtml' => '',
				'errors' => array(),
			);

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

			craft()->templates->startJsBuffer();

			$tabHtml['bodyHtml'] = craft()->templates->namespaceInputs(craft()->templates->render('_includes/fields', array(
				'namespace' => null,
				'element'   => $block,
				'fields'    => $fieldLayoutFields,
			)));

			// Reset $_isFresh's
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

	private function _getBlockHtml(Neo_BlockModel $block, $namespace = null)
	{
		return $this->_getBlockTypeHtml($block->getType(), $block, $namespace);
	}
}
