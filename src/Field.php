<?php
namespace benf\neo;

use Craft;
use craft\base\Field as BaseField;
use craft\helpers\ArrayHelper;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use craft\validators\ArrayValidator;

use benf\neo\Plugin as Neo;
use benf\neo\models\BlockType;
use benf\neo\models\BlockTypeGroup;
use benf\neo\elements\Block;
use benf\neo\elements\db\BlockQuery;
use benf\neo\assets\FieldAsset;

class Field extends BaseField
{
	public static function displayName(): string
	{
		return Craft::t('neo', "Neo");
	}

	public static function hasContentColumn(): bool
	{
		return false;
	}

	public static function supportedTranslationMethods(): array
	{
		return [
			self::TRANSLATION_METHOD_SITE,
		];
	}

	public $localizeBlocks = false;

	private $_blockTypes;
	private $_blockTypeGroups;

	public function rules(): array
	{
		$rules = parent::rules();
		$rules[] = [['minBlocks', 'maxBlocks'], 'integer', 'min' => 0];

		return $rules;
	}

	public $minBlocks;
	public $maxBlocks;

	public function getBlockTypes(): array
	{
		$blockTypes = $this->_blockTypes;

		if ($blockTypes === null)
		{
			if ($this->getIsNew())
			{
				$blockTypes = [];
			}
			else
			{
				$blockTypes = Neo::$plugin->blockTypes->getByFieldId($this->id);
				$this->_blockTypes = $blockTypes;
			}
		}

		return $blockTypes;
	}

	public function setBlockTypes($blockTypes)
	{
		$newBlockTypes = [];
		
		foreach ($blockTypes as $blockTypeId => $blockType)
		{
			$newBlockType = $blockType;

			if (!($blockType instanceof BlockType))
			{
				$newBlockType = new BlockType();
				$newBlockType->id = $blockTypeId;
				$newBlockType->fieldId = $this->id;
				$newBlockType->name = $blockType['name'];
				$newBlockType->handle = $blockType['handle'];
				$newBlockType->maxBlocks = (int)$blockType['maxBlocks'];
				$newBlockType->maxChildBlocks = (int)$blockType['maxChildBlocks'];
				$newBlockType->topLevel = (bool)$blockType['topLevel'];
				$newBlockType->childBlocks = $blockType['childBlocks'];
				$newBlockType->sortOrder = (int)$blockType['sortOrder'];

				if (!empty($blockType['fieldLayout']))
				{
					$fieldLayoutPost = $blockType['fieldLayout'];
					$requiredFieldPost = empty($blockType['requiredFields']) ? [] : $blockType['requiredFields'];

					// Add support for blank tabs
					foreach ($fieldLayoutPost as $tabName => $fieldIds)
					{
						$fieldLayoutPost[$tabName] = is_array($fieldIds) ? $fieldIds : [];
					}

					$fieldLayout = Craft::$app->getFields()->assembleLayout($fieldLayoutPost, $requiredFieldPost);
					$fieldLayout->type = Block::class;
					$newBlockType->setFieldLayout($fieldLayout);
				}
			}
			
			$newBlockTypes[] = $newBlockType;
		}

		$this->_blockTypes = $newBlockTypes;
	}

	public function getGroups(): array
	{
		$blockTypeGroups = $this->_blockTypeGroups;

		if ($blockTypeGroups === null)
		{
			if ($this->getIsNew())
			{
				$blockTypeGroups = [];
			}
			else
			{
				$blockTypeGroups = Neo::$plugin->blockTypes->getGroupsByFieldId($this->id);
				$this->_blockTypeGroups = $blockTypeGroups;
			}
		}

		return $blockTypeGroups;
	}

	public function setGroups($blockTypeGroups)
	{
		$newBlockTypeGroups = [];
		
		foreach ($blockTypeGroups as $blockTypeGroup)
		{
			$newBlockTypeGroup = $blockTypeGroup;

			if (!($blockTypeGroup instanceof BlockTypeGroup))
			{
				$newBlockTypeGroup = new BlockTypeGroup();
				$newBlockTypeGroup->fieldId = $this->id;
				$newBlockTypeGroup->name = $blockTypeGroup['name'];
				$newBlockTypeGroup->sortOrder = (int)$blockTypeGroup['sortOrder'];
			}
			
			$newBlockTypeGroups[] = $newBlockTypeGroup;
		}

		$this->_blockTypeGroups = $newBlockTypeGroups;
	}

	public function validate($attributeNames = null, $clearErrors = true): bool
	{
		$validates = parent::validate($attributeNames, $clearErrors);
		$validates = $validates && Neo::$plugin->fields->validate($this);

		return $validates;
	}

	public function getSettingsHtml()
	{
		$viewService = Craft::$app->getView();

		$html = '';

		// Disable creating Neo fields inside Matrix, SuperTable and potentially other field-grouping field types.
		if ($this->_getNamespaceDepth() >= 1)
		{
			$html = $this->_getNestingErrorHtml();
		}
		else
		{
			$viewService->registerAssetBundle(FieldAsset::class);
			$viewService->registerJs(FieldAsset::createSettingsJs($this));

			$html = $viewService->renderTemplate('neo/settings', ['neoField' => $this]);
		}

		return $html;
	}

	public function getInputHtml($value, ElementInterface $element = null): string
	{
		return $this->_getInputHtml($value, $element);
	}

	public function getStaticHtml($value, ElementInterface $element): string
	{
		return $this->_getInputHtml($value, $element, true);
	}

	public function normalizeValue($value, ElementInterface $element = null)
	{
		$query = null;

		if ($value instanceof ElementQueryInterface)
		{
			$query = $value;
		}
		else
		{
			$query = Block::find();

			// Existing element?
			if ($element && $element->id)
			{
				$query->ownerId($element->id);
			}
			else
			{
				$query->id(false);
			}

			$query
				->fieldId($this->id)
				->siteId($element->siteId ?? null);

			// Set the initially matched elements if $value is already set, which is the case if there was a validation
			// error or we're loading an entry revision.
			if (is_array($value) || $value === '')
			{
				$query->status = null;
				$query->enabledForSite = false;
				$query->limit = null;
				$query->setCachedResult($this->_createBlocksFromSerializedData($value, $element));
			}
		}

		return $query;
	}

	public function serializeValue($value, ElementInterface $element = null)
	{
		$serialized = [];
		$new = 0;

		foreach ($value->all() as $block)
		{
			$blockId = $block->id ?? 'new' . ++$new;
			$serialized[$blockId] = [
				'type' => $block->getType()->handle,
				'enabled' => $block->enabled,
				'collapsed' => $block->getCollapsed(),
				'level' => $block->level,
				'fields' => $block->getSerializedFieldValues(),
			];
		}

		return $serialized;
	}

	public function modifyElementsQuery(ElementQueryInterface $query, $value)
	{
		if ($value === 'not :empty:')
		{
			$value = ':notempty:';
		}

		if ($value === ':notempty:' || $value === ':empty:')
		{
			$alias = 'neoblocks_' . $this->handle;
			$operator = $value === ':notempty:' ? '!=' : '=';

			$query->subQuery->andWhere(
				"(select count([[{$alias}.id]]) from {{%neoblocks}} {{{$alias}}} where [[{$alias}.ownerId]] = [[elements.id]] and [[{$alias}.fieldId]] = :fieldId) {$operator} 0",
				[':fieldId' => $this->id]
			);
		}
		elseif ($value !== null)
		{
			return false;
		}

		return null;
	}

	public function getIsTranslatable(ElementInterface $element = null): bool
	{
		return $this->localizeBlocks;
	}

	public function getElementValidationRules(): array
	{
		return [
			'validateBlocks',
			[
				ArrayValidator::class,
				'min' => $this->minBlocks ?: null,
				'max' => $this->maxBlocks ?: null,
				'tooFew' => Craft::t('neo', '{attribute} should contain at least {min, number} {min, plural, one{block} other{blocks}}.'),
				'tooMany' => Craft::t('neo', '{attribute} should contain at most {max, number} {max, plural, one{block} other{blocks}}.'),
				'skipOnEmpty' => false,
				'on' => Element::SCENARIO_LIVE,
			],
		];
	}

	public function isValueEmpty($value, ElementInterface $element): bool
	{
		return $value->count() === 0;
	}

	public function validateBlocks(ElementInterface $element)
	{
		$value = $element->getFieldValue($this->handle);

		foreach ($value->all() as $key => $block)
		{
			if ($element->getScenario() === Element::SCENARIO_LIVE)
			{
				$block->setScenario(Element::SCENARIO_LIVE);
			}

			if (!$block->validate())
			{
				$element->addModelErrors($block, "{$this->handle}[{$key}]");
			}
		}
	}

	public function getSearchKeywords($value, ElementInterface $element): string
	{
		$keywords = [];

		foreach ($value->all() as $block)
		{
			$keywords[] = Neo::$plugin->blocks->getSearchKeywords($block);
		}

		return parent::getSearchKeywords($keywords, $element);
	}

	public function getEagerLoadingMap(array $sourceElements)
	{

	}

	public function afterSave(bool $isNew)
	{
		Neo::$plugin->fields->save($this);

		parent::afterSave($isNew);
	}

	public function beforeDelete(): bool
	{
		Neo::$plugin->fields->delete($this);

		return parent::beforeDelete();
	}

	public function afterElementSave(ElementInterface $element, bool $isNew)
	{
		Neo::$plugin->fields->saveValue($this, $element, $isNew);

		parent::afterElementSave($element, $isNew);
	}

	public function beforeElementDelete(ElementInterface $element): bool
	{
		$sitesService = Craft::$app->getSites();
		$elementsService = Craft::$app->getElements();

		foreach ($sitesService->getAllSiteIds() as $siteId)
		{
			$query = Block::find();
			$query->status(null);
			$query->enabledForSite(false);
			$query->siteId($siteId);
			$query->owner($element);

			$blocks = $query->all();

			foreach ($blocks as $block)
			{
				$elementsService->deleteElement($block);
			}
		}

		return parent::beforeElementDelete($element);
	}

	/**
	 * Returns what current depth the field is nested.
	 * For example, if a Neo field was being rendered inside a Matrix block, it's depth will be 2.
	 *
	 * @return int
	 */
	private function _getNamespaceDepth()
	{
		$namespace = Craft::$app->getView()->getNamespace();
		return preg_match_all('/\\bfields\\b/', $namespace);
	}

	private function _getNestingErrorHtml(): string
	{
		return '<span class="error">' . Craft::t('neo', "Unable to nest Neo fields.") . '</span>';
	}

	private function _getInputHtml($value, ElementInterface $element = null, bool $static = false): string
	{
		$viewService = Craft::$app->getView();

		if ($element !== null && $element->hasEagerLoadedElements($this->handle))
		{
			$value = $element->getEagerLoadedElements($this->handle);
		}

		if ($value instanceof BlockQuery)
		{
			$value = $value
				->limit(null)
				->status(null)
				->enabledForSite(false)
				->all();
		}

		$html = '';

		// Disable Neo fields inside Matrix, SuperTable and potentially other field-grouping field types.
		if ($this->_getNamespaceDepth() > 1)
		{
			$html = $this->_getNestingErrorHtml();
		}
		else
		{
			$viewService->registerAssetBundle(FieldAsset::class);
			$viewService->registerJs(FieldAsset::createInputJs($this, $value, $static));

			$html = $viewService->renderTemplate('neo/input', [
				'neoField' => $this,
				'id' => $viewService->formatInputId($this->handle),
				'name' => $this->handle,
				'translatable' => $this->localizeBlocks,
				'static' => $static,
			]);
		}

		return $html;
	}

	private function _createBlocksFromSerializedData($value, ElementInterface $element = null): array
	{
		$requestService = Craft::$app->getRequest();

		$blocks = [];

		if (is_array($value))
		{
			$oldBlocksById = [];
			$blockTypes = ArrayHelper::index(Neo::$plugin->blockTypes->getByFieldId($this->id), 'handle');
			
			if ($element && $element->id)
			{
				$ownerId = $element->id;
				$blockIds = [];

				foreach (array_keys($value) as $blockId)
				{
					if (is_numeric($blockId) && $blockId !== 0)
					{
						$blockIds[] = $blockId;
					}
				}

				if (!empty($blockIds))
				{
					$oldBlocksQuery = Block::find();
					$oldBlocksQuery->fieldId($this->id);
					$oldBlocksQuery->ownerId($ownerId);
					$oldBlocksQuery->id($blockIds);
					$oldBlocksQuery->limit(null);
					$oldBlocksQuery->status(null);
					$oldBlocksQuery->enabledForSite(false);
					$oldBlocksQuery->siteId($element->siteId);
					$oldBlocksQuery->indexBy('id');

					$oldBlocksById = $oldBlocksQuery->all();
				}
			}
			else
			{
				$ownerId = null;
			}

			$isLivePreview = $requestService->getIsLivePreview();

			foreach ($value as $blockId => $blockData)
			{
				$blockTypeHandle = isset($blockData['type']) ? $blockData['type'] : null;
				$blockType = $blockTypeHandle && isset($blockTypes[$blockTypeHandle]) ? $blockTypes[$blockTypeHandle] : null;
				$blockFields = isset($blockData['fields']) ? $blockData['fields'] : null;

				$isEnabled = isset($blockData['enabled']) ? (bool)$blockData['enabled'] : true;
				$isCollapsed = isset($blockData['collapsed']) ? (bool)$blockData['collapsed'] : false;
				$isNew = strpos($blockId, 'new') === 0;
				$isDeleted = !isset($oldBlocksById[$blockId]);

				if ($blockType && (!$isLivePreview || $isEnabled))
				{
					if ($isNew || $isDeleted)
					{
						$block = new Block();
						$block->fieldId = $this->id;
						$block->typeId = $blockType->id;
						$block->ownerId = $ownerId;
						$block->siteId = $element->siteId;
					}
					else
					{
						$block = $oldBlocksById[$blockId];
					}

					$block->setOwner($element);
					$block->setCollapsed($isCollapsed);
					$block->enabled = $isEnabled;
					$block->level = ((int)$blockData['level']) + 1;

					$fieldNamespace = $element->getFieldParamNamespace();

					if ($fieldNamespace !== null)
					{
						$blockNamespace = ($fieldNamespace ? $fieldNamespace . '.' : '') . "$this->handle.$blockId.fields";
						$block->setFieldParamNamespace($blockNamespace);
					}

					if ($blockFields)
					{
						$block->setFieldValues($blockFields);
					}

					$blocks[] = $block;
				}
			}
		}

		return $blocks;
	}
}
