<?php

namespace benf\neo;

use benf\neo\Plugin as Neo;
use benf\neo\assets\FieldAsset;
use benf\neo\elements\Block;
use benf\neo\elements\db\BlockQuery;
use benf\neo\gql\arguments\elements\Block as NeoBlockArguments;
use benf\neo\gql\resolvers\elements\Block as NeoBlockResolver;
use benf\neo\gql\types\generators\BlockType as NeoBlockTypeGenerator;
use benf\neo\gql\types\input\Block as NeoBlockInputType;
use benf\neo\jobs\DeleteBlock;
use benf\neo\jobs\DeleteBlocks;
use benf\neo\models\BlockStructure;
use benf\neo\models\BlockType;
use benf\neo\models\BlockTypeGroup;
use benf\neo\validators\FieldValidator;
use Craft;
use craft\base\EagerLoadingFieldInterface;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field as BaseField;
use craft\base\GqlInlineFragmentFieldInterface;
use craft\base\GqlInlineFragmentInterface;
use craft\db\Query;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ArrayHelper;
use craft\helpers\ElementHelper;
use craft\helpers\Gql as GqlHelper;
use craft\helpers\StringHelper;
use craft\helpers\Queue;
use craft\gql\GqlEntityRegistry;
use craft\queue\jobs\ApplyNewPropagationMethod;
use craft\queue\jobs\ResaveElements;
use craft\services\Elements;
use craft\validators\ArrayValidator;
use GraphQL\Type\Definition\Type;
use yii\base\UnknownPropertyException;
use yii\db\Exception;
use yii\base\InvalidArgumentException;

/**
 * Class Field
 *
 * @package benf\neo
 * @author Spicy Web <craft@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.0.0
 */
class Field extends BaseField implements EagerLoadingFieldInterface, GqlInlineFragmentFieldInterface
{
    const PROPAGATION_METHOD_NONE = 'none';
    const PROPAGATION_METHOD_SITE_GROUP = 'siteGroup';
    const PROPAGATION_METHOD_LANGUAGE = 'language';
    /**
     * @since 2.12.0
     */
    const PROPAGATION_METHOD_CUSTOM = 'custom';
    const PROPAGATION_METHOD_ALL = 'all';

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('neo', 'Neo');
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
     * @inheritdoc
     */
    public static function valueType(): string
    {
        return BlockQuery::class;
    }

    /**
     * @var bool Whether this field is translatable.
     * @deprecated in 2.4.0. Use [[$propagationMethod]] instead
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
     * @since 2.3.0
     */
    public $maxTopBlocks;

    /**
     * @var int|null The maximum number of levels that blocks in this field can be nested.
     * @since 2.9.0
     */
    public $maxLevels;

    /**
     * @var bool
     * @since 2.6.5
     * @deprecated in 2.12.5
     */
    public $wasModified = false;

    /**
     * @var array|null The block types associated with this field.
     */
    private $_blockTypes;

    /**
     * @var array|null The block type groups associated with this field.
     */
    private $_blockTypeGroups;

    /**
     * @var string Propagation method
     *
     * This will be set to one of the following:
     *
     * - `none` – Only save blocks in the site they were created in
     * - `siteGroup` – Save blocks to other sites in the same site group
     * - `language` – Save blocks to other sites with the same language
     * - `all` – Save blocks to all sites supported by the owner element
     * - `custom` – Save blocks to sites depending on the [[propagationKeyFormat]] value
     *
     * @since 2.4.0
     */
    public $propagationMethod = self::PROPAGATION_METHOD_ALL;

    /**
     * @var string The old propagation method for this field
     */
    private $_oldPropagationMethod;

    /**
     * @var string|null The field’s propagation key format, if [[propagationMethod]] is `custom`
     * @since 2.12.0
     */
    public $propagationKeyFormat;

    /**
     * @inheritdoc
     */
    public function __construct($config = [])
    {
        if (array_key_exists('localizeBlocks', $config)) {
            $config['propagationMethod'] = $config['localizeBlocks'] ? 'none' : 'all';
            unset($config['localizeBlocks']);
        }
        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        if ($this->propagationKeyFormat === '') {
            $this->propagationKeyFormat = null;
        }

        // Set localizeBlocks in case anything is still checking it
        $this->localizeBlocks = $this->propagationMethod === self::PROPAGATION_METHOD_NONE;
        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function settingsAttributes(): array
    {
        return ArrayHelper::withoutValue(parent::settingsAttributes(), 'localizeBlocks');
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = [
            ['propagationMethod'],
            'in',
            'range' => [
                self::PROPAGATION_METHOD_NONE,
                self::PROPAGATION_METHOD_SITE_GROUP,
                self::PROPAGATION_METHOD_LANGUAGE,
                self::PROPAGATION_METHOD_CUSTOM,
                self::PROPAGATION_METHOD_ALL
            ]
        ];
        $rules[] = [['minBlocks', 'maxBlocks', 'maxTopBlocks', 'maxLevels'], 'integer', 'min' => 0];

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

        if ($blockTypes === null) {
            if ($this->getIsNew()) {
                $blockTypes = [];
            } else {
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
        $fieldsService = Craft::$app->getFields();
        $newBlockTypes = [];

        foreach ($blockTypes as $blockTypeId => $blockType) {
            $newBlockType = $blockType;

            if (!($blockType instanceof BlockType)) {
                $newBlockType = new BlockType();
                $newBlockType->id = $blockTypeId;
                $newBlockType->fieldId = $this->id;
                $newBlockType->name = $blockType['name'];
                $newBlockType->handle = $blockType['handle'];
                $newBlockType->maxBlocks = (int)$blockType['maxBlocks'];
                $newBlockType->maxSiblingBlocks = (int)$blockType['maxSiblingBlocks'];
                $newBlockType->maxChildBlocks = (int)$blockType['maxChildBlocks'];
                $newBlockType->topLevel = (bool)$blockType['topLevel'];
                $newBlockType->childBlocks = $blockType['childBlocks'] ?: null;
                $newBlockType->sortOrder = (int)$blockType['sortOrder'];
                $newBlockType->groupId = isset($blockType['groupId']) ? (int)$blockType['groupId'] : null;

                // Allow the `fieldLayoutId` to be set in the blockType settings
                if ($fieldLayoutId = ($blockType['fieldLayoutId'] ?? null)) {
                    if ($fieldLayout = $fieldsService->getLayoutById($fieldLayoutId)) {
                        $newBlockType->setFieldLayout($fieldLayout);
                        $newBlockType->fieldLayoutId = $fieldLayout->id;
                    }
                }

                if (!empty($blockType['elementPlacements'])) {
                    $fieldLayout = $fieldsService->assembleLayoutFromPost('types.' . self::class . ".blockTypes.{$blockTypeId}");
                    $fieldLayout->type = Block::class;

                    // Ensure the field layout ID and UID are set, if they exist
                    if (is_int($blockTypeId)) {
                        $layoutResult = (new Query())
                            ->select([
                                'bt.fieldLayoutId',
                                'fl.uid',
                            ])
                            ->from('{{%neoblocktypes}} bt')
                            ->innerJoin('{{%fieldlayouts}} fl', '[[fl.id]] = [[bt.fieldLayoutId]]')
                            ->where(['bt.id' => $blockTypeId])
                            ->one();

                        if ($layoutResult !== null) {
                            $fieldLayout->id = $layoutResult['fieldLayoutId'];
                            $fieldLayout->uid = $layoutResult['uid'];
                        }
                    }

                    $newBlockType->setFieldLayout($fieldLayout);
                    $newBlockType->fieldLayoutId = $fieldLayout->id;
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

        if ($blockTypeGroups === null) {
            if ($this->getIsNew()) {
                $blockTypeGroups = [];
            } else {
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

        foreach ($blockTypeGroups as $id => $blockTypeGroup) {
            $newBlockTypeGroup = $blockTypeGroup;

            if (!($blockTypeGroup instanceof BlockTypeGroup)) {
                $newBlockTypeGroup = new BlockTypeGroup();
                $newBlockTypeGroup->id = $id;
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

        // Disable creating Neo fields inside Matrix, Super Table and potentially other field-grouping field types.
        if ($this->_getNamespaceDepth() >= 1) {
            $html = $this->_getNestingErrorHtml();
        } else {
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
        if ($value instanceof ElementQueryInterface) {
            return $value;
        }

        $query = Block::find();
        $this->_populateQuery($query, $element);

        // Set the initially matched elements if $value is already set, which is the case if there was a validation
        // error or we're loading an entry revision.
        $elements = null;

        if ($value === '') {
            $elements = [];
        } elseif ($element && is_array($value)) {
            $elements = $this->_createBlocksFromSerializedData($value, $element);
        }

        if ($elements !== null) {
            if (!Craft::$app->getRequest()->getIsLivePreview()) {
                $query->anyStatus();
            } else {
                $query->status = Element::STATUS_ENABLED;
            }

            $query->limit = null;
            // don't set the cached result if element is a draft initially.
            // on draft creation the all other sites (in a multisite) uses the cached result from the element (where the draft came from)
            // which overwrites the contents on the other sites.
            $query->setCachedResult($elements);
            $query->useMemoized($elements);
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

        foreach ($value->all() as $block) {
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
    public function copyValue(ElementInterface $from, ElementInterface $to): void
    {
        // Much like Matrix fields, we'll be doing this in afterElementPropagate()
    }

    /**
     * @inheritdoc
     */
    public function modifyElementsQuery(ElementQueryInterface $query, $value)
    {
        if ($value === 'not :empty:') {
            $value = ':notempty:';
        }

        if ($value === ':notempty:' || $value === ':empty:') {
            $alias = 'neoblocks_' . $this->handle;
            $operator = $value === ':notempty:' ? '!=' : '=';

            $query->subQuery->andWhere(
                "(select count([[{$alias}.id]]) from {{%neoblocks}} {{{$alias}}} where [[{$alias}.ownerId]] = [[elements.id]] and [[{$alias}.fieldId]] = :fieldId) {$operator} 0",
                [':fieldId' => $this->id]
            );
        } elseif ($value !== null) {
            return false;
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getIsTranslatable(ElementInterface $element = null): bool
    {
        return $this->propagationMethod !== self::PROPAGATION_METHOD_ALL;
    }

    /**
     * @inheritdoc
     */
    public function getStatus(ElementInterface $element): ?array
    {
        return $element->isFieldOutdated($this->handle) ? [
            Element::ATTR_STATUS_OUTDATED,
            Craft::t('app', 'This field was updated in the Current revision.'),
        ] : null;
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
                'tooFew' => Craft::t('neo',
                    '{attribute} should contain at least {min, number} {min, plural, one{block} other{blocks}}.'),
                'tooMany' => Craft::t('neo',
                    '{attribute} should contain at most {max, number} {max, plural, one{block} other{blocks}}.'),
                'skipOnEmpty' => false,
                'on' => Element::SCENARIO_LIVE,
            ],
            [
                FieldValidator::class,
                'maxTopBlocks' => $this->maxTopBlocks ?: null,
                'maxLevels' => $this->maxLevels ?: null,
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
        $blocks = $value->anyStatus()->all();
        $allBlocksValidate = true;

        foreach ($blocks as $key => $block) {
            if ($element->getScenario() === Element::SCENARIO_LIVE && $block->enabled) {
                $block->setScenario(Element::SCENARIO_LIVE);
            }

            if (!$block->validate()) {
                $element->addModelErrors($block, "{$this->handle}[{$key}]");

                if ($allBlocksValidate) {
                    $allBlocksValidate = false;
                }
            }
        }

        if (!$allBlocksValidate) {
            $value->setCachedResult($blocks);
        }
    }

    /**
     * @inheritdoc
     */
    public function getEagerLoadingMap(array $sourceElements)
    {
        $sourceElementIds = [];

        foreach ($sourceElements as $sourceElement) {
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
                    '[[neoblockstructures.ownerSiteId]] = ' . Craft::$app->getSites()->getCurrentSite()->id,
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
            ->orderBy(['[[neoblocks.sortOrder]]' => SORT_ASC])
            ->all();

        return [
            'elementType' => Block::class,
            'map' => $map,
            'criteria' => [
                'fieldId' => $this->id,
                'ownerId' => $sourceElementIds,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function beforeSave(bool $isNew): bool
    {
        if (!parent::beforeSave($isNew)) {
            return false;
        }

        $fieldsService = Craft::$app->getFields();
        $requestService = Craft::$app->getRequest();
        $class = Self::class;

        // Later, the field saving process will call `getGroups()` when trying to delete old groups. If this request is
        // coming from the field settings page and all groups were deleted by the user, `$this->_blockTypeGroups` will
        // be `null`, `getGroups()` will return `Neo::$plugin->blockTypes->getGroupsByFieldId($this->id)` and the groups
        // won't be deleted. By detecting this here, we can set an empty array of groups, so the groups will actually be
        // deleted.
        if (!$requestService->isConsoleRequest && $requestService->getBodyParam("types.{$class}") !== null && $requestService->getBodyParam("types.{$class}.groups") === null) {
            $this->setGroups([]);
        }

        // If a block type doesn't already have a field layout set, check for POST data from the field layout designer
        foreach ($this->getBlockTypes() as $blockType) {
            if (!$blockType->fieldLayout) {
                $fieldLayout = $fieldsService->assembleLayoutFromPost("types.{$class}.blockTypes.{$blockType->id}");
                $fieldLayout->type = $class;
                $blockType->setFieldLayout($fieldLayout);
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew)
    {
        Neo::$plugin->fields->save($this);

        if ($this->oldSettings !== null) {
            $oldPropagationMethod = $this->oldSettings['propagationMethod'] ?? self::PROPAGATION_METHOD_ALL;
            $oldPropagationKeyFormat = $this->oldSettings['propagationKeyFormat'] ?? null;

            if ($this->propagationMethod !== $oldPropagationMethod || $this->propagationKeyFormat !== $oldPropagationKeyFormat) {
                Queue::push(new ApplyNewPropagationMethod([
                    'description' => Craft::t('neo', 'Applying new propagation method to Neo blocks'),
                    'elementType' => Block::class,
                    'criteria' => [
                        'fieldId' => $this->id,
                    ],
                ]));
            }
        }

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
    public function afterElementPropagate(ElementInterface $element, bool $isNew)
    {
        $resetValue = false;

        /** @var Element $element */
        if ($element->duplicateOf !== null) {
            Neo::$plugin->fields->duplicateBlocks($this, $element->duplicateOf, $element, true);
            $resetValue = true;
        } else if ($element->isFieldDirty($this->handle) || !empty($element->newSiteIds)) {
            Neo::$plugin->fields->saveValue($this, $element);
        } else if ($element->mergingCanonicalChanges) {
            Neo::$plugin->fields->mergeCanonicalChanges($this, $element);
            $resetValue = true;
        }

        // Repopulate the Neo block query if this is a new element
        if ($resetValue || $isNew) {
            $query = $element->getFieldValue($this->handle);
            $this->_populateQuery($query, $element);
            $query->clearCachedResult();
            $query->useMemoized(false);
        }

        parent::afterElementPropagate($element, $isNew);
    }

    /**
     * @inheritdoc
     */
    public function beforeElementDelete(ElementInterface $element): bool
    {
        if (!parent::beforeElementDelete($element)) {
            return false;
        }

        // Craft hard-deletes element structure nodes even when soft-deleting an element, which means we lose all Neo
        // field structure data (i.e. block order, levels) when its owner is soft-deleted.  We need to get all block
        // structures for this field/owner before soft-deleting the blocks, and re-save them after the blocks are
        // soft-deleted, so the blocks can be restored correctly if the owner element is restored.
        $blockStructures = [];
        $blocksBySite = [];

        if (!$element->hardDelete) {
            // Get the structures for each site
            $structureRows = (new Query())
                ->select([
                    'id',
                    'structureId',
                    'ownerSiteId',
                    'ownerId',
                    'fieldId',
                ])
                ->from(['{{%neoblockstructures}}'])
                ->where([
                    'fieldId' => $this->id,
                    'ownerId' => $element->id,
                ])
                ->all();

            foreach ($structureRows as $row) {
                $blockStructures[] = new BlockStructure($row);
            }

            // Get the blocks for each structure
            foreach ($blockStructures as $blockStructure) {
                // Site IDs start from 1 -- let's treat non-localized blocks as site 0
                $key = $blockStructure->ownerSiteId ?? 0;

                $allBlocksQuery = Block::find()
                    ->anyStatus()
                    ->fieldId($this->id)
                    ->ownerId($element->id);

                if ($key !== 0) {
                    $allBlocksQuery->siteId($key);
                }

                $allBlocks = $allBlocksQuery->all();

                // if the neo block structure doesn't have the ownerSiteId set and has blocks
                // set the ownerSiteId of the neo block structure.

                // it's set from the first block because we got all blocks related to this structure beforehand
                // so the siteId should be the same for all blocks.
                if (empty($blockStructure->ownerSiteId) && !empty($allBlocks)) {
                    $blockStructure->ownerSiteId = $allBlocks[0]->siteId;
                    // need to set the new key since the ownersiteid is now set
                    $key = $blockStructure->ownerSiteId;
                }

                $blocksBySite[$key] = $allBlocks;
            }
        } else {
            // If the owner element is being hard-deleted, make sure any block structure data is deleted
            foreach (ArrayHelper::getColumn(ElementHelper::supportedSitesForElement($element), 'siteId') as $siteId) {
                while (($blockStructure = Neo::$plugin->blocks->getStructure($this->id, $element->id, $siteId)) !== null) {
                    Neo::$plugin->blocks->deleteStructure($blockStructure, true);
                }
            }
        }

        // Delete all Neo blocks for this element and field
        Queue::push(new DeleteBlocks([
            'fieldId' => $this->id,
            'elementId' => $element->id,
            'hardDelete' => $element->hardDelete,
        ]));

        // Recreate the block structures with the original block data
        foreach ($blockStructures as $blockStructure) {
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
        // No need to do anything related to block structures here since they were recreated in `beforeElementDelete()`
        foreach ($supportedSites as $supportedSite) {
            $blocks = Block::find()
                ->anyStatus()
                ->siteId($supportedSite['siteId'])
                ->ownerId($element->id)
                ->trashed()
                ->andWhere(['neoblocks.deletedWithOwner' => true])
                ->all();

            foreach ($blocks as $block) {
                $elementsService->restoreElement($block);
            }
        }

        parent::afterElementRestore($element);
    }

    /**
     * @inheritdoc
     */
    public function getContentGqlType()
    {
        $typeArray = NeoBlockTypeGenerator::generateTypes($this);
        $typeName = $this->handle . '_NeoField';
        $resolver = static function (Block $value) {
            return $value->getGqlTypeName();
        };

        return [
            'name' => $this->handle,
            'type' => Type::listOf(GqlHelper::getUnionType($typeName, $typeArray, $resolver)),
            'args' => NeoBlockArguments::getArguments(),
            'resolve' => NeoBlockResolver::class . '::resolve',
        ];
    }

    /**
     * @inheritdoc
     * @since 2.9.0
     */
    public function getContentGqlMutationArgumentType()
    {
        return NeoBlockInputType::getType($this);
    }

    /**
     * @inheritdoc
     * @throws InvalidArgumentException
     */
    public function getGqlFragmentEntityByName(string $fragmentName): GqlInlineFragmentInterface
    {
        $blockTypeHandle = StringHelper::removeLeft(StringHelper::removeRight($fragmentName, '_BlockType'), $this->handle . '_');
        $blockType = ArrayHelper::firstWhere($this->getBlockTypes(), 'handle', $blockTypeHandle);

        if (!$blockType) {
            throw new InvalidArgumentException('Invalid fragment name: ' . $fragmentName);
        }

        return $blockType;
    }

    /**
     * @inheritdoc
     */
    protected function searchKeywords($value, ElementInterface $element): string
    {
        $allFields = Craft::$app->getFields()->getAllFields();
        $keywords = [];

        foreach ($value->all() as $block) {
            $fieldLayout = $block->getFieldLayout();

            if ($fieldLayout === null) {
                continue;
            }

            foreach ($allFields as $field) {
                if ($field->searchable && in_array($field->id, $fieldLayout->getFieldIds())) {
                    $fieldValue = $block->getFieldValue($field->handle);
                    $keywords[] = $field->getSearchKeywords($fieldValue, $element);
                }
            }
        }

        return parent::searchKeywords($keywords, $element);
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
        return '<span class="error">' . Craft::t('neo', 'Unable to nest Neo fields.') . '</span>';
    }

    /**
     * Returns the input HTML for a Neo field.
     *
     * @param BlockQuery|array $value The block query or block data to render.
     * @param ElementInterface|null $element The element associated with this field, if any.
     * @param bool $static Whether to generate static HTML, e.g. for displaying entry revisions.
     * @return string
     * @throws
     */
    private function _getInputHtml($value, ElementInterface $element = null, bool $static = false): string
    {
        $viewService = Craft::$app->getView();

        if ($element !== null && $element->hasEagerLoadedElements($this->handle)) {
            $value = $element->getEagerLoadedElements($this->handle);
        }

        if ($value instanceof BlockQuery) {
            $query = $value;
            $value = $query->getCachedResult() ?? $query->limit(null)->anyStatus()->all();
        }

        $siteId = $element->siteId ?? Craft::$app->getSites()->getCurrentSite()->id;
        $html = '';

        // Disable Neo fields inside Matrix, Super Table and potentially other field-grouping field types.
        if ($this->_getNamespaceDepth() > 1) {
            $html = $this->_getNestingErrorHtml();
        } else if ($static && empty($value)) {
            $html = '<p class="light">' . Craft::t('app', 'No blocks.') . '</p>';
        } else {
            $viewService->registerAssetBundle(FieldAsset::class);
            $viewService->registerJs(FieldAsset::createInputJs($this, $value, $static, $siteId, $element));

            $html = $viewService->renderTemplate('neo/input', [
                'neoField' => $this,
                'id' => $viewService->formatInputId($this->handle),
                'name' => $this->handle,
                'translatable' => $this->propagationMethod,
                'static' => $static,
            ]);
        }

        return $html;
    }

    /**
     * Creates Neo blocks out of the given serialized data.
     *
     * @param array $value The raw field data.
     * @param ElementInterface $element The element associated with this field
     * @return array The Blocks created from the given data.
     */
    private function _createBlocksFromSerializedData(array $value, ElementInterface $element): array
    {
        $blockTypes = ArrayHelper::index(Neo::$plugin->blockTypes->getByFieldId($this->id), 'handle');

        // Get the old blocks
        if ($element->id) {
            $oldBlocksById = Block::find()
                ->fieldId($this->id)
                ->ownerId($element->id)
                ->limit(null)
                ->anyStatus()
                ->siteId($element->siteId)
                ->orderBy(['neoblocks.sortOrder' => SORT_ASC])
                ->indexBy('id')
                ->all();
        } else {
            $oldBlocksById = [];
        }

        $fieldNamespace = $element->getFieldParamNamespace();
        $baseBlockFieldNamespace = $fieldNamespace ? "{$fieldNamespace}.{$this->handle}" : null;

        // Was the value posted in the new (delta) format?
        if (isset($value['blocks']) || isset($value['sortOrder'])) {
            $newBlockData = $value['blocks'] ?? [];
            $newSortOrder = $value['sortOrder'] ?? array_keys($oldBlocksById);
            if ($baseBlockFieldNamespace) {
                $baseBlockFieldNamespace .= '.blocks';
            }
        } else {
            $newBlockData = $value;
            $newSortOrder = array_keys($value);
        }

        /** @var Block[] $blocks */
        $blocks = [];
        $prevBlock = null;

        foreach ($newSortOrder as $blockId) {
            $blockData = $newBlockData[$blockId] ?? (
                isset(Elements::$duplicatedElementSourceIds[$blockId]) && isset($newBlockData[Elements::$duplicatedElementSourceIds[$blockId]])
                    ? $newBlockData[Elements::$duplicatedElementSourceIds[$blockId]]
                    : []
                );

            // If this is a preexisting block but we don't have a record of it,
            // check to see if it was recently duplicated.
            if (
                strpos($blockId, 'new') !== 0 &&
                !isset($oldBlocksById[$blockId]) &&
                isset(Elements::$duplicatedElementIds[$blockId]) &&
                isset($oldBlocksById[Elements::$duplicatedElementIds[$blockId]])
            ) {
                $blockId = Elements::$duplicatedElementIds[$blockId];
            }

            // Existing block?
            if (isset($oldBlocksById[$blockId])) {
                $block = $oldBlocksById[$blockId];
                $block->dirty = !empty($blockData);
            } else {
                // Make sure it's a valid block type
                if (!isset($blockData['type']) || !isset($blockTypes[$blockData['type']])) {
                    continue;
                }

                $block = new Block();
                $block->fieldId = $this->id;
                $block->typeId = $blockTypes[$blockData['type']]->id;
                $block->ownerId = $element->id;
                $block->siteId = $element->siteId;
            }

            $blockLevel = (int)($blockData['level'] ?? $block->level);

            $block->setOwner($element);
            $block->oldLevel = $block->level;
            $block->level = $blockLevel;

            if (isset($blockData['collapsed'])) {
                $block->setCollapsed((bool)$blockData['collapsed']);
            }

            if (isset($blockData['enabled'])) {
                $block->enabled = (bool)$blockData['enabled'];
            }

            // Set the content post location on the block if we can
            if ($baseBlockFieldNamespace) {
                $block->setFieldParamNamespace("{$baseBlockFieldNamespace}.{$blockId}.fields");
            }

            if (isset($blockData['fields'])) {
                $block->setFieldValues($blockData['fields']);
            }

            if ($prevBlock) {
                $prevBlock->setNext($block);
                $block->setPrev($prevBlock);
            }

            $prevBlock = $block;
            $blocks[] = $block;
        }

        if (!empty($blocks)) {
            // Generally, block data will be received with levels starting from 0, so they need to be adjusted up by 1.
            // For entry revisions and new entry drafts, though, the block data will have levels starting from 1.
            // Because the first block in a field will always be level 1, we can use that to check whether the count is
            // starting from 0 or 1 and thus ensure that all blocks display at the correct level.
            $adjustLevels = (int)$blocks[0]->level === 0;

            foreach ($blocks as $block) {
                $block->setAllElements($blocks);

                if ($adjustLevels) {
                    $block->level++;
                }
            }
        }

        return $blocks;
    }

    /**
     * Sets some default properties on a Neo block query on this field, given its owner element.
     *
     * @param BlockQuery $query
     * @param ElementInterface|null $element
     */
    private function _populateQuery(BlockQuery $query, ElementInterface $element = null)
    {
        // Existing element?
        $existingElement = $element && $element->id;

        if ($existingElement) {
            $query->ownerId($element->id);
        } else {
            $query->id(false);
        }

        $query->fieldId($this->id)->siteId($element->siteId ?? null);

        // If the owner element exists, set the appropriate block structure
        if ($existingElement) {
            $blockStructure = Neo::$plugin->blocks->getStructure($this->id, $element->id, (int)$element->siteId);

            if ($blockStructure) {
                $query->structureId($blockStructure->structureId);
            }
        }
    }
}
