<?php
namespace benf\neo;

use Craft;
use craft\base\EagerLoadingFieldInterface;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field as BaseField;
use craft\db\Query;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ArrayHelper;
use craft\helpers\ElementHelper;
use craft\validators\ArrayValidator;

use benf\neo\Plugin as Neo;
use benf\neo\assets\FieldAsset;
use benf\neo\elements\Block;
use benf\neo\elements\db\BlockQuery;
use benf\neo\models\BlockStructure;
use benf\neo\models\BlockType;
use benf\neo\models\BlockTypeGroup;
use benf\neo\validators\FieldValidator;

/**
 * Class Field
 *
 * @package benf\neo
 * @author Spicy Web <craft@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.0.0
 */
class Field extends BaseField implements EagerLoadingFieldInterface
{
	/**
	 * @inheritdoc
	 */
	public static function displayName(): string
	{
		return Craft::t('neo', "Neo");
	}

	/**
	 * @inheritdoc
	 */
	public static function hasContentColumn(): bool
	{
		return false;
	}

	/**
	 * @inheritdoc
	 */
	public static function supportedTranslationMethods(): array
	{
		return [
			self::TRANSLATION_METHOD_SITE,
		];
	}

	/**
	 * @var bool Whether this field is translatable.
	 */
	public $localizeBlocks = false;

	/**
	 * @var int|null The minimum number of blocks this field can have.
	 */
	public $minBlocks;

	/**
	 * @var int|null The maximum number of blocks this field can have.
	 */
	public $maxBlocks;

	/**
	 * @var int|null The maximum number of top-level blocks this field can have.
	 */
	public $maxTopBlocks;

	/**
	 * @var array|null The block types associated with this field.
	 */
	private $_blockTypes;

	/**
	 * @var array|null The block type groups associated with this field.
	 */
	private $_blockTypeGroups;

	/**
	 * @inheritdoc
	 */
	public function rules(): array
	{
		$rules = parent::rules();
		$rules[] = [['minBlocks', 'maxBlocks', 'maxTopBlocks'], 'integer', 'min' => 0];

		return $rules;
	}

	/**
	 * Returns this field's block types.
	 *
	 * @return array This field's block types.
	 */
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

	/**
	 * Sets this field's block types.
	 *
	 * @param array $blockTypes The block types to associate with this field.
	 */
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

					// Ensure the field layout ID is set, if it exists
					if (is_int($blockTypeId))
					{
						$layoutIdResult = (new Query)
							->select(['fieldLayoutId'])
							->from('{{%neoblocktypes}}')
							->where(['id' => $blockTypeId])
							->one();

						if ($layoutIdResult !== null)
						{
							$fieldLayout->id = $layoutIdResult['fieldLayoutId'];
						}
					}

					$newBlockType->setFieldLayout($fieldLayout);
				}
			}
			
			$newBlockTypes[] = $newBlockType;
		}

		$this->_blockTypes = $newBlockTypes;
	}

	/**
	 * Returns this field's block type groups.
	 *
	 * @return array This field's block type groups.
	 */
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

	/**
	 * Sets this field's block type groups.
	 *
	 * @param array $blockTypeGroups The block type groups to associate with this field.
	 */
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

	/**
	 * @inheritdoc
	 */
	public function validate($attributeNames = null, $clearErrors = true): bool
	{
		$validates = parent::validate($attributeNames, $clearErrors);
		$validates = $validates && Neo::$plugin->fields->validate($this);

		return $validates;
	}

	/**
	 * @inheritdoc
	 */
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

	/**
	 * @inheritdoc
	 */
	public function getInputHtml($value, ElementInterface $element = null): string
	{
		return $this->_getInputHtml($value, $element);
	}

	/**
	 * @inheritdoc
	 */
	public function getStaticHtml($value, ElementInterface $element): string
	{
		return $this->_getInputHtml($value, $element, true);
	}

	/**
	 * @inheritdoc
	 */
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
			$blockStructure = null;

			// Existing element?
			$existingElement = $element && $element->id;
			if ($existingElement)
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

			// If an owner element exists, set the appropriate owner site ID and block structure, depending on whether
			// the field is set to manage blocks on a per-site basis
			if ($existingElement)
			{
				if ($this->localizeBlocks)
				{
					// Look for the block structure with the owner site ID
					// If a structure is not found, then look for one without an owner site ID set
					$blockStructure = Neo::$plugin->blocks->getStructure($this->id, $element->id, $element->siteId);

					if ($blockStructure)
					{
						$query->ownerSiteId($element->siteId);
					}
					else
					{
						$blockStructure = Neo::$plugin->blocks->getStructure($this->id, $element->id);
					}
				}
				else
				{
					// Look for the block structure without looking for a specific owner site ID
					// If the structure's owner site ID is not null but does not match the owner's site ID, we did not
					// find the correct structure, so we'll need to set the query's owner site ID and look for the
					// structure with that owner site ID
					$blockStructure = Neo::$plugin->blocks->getStructure($this->id, $element->id);

					if ($blockStructure && $blockStructure->ownerSiteId && $blockStructure->ownerSiteId != $element->siteId)
					{
						$query->ownerSiteId($element->siteId);
						$blockStructure = Neo::$plugin->blocks->getStructure($this->id, $element->id, $element->siteId);
					}
				}
			}

			// If we found the block structure, set the query's structure ID
			if ($blockStructure)
			{
				$query->structureId($blockStructure->structureId);
			}

			// Set the initially matched elements if $value is already set, which is the case if there was a validation
			// error or we're loading an entry revision.
			if (is_array($value) || $value === '')
			{
				$elements = $this->_createBlocksFromSerializedData($value, $element);

				if (!Craft::$app->getRequest()->getIsLivePreview())
				{
					$query->anyStatus();
				}
				else
				{
					$query->status = Element::STATUS_ENABLED;
				}

				$query->limit = null;
				$query->setCachedResult($elements);
				$query->useMemoized($elements);
			}
		}

		return $query;
	}

	/**
	 * @inheritdoc
	 */
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

	/**
	 * @inheritdoc
	 */
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

	/**
	 * @inheritdoc
	 */
	public function getIsTranslatable(ElementInterface $element = null): bool
	{
		return $this->localizeBlocks;
	}

	/**
	 * @inheritdoc
	 */
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
			[
				FieldValidator::class,
				'maxTopBlocks' => $this->maxTopBlocks ?: null,
				'on' => Element::SCENARIO_LIVE,
			],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function isValueEmpty($value, ElementInterface $element): bool
	{
		return $value->count() === 0;
	}

	/**
	 * Perform validation on blocks belonging to this field for a given element.
	 *
	 * @param ElementInterface $element
	 */
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

	/**
	 * @inheritdoc
	 */
	public function getSearchKeywords($value, ElementInterface $element): string
	{
		$keywords = [];

		foreach ($value->all() as $block)
		{
			$keywords[] = Neo::$plugin->blocks->getSearchKeywords($block);
		}

		return parent::getSearchKeywords($keywords, $element);
	}

	/**
	 * @inheritdoc
	 */
	public function getEagerLoadingMap(array $sourceElements)
	{
		$sourceElementIds = [];

		foreach ($sourceElements as $sourceElement)
		{
			$sourceElementIds[] = $sourceElement->id;
		}

		// Return any relation data on these elements, defined with this field.
		$map = (new Query())
			->select(['[[neoblocks.ownerId]] as source', '[[neoblocks.id]] as target'])
			->from('{{%neoblocks}} neoblocks')
			->where([
				'[[neoblocks.ownerId]]' => $sourceElementIds,
				'[[neoblocks.fieldId]]' => $this->id
			])
			// Join structural information to get the ordering of the blocks.
			->leftJoin(
				'{{%neoblockstructures}} neoblockstructures',
				[
					'and',
					'[[neoblockstructures.ownerId]] = [[neoblocks.ownerId]]',
					'[[neoblockstructures.fieldId]] = [[neoblocks.fieldId]]',
					[
						'or',
						'[[neoblockstructures.ownerSiteId]] = [[neoblocks.ownerSiteId]]',

						// If there is no site ID set (in other words, `ownerSiteId` is `null`), then the above
						// comparison will not be true for some reason. So if it's not evaluated to true, then check
						// to see if both `ownerSiteId` properties are `null`.
						[
							'and',
							'[[neoblockstructures.ownerSiteId]] is null',
							'[[neoblocks.ownerSiteId]] is null',
						],
					],
				]
			)
			->leftJoin(
				'{{%structureelements}} structureelements',
				[
					'and',
					'[[structureelements.structureId]] = [[neoblockstructures.structureId]]',
					'[[structureelements.elementId]] = [[neoblocks.id]]',
				]
			)
			->orderBy(['[[structureelements.lft]]' => SORT_ASC])
			->all();

		return [
			'elementType' => Block::class,
			'map' => $map,
			'criteria' => ['fieldId' => $this->id],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function afterSave(bool $isNew)
	{
		Neo::$plugin->fields->save($this);

		parent::afterSave($isNew);
	}

	/**
	 * @inheritdoc
	 */
	public function beforeDelete(): bool
	{
		Neo::$plugin->fields->delete($this);

		return parent::beforeDelete();
	}

	/**
	 * @inheritdoc
	 */
	public function afterElementSave(ElementInterface $element, bool $isNew)
	{
		Neo::$plugin->fields->saveValue($this, $element, $isNew);

		parent::afterElementSave($element, $isNew);
	}

	/**
	 * @inheritdoc
	 */
	public function beforeElementDelete(ElementInterface $element): bool
	{
		if (!parent::beforeElementDelete($element))
		{
			return false;
		}

		$sitesService = Craft::$app->getSites();
		$elementsService = Craft::$app->getElements();

		// Craft hard-deletes element structure nodes even when soft-deleting an element, which means we lose all Neo
		// field structure data (i.e. block order, levels) when its owner is soft-deleted.  We need to get all block
		// structures for this field/owner before soft-deleting the blocks, and re-save them after the blocks are
		// soft-deleted, so the blocks can be restored correctly if the owner element is restored.
		$blockStructures = [];
		$blocksBySite = [];

		// Get the structures for each site
		$structureRows = (new Query())
			->select([
				'id',
				'structureId',
				'ownerId',
				'ownerSiteId',
				'fieldId',
			])
			->from(['{{%neoblockstructures}}'])
			->where([
				'fieldId' => $this->id,
				'ownerId' => $element->id,
			])
			->all();

		foreach ($structureRows as $row)
		{
			$blockStructures[] = new BlockStructure($row);
		}

		// Get the blocks for each structure
		foreach ($blockStructures as $blockStructure)
		{
			// Site IDs start from 1 -- let's treat non-localized blocks as site 0
			$key = $blockStructure->ownerSiteId ?? 0;
			$blocksBySite[$key] = Block::find()
				->anyStatus()
				->fieldId($this->id)
				->ownerSiteId($blockStructure->ownerSiteId)
				->owner($element)
				->all();
		}

		// Delete all Neo blocks for this element and field
		foreach ($sitesService->getAllSiteIds() as $siteId)
		{
			$blocks = Block::find()
				->anyStatus()
				->fieldId($this->id)
				->siteId($siteId)
				->owner($element)
				->inReverse()
				->all();

			foreach ($blocks as $block)
			{
				$block->deletedWithOwner = true;
				$elementsService->deleteElement($block);
			}
		}

		// Recreate the block structures with the original block data
		foreach ($blockStructures as $blockStructure)
		{
			$key = $blockStructure->ownerSiteId ?? 0;
			Neo::$plugin->blocks->saveStructure($blockStructure);
			Neo::$plugin->blocks->buildStructure($blocksBySite[$key], $blockStructure);
		}

		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function afterElementRestore(ElementInterface $element)
	{
		$elementsService = Craft::$app->getElements();
		$supportedSites = ElementHelper::supportedSitesForElement($element);

		// Restore the Neo blocks that were deleted with $element
		foreach ($supportedSites as $supportedSite)
		{
			$blocks = Block::find()
				->anyStatus()
				->siteId($supportedSite['siteId'])
				->owner($element)
				->trashed()
				->andWhere(['neoblocks.deletedWithOwner' => true])
				->all();

			foreach ($blocks as $block)
			{
				$elementsService->restoreElement($block);
			}
		}

		parent::afterElementRestore($element);
	}

	/**
	 * Returns what current depth the field is nested.
	 * For example, if a Neo field was being rendered inside a Matrix block, its depth will be 2.
	 *
	 * @return int
	 */
	private function _getNamespaceDepth()
	{
		$namespace = Craft::$app->getView()->getNamespace();
		return preg_match_all('/\\bfields\\b/', $namespace);
	}

	/**
	 * Returns the error HTML associated with attempts to nest a Neo field within some other field.
	 *
	 * @return string
	 */
	private function _getNestingErrorHtml(): string
	{
		return '<span class="error">' . Craft::t('neo', "Unable to nest Neo fields.") . '</span>';
	}

	/**
	 * Returns the input HTML for a Neo field.
	 *
	 * @param BlockQuery|array $value The block query or block data to render.
	 * @param ElementInterface|null $element The element associated with this field, if any.
	 * @param bool $static Whether to generate static HTML, e.g. for displaying entry revisions.
	 * @return string
	 */
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
				->anyStatus()
				->all();
		}

		$siteId = $element !== null ? $element->siteId : null;

		$html = '';

		// Disable Neo fields inside Matrix, SuperTable and potentially other field-grouping field types.
		if ($this->_getNamespaceDepth() > 1)
		{
			$html = $this->_getNestingErrorHtml();
		}
		else
		{
			$viewService->registerAssetBundle(FieldAsset::class);
			$viewService->registerJs(FieldAsset::createInputJs($this, $value, $static, $siteId));

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

	/**
	 * Creates Neo blocks out of the given serialized data.
	 *
	 * @param array $value The raw field data.
	 * @param ElementInterface|null $element The element associated with this field, if any.
	 * @return array The Blocks created from the given data.
	 */
	private function _createBlocksFromSerializedData($value, ElementInterface $element = null): array
	{
		$requestService = Craft::$app->getRequest();

		$blocks = [];

		if (is_array($value))
		{
			$oldBlocksById = [];
			$blockTypes = ArrayHelper::index(Neo::$plugin->blockTypes->getByFieldId($this->id), 'handle');
			$prevBlock = null;
			
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
					$oldBlocksQuery->anyStatus();
					$oldBlocksQuery->siteId($element->siteId);
					$oldBlocksQuery->indexBy('id');

					$oldBlocksById = $oldBlocksQuery->all();
				}
			}
			else
			{
				$ownerId = null;
			}

			// Generally, block data will be received with levels starting from 0, so they need to be adjusted up by 1.
			// For entry revisions and new entry drafts, though, the block data will have levels starting from 1.
			// Because the first block in a field will always be level 1, we can use that to check whether the count is
			// starting from 0 or 1 and thus ensure that all blocks display at the correct level.
			$adjustLevels = false;

			if (!empty($value))
			{
				$firstBlock = reset($value);
				$firstBlockLevel = (int)$firstBlock['level'];

				if ($firstBlockLevel === 0)
				{
					$adjustLevels = true;
				}
			}

			foreach ($value as $blockId => $blockData)
			{
				$blockTypeHandle = isset($blockData['type']) ? $blockData['type'] : null;
				$blockType = $blockTypeHandle && isset($blockTypes[$blockTypeHandle]) ? $blockTypes[$blockTypeHandle] : null;
				$blockFields = isset($blockData['fields']) ? $blockData['fields'] : null;

				$isEnabled = isset($blockData['enabled']) ? (bool)$blockData['enabled'] : true;
				$isCollapsed = isset($blockData['collapsed']) ? (bool)$blockData['collapsed'] : false;
				$isModified = isset($blockData['modified']) ? (bool)$blockData['modified'] : false;
				$isNew = strpos($blockId, 'new') === 0;
				$isDeleted = !isset($oldBlocksById[$blockId]);

				if ($blockType)
				{
					// Adjust block levels to their correct value if necessary.
					$blockLevel = (int)$blockData['level'];

					if ($adjustLevels)
					{
						$blockLevel++;
					}

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
					$block->setModified($isModified);
					$block->enabled = $isEnabled;
					$block->level = $blockLevel;

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

					if ($prevBlock)
					{
						$prevBlock->setNext($block);
						$block->setPrev($prevBlock);
					}

					$prevBlock = $block;
					$blocks[] = $block;
				}
			}

			foreach ($blocks as $block)
			{
				$block->setAllElements($blocks);
			}
		}

		return $blocks;
	}
}
