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
class NeoFieldType extends BaseFieldType
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
		$settings = $this->getSettings();
		$jsBlockTypes = array();

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
					$jsTabFields[] = $field->fieldId;
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

		/*craft()->templates->startJsBuffer();

		$fieldLayoutHtml = craft()->templates->render('_includes/fieldlayoutdesigner', array(
			'fieldLayout' => false,
			'instructions' => '',
		));

		craft()->templates->clearJsBuffer();*/

		$jsSettings = array(
			'namespace' => craft()->templates->getNamespace(),
			'blockTypes' => $jsBlockTypes,
			//'fieldLayoutHtml' => $fieldLayoutHtml,
		);

		craft()->templates->includeJsResource('neo/dist/main.js');
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

		$neoSettings->setBlockTypes($blockTypes);

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
		$id = craft()->templates->formatInputId($name);
		$settings = $this->getSettings();

		if ($value instanceof ElementCriteriaModel)
		{
			$value->limit = null;
			$value->status = null;
			$value->localeEnabled = null;
		}

		$html = craft()->templates->render('neo/_fieldtype/input', array(
			'id' => $id,
			'name' => $name,
			'blockTypes' => $settings->getBlockTypes(),
			'blocks' => $value,
			'static' => false
		));

		// Get the block types data
		$blockTypeInfo = $this->_getBlockTypeInfoForInput($name);

		$jsSettings = array(
			'namespace' => craft()->templates->namespaceInputName($name),
			'blockTypes' => $blockTypeInfo,
			'inputId' => craft()->templates->namespaceInputId($id),
			'maxBlocks' => $settings->maxBlocks
		);

		craft()->templates->includeJsResource('neo/dist/main.js');
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

				// Preserve the collapsed state, which the browser can't remember on its own for new blocks
				$block->collapsed = !empty($blockData['collapsed']);
			}
			else
			{
				$block = $oldBlocksById[$blockId];
			}

			$block->setOwner($this->element);
			$block->enabled = (isset($blockData['enabled']) ? (bool) $blockData['enabled'] : true);

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

	/**
	 * Returns info about each block type and their field types for the Neo field input.
	 *
	 * @param string $name
	 *
	 * @return array
	 */
	private function _getBlockTypeInfoForInput($name)
	{
		$jsBlockTypes = array();

		// Set a temporary namespace for these
		$originalNamespace = craft()->templates->getNamespace();
		$namespace = craft()->templates->namespaceInputName($name.'[__BLOCK__][fields]', $originalNamespace);
		craft()->templates->setNamespace($namespace);

		$settings = $this->getSettings();
		$blockTypes = $settings->getBlockTypes();

		foreach($blockTypes as $blockType)
		{
			$fieldLayout = $blockType->getFieldLayout();
			$fieldLayoutTabs = $fieldLayout->getTabs();



			// Create a fake Neo Block model so the field types have a way to get at the owner element, if there is one
			$block = new Neo_BlockModel();
			$block->fieldId = $this->model->id;
			$block->typeId = $blockType->id;

			if($this->element)
			{
				$block->setOwner($this->element);
				$block->locale = $this->element->locale;
			}

			$jsTabs = array();

			foreach($fieldLayoutTabs as $fieldLayoutTab)
			{
				$jsTab = array(
					'name' => Craft::t($fieldLayoutTab->name),
					'bodyHtml' => '',
					'footHtml' => '',
				);

				$fieldLayoutFields = $fieldLayoutTab->getFields();

				foreach($fieldLayoutFields as $fieldLayoutField)
				{
					$fieldType = $fieldLayoutField->getField()->getFieldType();

					if ($fieldType)
					{
						$fieldType->element = $block;
						$fieldType->setIsFresh(true);
					}
				}

				craft()->templates->startJsBuffer();

				$jsTab['bodyHtml'] = craft()->templates->namespaceInputs(craft()->templates->render('_includes/fields', array(
					'namespace' => null,
					'fields'    => $fieldLayoutFields
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

				$jsTab['footHtml'] = craft()->templates->clearJsBuffer();

				$jsTabs[] = $jsTab;
			}

			$jsBlockTypes[] = array(
				'handle'    => $blockType->handle,
				'name'      => Craft::t($blockType->name),
				'maxBlocks' => $blockType->maxBlocks,
				'tabs'      => $jsTabs,
			);
		}

		craft()->templates->setNamespace($originalNamespace);

		return $jsBlockTypes;
	}
}
