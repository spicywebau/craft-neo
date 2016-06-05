<?php
namespace Craft;

class NeoFieldType extends BaseFieldType implements IEagerLoadingFieldType
{
	public function getName()
	{
		return "Neo";
	}

	public function defineContentAttribute()
	{
		return false;
	}

	public function getSettingsHtml()
	{
		if($this->_getNamespaceDepth() > 2)
		{
			return '<span class="error">' . Craft::t("Unable to nest Neo fields.") . '</span>';
		}

		$settings = $this->getSettings();
		$jsBlockTypes = [];
		$jsGroups = [];

		foreach($settings->getBlockTypes() as $blockType)
		{
			$fieldLayout = $blockType->getFieldLayout();
			$fieldLayoutTabs = $fieldLayout->getTabs();

			$jsFieldLayout = [];

			foreach($fieldLayoutTabs as $tab)
			{
				$tabFields = $tab->getFields();
				$jsTabFields = [];

				foreach($tabFields as $field)
				{
					$jsTabFields[] = [
						'id' => $field->fieldId,
						'required' => $field->required,
					];
				}

				$jsFieldLayout[] = [
					'name' => $tab->name,
					'fields' => $jsTabFields,
				];
			}

			$jsBlockTypes[] = [
				'id' => $blockType->id,
				'sortOrder' => $blockType->sortOrder,
				'name' => $blockType->name,
				'handle' => $blockType->handle,
				'maxBlocks' => $blockType->maxBlocks,
				'childBlocks' => $blockType->childBlocks,
				'topLevel' => (bool) $blockType->topLevel,
				'errors' => $blockType->getErrors(),
				'fieldLayout' => $jsFieldLayout,
				'fieldLayoutId' => $fieldLayout->id,
			];
		}

		foreach($settings->getGroups() as $group)
		{
			$jsGroups[] = [
				'id' => $group->id,
				'sortOrder' => $group->sortOrder,
				'name' => $group->name,
			];
		}

		craft()->templates->startJsBuffer();

		$fieldLayoutHtml = craft()->templates->render('_includes/fieldlayoutdesigner', [
			'fieldLayout' => false,
			'instructions' => '',
		]);

		craft()->templates->clearJsBuffer();

		$jsSettings = [
			'namespace' => craft()->templates->getNamespace(),
			'blockTypes' => $jsBlockTypes,
			'groups' => $jsGroups,
			'fieldLayoutHtml' => $fieldLayoutHtml,
		];


		craft()->templates->includeJsFile('https://cdnjs.cloudflare.com/ajax/libs/babel-polyfill/6.7.4/polyfill.min.js');
		craft()->templates->includeJsResource('neo/main.js');
		craft()->templates->includeJs('new Neo.Configurator(' . JsonHelper::encode($jsSettings) . ')');

		craft()->templates->includeTranslations(
			"Block Types",
			"Block type",
			"Group",
			"Settings",
			"Field Layout",
			"Reorder",
			"Name",
			"What this block type will be called in the CP.",
			"Handle",
			"How you'll refer to this block type in the templates.",
			"Max Blocks",
			"The maximum number of blocks of this type the field is allowed to have.",
			"All",
			"Child Blocks",
			"Which block types do you want to allow as children?",
			"Top Level",
			"Will this block type be allowed at the top level?",
			"Delete block type",
			"This can be left blank if you just want an unlabeled separator.",
			"Delete group"
		);

		return craft()->templates->render('neo/_fieldtype/settings', [
			'settings' => $this->getSettings(),
		]);
	}

	public function prepSettings($settings)
	{
		if($settings instanceof Neo_SettingsModel)
		{
			return $settings;
		}

		$neoSettings = new Neo_SettingsModel($this->model);
		$blockTypes = [];
		$groups = [];

		if(!empty($settings['blockTypes']))
		{
			foreach($settings['blockTypes'] as $blockTypeId => $blockTypeSettings)
			{
				$blockType = new Neo_BlockTypeModel();
				$blockType->id = $blockTypeId;
				$blockType->fieldId = $this->model->id;
				$blockType->name = $blockTypeSettings['name'];
				$blockType->handle = $blockTypeSettings['handle'];
				$blockType->maxBlocks = $blockTypeSettings['maxBlocks'];
				$blockType->sortOrder = $blockTypeSettings['sortOrder'];
				$blockType->childBlocks = $blockTypeSettings['childBlocks'];
				$blockType->topLevel = (bool) $blockTypeSettings['topLevel'];

				if(!empty($blockTypeSettings['fieldLayout']))
				{
					$fieldLayoutPost = $blockTypeSettings['fieldLayout'];
					$requiredFieldPost = empty($blockTypeSettings['requiredFields']) ? [] : $blockTypeSettings['requiredFields'];

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

	public function onAfterSave()
	{
		craft()->neo->saveSettings($this->getSettings(), false);
	}

	public function onBeforeDelete()
	{
		craft()->neo->deleteNeoField($this->model);
	}

	public function prepValue($value)
	{
		$criteria = craft()->neo->getCriteria();

		if(!empty($this->element->id))
		{
			$criteria->ownerId = $this->element->id;
		}
		else
		{
			$criteria->id = false;
		}

		$criteria->fieldId = $this->model->id;
		$criteria->locale = $this->element->locale;

		if(is_array($value) || $value === '')
		{
			$criteria->status = null;
			$criteria->localeEnabled = null;
			$criteria->limit = null;

			if(is_array($value))
			{
				$prevElement = null;

				foreach($value as $element)
				{
					if($prevElement)
					{
						$prevElement->setNext($element);
						$element->setPrev($prevElement);
					}

					$prevElement = $element;
				}

				foreach($value as $element)
				{
					$element->setAllElements($value);
				}

				$criteria->setMatchedElements($value);
				$criteria->setAllElements($value);
			}
			else if($value === '')
			{
				$criteria->setMatchedElements([]);
			}
		}

		return $criteria;
	}

	public function modifyElementsQuery(DbCommand $query, $value)
	{
		if($value == 'not :empty:')
		{
			$value = ':notempty:';
		}

		if($value == ':notempty:' || $value == ':empty:')
		{
			$alias = 'neoblocks_' . $this->model->handle;
			$operator = ($value == ':notempty:' ? '!=' : '=');

			$query->andWhere(
				"(select count({$alias}.id) from {{neoblocks}} {$alias} where {$alias}.ownerId = elements.id and {$alias}.fieldId = :fieldId) {$operator} 0",
				[':fieldId' => $this->model->id]
			);
		}

		return $value !== null ? false : null;
	}

	public function getInputHtml($name, $value)
	{
		if($this->_getNamespaceDepth() > 1)
		{
			return '<span class="error">' . Craft::t("Unable to nest Neo fields.") . '</span>';
		}

		$id = craft()->templates->formatInputId($name);
		$settings = $this->getSettings();

		if($value instanceof ElementCriteriaModel)
		{
			$value->limit = null;
			$value->status = null;
			$value->localeEnabled = null;
		}
		else if(!$value)
		{
			$value = [];
		}

		$html = craft()->templates->render('neo/_fieldtype/input', [
			'id' => $id,
			'name' => $name,
			'blockTypes' => $settings->getBlockTypes(),
			'blocks' => $value,
			'static' => false
		]);

		$this->_prepareInputHtml($id, $name, $settings, $value);

		return $html;
	}

	public function prepValueFromPost($data)
	{
		$blockTypes = craft()->neo->getBlockTypesByFieldId($this->model->id, 'handle');

		if(!is_array($data))
		{
			return [];
		}

		$oldBlocksById = [];

		if(!empty($this->element->id))
		{
			$ownerId = $this->element->id;
			$ids = [];

			foreach(array_keys($data) as $blockId)
			{
				if(is_numeric($blockId) && $blockId != 0)
				{
					$ids[] = $blockId;
				}
			}

			if($ids)
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

				foreach($oldBlocks as $oldBlock)
				{
					$oldBlocksById[$oldBlock->id] = $oldBlock;
				}
			}
		}
		else
		{
			$ownerId = null;
		}

		$blocks = [];

		foreach($data as $blockId => $blockData)
		{
			if(!isset($blockData['type']) || !isset($blockTypes[$blockData['type']]))
			{
				continue;
			}

			$blockType = $blockTypes[$blockData['type']];

			if(strncmp($blockId, 'new', 3) === 0 || !isset($oldBlocksById[$blockId]))
			{
				$block = new Neo_BlockModel();
				$block->fieldId = $this->model->id;
				$block->typeId = $blockType->id;
				$block->ownerId = $ownerId;
				$block->ownerLocale = $this->element->locale;
			}
			else
			{
				$block = $oldBlocksById[$blockId];
			}

			$block->setOwner($this->element);
			$block->enabled = (isset($blockData['enabled']) ? (bool) $blockData['enabled'] : true);
			$block->collapsed = (isset($blockData['collapsed']) ? (bool) $blockData['collapsed'] : false);
			$block->level = (isset($blockData['level']) ? intval($blockData['level']) : 0) + 1;

			$ownerContentPostLocation = $this->element->getContentPostLocation();

			if($ownerContentPostLocation)
			{
				$block->setContentPostLocation("{$ownerContentPostLocation}.{$this->model->handle}.{$blockId}.fields");
			}

			if(isset($blockData['fields']))
			{
				$block->setContentFromPost($blockData['fields']);
			}

			$blocks[] = $block;
		}

		return $blocks;
	}

	public function validate($blocks)
	{
		$errors = [];
		$blocksValidate = true;

		foreach($blocks as $block)
		{
			if (!craft()->neo->validateBlock($block))
			{
				$blocksValidate = false;
			}
		}

		if(!$blocksValidate)
		{
			$errors[] = Craft::t("Correct the errors listed above.");
		}

		$maxBlocks = $this->getSettings()->maxBlocks;

		if($maxBlocks && count($blocks) > $maxBlocks)
		{
			if($maxBlocks == 1)
			{
				$errors[] = Craft::t("There can’t be more than one block.");
			}
			else
			{
				$errors[] = Craft::t("There can’t be more than {max} blocks.", ['max' => $maxBlocks]);
			}
		}

		// TODO validate individual blocktype max blocks

		return $errors ? $errors : true;
	}

	public function getSearchKeywords($value)
	{
		$keywords = [];
		$contentService = craft()->content;

		foreach($value as $block)
		{
			$originalContentTable = $contentService->contentTable;
			$originalFieldColumnPrefix = $contentService->fieldColumnPrefix;
			$originalFieldContext = $contentService->fieldContext;

			$contentService->contentTable = $block->getContentTable();
			$contentService->fieldColumnPrefix = $block->getFieldColumnPrefix();
			$contentService->fieldContext = $block->getFieldContext();

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

			$contentService->contentTable = $originalContentTable;
			$contentService->fieldColumnPrefix = $originalFieldColumnPrefix;
			$contentService->fieldContext = $originalFieldContext;
		}

		return parent::getSearchKeywords($keywords);
	}

	public function onAfterElementSave()
	{
		craft()->neo->saveField($this);
	}

	public function getStaticHtml($value)
	{
		if($value)
		{
			$settings = $this->getSettings();
			$id = StringHelper::randomString();

			$html = craft()->templates->render('neo/_fieldtype/input', [
				'id' => $id,
				'name' => $id,
				'blockTypes' => $settings->getBlockTypes(),
				'blocks' => $value,
				'static' => true,
			]);

			$this->_prepareInputHtml($id, $id, $settings, $value, true);

			return $html;
		}
		else
		{
			return '<p class="light">' . Craft::t("No blocks.") . '</p>';
		}
	}

	public function getEagerLoadingMap($sourceElements)
	{
		$sourceElementIds = [];

		foreach($sourceElements as $sourceElement)
		{
			$sourceElementIds[] = $sourceElement->id;
		}

		// Return any relation data on these elements, defined with this field
		$map = craft()->db->createCommand()
			->select('ownerId as source, id as target')
			->from('neoblocks')
			->where(
				['and', 'fieldId=:fieldId', ['in', 'ownerId', $sourceElementIds]],
				[':fieldId' => $this->model->id]
			)
			// ->order('sortOrder') // TODO Need to join the structure elements table to get `lft` for ordering
			->queryAll();

		return [
			'elementType' => Neo_ElementType::NeoBlock,
			'map' => $map,
			'criteria' => array('fieldId' => $this->model->id),
		];
	}

	protected function getSettingsModel()
	{
		return new Neo_SettingsModel($this->model);
	}

	private function _getNamespaceDepth()
	{
		$namespace = craft()->templates->getNamespace();

		return preg_match_all('/\\bfields\\b/', $namespace);
	}

	private function _prepareInputHtml($id, $name, $settings, $value, $static = false)
	{
		$blockTypeInfo = [];
		foreach($settings->getBlockTypes() as $blockType)
		{
			$fieldLayout = $blockType->getFieldLayout();
			$blockTypeInfo[] = [
				'id' => $blockType->id,
				'fieldLayoutId' => $fieldLayout->id,
				'sortOrder' => $blockType->sortOrder,
				'handle' => $blockType->handle,
				'name' => Craft::t($blockType->name),
				'maxBlocks' => $blockType->maxBlocks,
				'childBlocks' => $blockType->childBlocks,
				'topLevel' => (bool) $blockType->topLevel,
				'tabs' => $this->_getBlockTypeHtml($blockType, null, $name, $static),
			];
		}

		$groupInfo = [];
		foreach($settings->getGroups() as $group)
		{
			$groupInfo[] = [
				'sortOrder' => $group->sortOrder,
				'name' => $group->name,
			];
		}

		$blockInfo = [];
		$sortOrder = 0;

		foreach($value as $block)
		{
			$blockInfo[] = [
				'id' => $block->id,
				'blockType' => $block->getType()->handle,
				'sortOrder' => $sortOrder++,
				'collapsed' => (bool) $block->collapsed,
				'enabled' => (bool) $block->enabled,
				'level' => intval($block->level) - 1,
				'tabs' => $this->_getBlockHtml($block, $name, $static),
			];
		}

		$jsSettings = [
			'namespace' => craft()->templates->namespaceInputName($name),
			'blockTypes' => $blockTypeInfo,
			'groups' => $groupInfo,
			'inputId' => craft()->templates->namespaceInputId($id),
			'maxBlocks' => $settings->maxBlocks,
			'blocks' => $blockInfo,
			'static' => $static,
		];

		craft()->templates->includeJsFile('https://cdnjs.cloudflare.com/ajax/libs/babel-polyfill/6.7.4/polyfill.min.js');
		craft()->templates->includeJsResource('neo/main.js');
		craft()->templates->includeJs('new Neo.Input(' . JsonHelper::encode($jsSettings) . ')');

		craft()->templates->includeTranslations(
			"Select",
			"Actions",
			"Add a block",
			"Add block above",
			"Are you sure you want to delete the selected blocks?",
			"Expand",
			"Collapse",
			"Enable",
			"Disable",
			"Disabled",
			"Delete",
			"Are you sure you want to delete the selected blocks?",
			"Reorder"
		);
	}

	private function _getBlockTypeHtml(Neo_BlockTypeModel $blockType, Neo_BlockModel $block = null, $namespace = null, $static = false)
	{
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

			craft()->templates->startJsBuffer();

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

	private function _getBlockHtml(Neo_BlockModel $block, $namespace = null, $static = false)
	{
		return $this->_getBlockTypeHtml($block->getType(), $block, $namespace, $static);
	}
}
