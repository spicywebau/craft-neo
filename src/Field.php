<?php

namespace benf\neo;

use benf\neo\assets\InputAsset;
use benf\neo\assets\SettingsAsset;
use benf\neo\elements\Block;
use benf\neo\elements\db\BlockQuery;
use benf\neo\enums\BlockTypeGroupDropdown;
use benf\neo\gql\arguments\elements\Block as NeoBlockArguments;
use benf\neo\gql\resolvers\elements\Block as NeoBlockResolver;
use benf\neo\gql\types\generators\BlockType as NeoBlockTypeGenerator;
use benf\neo\gql\types\input\Block as NeoBlockInputType;
use benf\neo\models\BlockStructure;
use benf\neo\models\BlockType;
use benf\neo\models\BlockTypeGroup;
use benf\neo\Plugin as Neo;
use benf\neo\validators\FieldValidator;
use Craft;
use craft\base\EagerLoadingFieldInterface;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field as BaseField;
use craft\base\GqlInlineFragmentFieldInterface;
use craft\base\GqlInlineFragmentInterface;
use craft\db\Query;
use craft\db\Table;
use craft\elements\db\ElementQueryInterface;
use craft\fields\conditions\EmptyFieldConditionRule;
use craft\helpers\ArrayHelper;
use craft\helpers\ElementHelper;
use craft\helpers\Gql as GqlHelper;
use craft\helpers\Html;
use craft\helpers\Queue;
use craft\helpers\StringHelper;
use craft\i18n\Translation;
use craft\queue\jobs\ApplyNewPropagationMethod;
use craft\services\Elements;
use craft\validators\ArrayValidator;
use GraphQL\Type\Definition\Type;
use yii\base\InvalidArgumentException;

/**
 * Class Field
 *
 * @package benf\neo
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.0.0
 */
class Field extends BaseField implements EagerLoadingFieldInterface, GqlInlineFragmentFieldInterface
{
    public const PROPAGATION_METHOD_NONE = 'none';
    public const PROPAGATION_METHOD_SITE_GROUP = 'siteGroup';
    public const PROPAGATION_METHOD_LANGUAGE = 'language';
    /**
     * @since 2.12.0
     */
    public const PROPAGATION_METHOD_CUSTOM = 'custom';
    public const PROPAGATION_METHOD_ALL = 'all';

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
     * @var int|null The minimum number of blocks this field can have.
     */
    public ?int $minBlocks = null;

    /**
     * @var int|null The maximum number of blocks this field can have.
     */
    public ?int $maxBlocks = null;

    /**
     * @var int|null The maximum number of top-level blocks this field can have.
     * @since 2.3.0
     */
    public ?int $maxTopBlocks = null;

    /**
     * @var int|null The minimum number of levels that blocks in this field can be nested.
     * @since 3.3.0
     */
    public ?int $minLevels = null;

    /**
     * @var int|null The maximum number of levels that blocks in this field can be nested.
     * @since 2.9.0
     */
    public ?int $maxLevels = null;

    /**
     * @var BlockType[]|null The block types associated with this field.
     */
    private ?array $_blockTypes = null;

    /**
     * @var BlockTypeGroup[]|null The block type groups associated with this field.
     */
    private ?array $_blockTypeGroups = null;

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
    public string $propagationMethod = self::PROPAGATION_METHOD_ALL;

    /**
     * @var string|null The old propagation method for this field
     */
    private ?string $_oldPropagationMethod = null;

    /**
     * @var string|null The field’s propagation key format, if [[propagationMethod]] is `custom`
     * @since 2.12.0
     */
    public ?string $propagationKeyFormat = null;

    private ?\Exception $_inputHtmlException = null;

    /**
     * @inheritdoc
     */
    public function __construct($config = [])
    {
        // Convert `localizeBlocks` to `propagationMethod`
        if (array_key_exists('localizeBlocks', $config)) {
            $config['propagationMethod'] = $config['localizeBlocks'] ? 'none' : 'all';
            unset($config['localizeBlocks']);
        }

        // Ignore `wasModified`
        if (array_key_exists('wasModified', $config)) {
            unset($config['wasModified']);
        }

        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        if ($this->propagationKeyFormat === '') {
            $this->propagationKeyFormat = null;
        }

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
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [
            ['propagationMethod'], 'in', 'range' => [
                self::PROPAGATION_METHOD_NONE,
                self::PROPAGATION_METHOD_SITE_GROUP,
                self::PROPAGATION_METHOD_LANGUAGE,
                self::PROPAGATION_METHOD_CUSTOM,
                self::PROPAGATION_METHOD_ALL,
            ],
        ];
        $rules[] = [['blockTypes'], ArrayValidator::class, 'min' => 1, 'skipOnEmpty' => false];
        $rules[] = [['minBlocks', 'maxBlocks', 'maxTopBlocks', 'minLevels', 'maxLevels'], 'integer', 'min' => 0];

        return $rules;
    }

    /**
     * Returns this field's block types.
     *
     * @return BlockType[] This field's block types.
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
    public function setBlockTypes(array $blockTypes)
    {
        $conditionsService = Craft::$app->getConditions();
        $fieldsService = Craft::$app->getFields();
        $request = Craft::$app->getRequest();
        $newBlockTypes = [];

        foreach ($blockTypes as $blockTypeId => $blockType) {
            $newBlockType = $blockType;

            if (!($blockType instanceof BlockType)) {
                foreach (array_keys($blockType['conditions']) as $elementType) {
                    if (!isset($blockType['conditions'][$elementType]['conditionRules'])) {
                        // Don't bother setting condition data for any element types that have no rules set
                        unset($blockType['conditions'][$elementType]);
                    } else {
                        // Get the condition config
                        $blockType['conditions'][$elementType] = $conditionsService->createCondition($blockType['conditions'][$elementType])->getConfig();
                    }
                }

                $newBlockType = new BlockType();
                $newBlockType->id = (int)$blockTypeId;
                $newBlockType->fieldId = $this->id;
                $newBlockType->name = $blockType['name'];
                $newBlockType->handle = $blockType['handle'];
                $newBlockType->description = $blockType['description'];
                $newBlockType->minBlocks = (int)$blockType['minBlocks'];
                $newBlockType->maxBlocks = (int)$blockType['maxBlocks'];
                $newBlockType->minSiblingBlocks = (int)$blockType['minSiblingBlocks'];
                $newBlockType->maxSiblingBlocks = (int)$blockType['maxSiblingBlocks'];
                $newBlockType->minChildBlocks = (int)$blockType['minChildBlocks'];
                $newBlockType->maxChildBlocks = (int)$blockType['maxChildBlocks'];
                $newBlockType->topLevel = (bool)$blockType['topLevel'];
                $newBlockType->childBlocks = $blockType['childBlocks'] ?: null;
                $newBlockType->sortOrder = (int)$blockType['sortOrder'];
                $newBlockType->conditions = $blockType['conditions'] ?? [];
                $newBlockType->groupId = isset($blockType['groupId']) ? (int)$blockType['groupId'] : null;

                // Allow the `fieldLayoutId` to be set in the blockType settings
                if ($fieldLayoutId = ($blockType['fieldLayoutId'] ?? null)) {
                    if ($fieldLayout = $fieldsService->getLayoutById($fieldLayoutId)) {
                        $newBlockType->setFieldLayout($fieldLayout);
                        $newBlockType->fieldLayoutId = $fieldLayout->id;
                    }
                } elseif ($request->getBodyParam('neoBlockType' . (string)$blockTypeId) !== null) {
                    // Otherwise, check for a field layout in the POST data
                    $fieldLayout = $fieldsService->assembleLayoutFromPost('neoBlockType' . (string)$blockTypeId);
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
                } else {
                    // No field layout data was sent, which means this is an existing block type whose field layout
                    // designer was never loaded, and therefore no changes were made to any field layout the block type
                    // already has
                    $fieldLayoutId = (new Query())
                        ->select(['fieldLayoutId'])
                        ->from('{{%neoblocktypes}}')
                        ->where(['id' => $blockTypeId])
                        ->scalar();

                    if ($fieldLayoutId) {
                        $fieldLayout = $fieldsService->getLayoutById($fieldLayoutId);
                        $newBlockType->setFieldLayout($fieldLayout);
                        $newBlockType->fieldLayoutId = $fieldLayoutId;
                    }
                }
            }

            $newBlockTypes[] = $newBlockType;
        }

        $this->_blockTypes = $newBlockTypes;
    }

    /**
     * Returns this field's block type groups.
     *
     * @return BlockTypeGroup[] This field's block type groups.
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
    public function setGroups(array $blockTypeGroups)
    {
        $newBlockTypeGroups = [];

        foreach ($blockTypeGroups as $id => $blockTypeGroup) {
            $newBlockTypeGroup = $blockTypeGroup;

            if (!($blockTypeGroup instanceof BlockTypeGroup)) {
                $newBlockTypeGroup = new BlockTypeGroup();
                $newBlockTypeGroup->id = (int)$id;
                $newBlockTypeGroup->fieldId = $this->id;
                $newBlockTypeGroup->name = $blockTypeGroup['name'];
                $newBlockTypeGroup->sortOrder = (int)$blockTypeGroup['sortOrder'];
                $newBlockTypeGroup->alwaysShowDropdown = match ($blockTypeGroup['alwaysShowDropdown']) {
                    BlockTypeGroupDropdown::Show => true,
                    BlockTypeGroupDropdown::Hide => false,
                    BlockTypeGroupDropdown::Global => null,
                };
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
    public function getSettingsHtml(): ?string
    {
        $viewService = Craft::$app->getView();
        $html = '';

        // Disable creating Neo fields inside Matrix, Super Table and potentially other field-grouping field types.
        if ($this->_getNamespaceDepth() >= 1) {
            $html = $this->_getNestingErrorHtml();
        } else {
            $viewService->registerAssetBundle(SettingsAsset::class);
            $viewService->registerJs(SettingsAsset::createSettingsJs($this));

            $html = $viewService->renderTemplate('neo/settings', ['neoField' => $this]);
        }

        return $html;
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(mixed $value, ?ElementInterface $element = null): string
    {
        // `inputHtml` being called a second time with namespace depth > 1 when there's an error...
        if ($this->_inputHtmlException !== null) {
            throw $this->_inputHtmlException;
        }

        // Disable Neo fields inside Matrix, Super Table and potentially other field-grouping field types.
        if ($this->_getNamespaceDepth() > 1) {
            return $this->_getNestingErrorHtml();
        }

        try {
            $view = Craft::$app->getView();
            $newIdCounter = 0;

            if ($element !== null && $element->hasEagerLoadedElements($this->handle)) {
                $value = $element->getEagerLoadedElements($this->handle);
            }

            if ($value instanceof BlockQuery) {
                $value = $value->getCachedResult() ?? $value->limit(null)->status(null)->all();
            }

            foreach ($value as $block) {
                if ($block->id === null) {
                    // Set a non-positive ID on the block, which the templates will interpret as a new, unsaved block
                    $block->id = $newIdCounter--;
                }

                $block->useMemoized($value);
            }

            $view->registerAssetBundle(InputAsset::class);
            $view->registerJs(InputAsset::createInputJs($this, $element));

            return $view->renderTemplate('neo/input', [
                'handle' => $this->handle,
                'blocks' => $value,
                'id' => $view->formatInputId($this->handle),
                'name' => $this->handle,
                'translatable' => $this->propagationMethod,
                'static' => false,
            ]);
        } catch (\Exception $e) {
            $this->_inputHtmlException = $e;
            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function getStaticHtml(mixed $value, ElementInterface $element): string
    {
        $value = $value->status(null)->all();

        if (empty($value)) {
            return '<p class="light">' . Craft::t('app', 'No blocks.') . '</p>';
        }

        $view = Craft::$app->getView();
        $view->registerAssetBundle(InputAsset::class);
        $view->registerJs(InputAsset::createInputJs($this, $element));

        foreach ($value as $block) {
            $block->useMemoized($value);
        }

        return $view->renderTemplate('neo/input', [
            'handle' => $this->handle,
            'blocks' => $value,
            'id' => Html::id($this->handle),
            'name' => $this->handle,
            'translatable' => $this->propagationMethod,
            'static' => true,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function normalizeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        if ($value instanceof ElementQueryInterface) {
            return $value;
        }

        $query = Block::find();
        $this->_populateQuery($query, $element);

        // Set the initially matched elements if $value is already set, which is the case if there was a validation
        // error or we're loading an entry revision.
        if ($value === '') {
            $query->setCachedResult([]);
            $query->useMemoized(false);
        } elseif ($element && is_array($value)) {
            $elements = $this->_createBlocksFromSerializedData($value, $element);
            $query->setCachedResult($elements);
            $query->useMemoized($elements);
        }

        return $query;
    }

    /**
     * @inheritdoc
     */
    public function serializeValue(mixed $value, ?ElementInterface $element = null): mixed
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
    public function getElementConditionRuleType(): array|string|null
    {
        return class_exists(EmptyFieldConditionRule::class) ? EmptyFieldConditionRule::class : null;
    }

    /**
     * @inheritdoc
     */
    public function modifyElementsQuery(ElementQueryInterface $query, mixed $value): void
    {
        if ($value === 'not :empty:') {
            $value = ':notempty:';
        }

        if ($value === ':notempty:' || $value === ':empty:') {
            $ns = $this->handle . '_' . StringHelper::randomString(5);
            $query->subQuery->andWhere([
                $value === ':empty:' ? 'not exists' : 'exists',
                (new Query())
                    ->from(["neoblocks_$ns" => '{{%neoblocks}}'])
                    ->innerJoin(["elements_$ns" => Table::ELEMENTS], "[[elements_$ns.id]] = [[neoblocks_$ns.id]]")
                    ->innerJoin(["neoblocks_owners_$ns" => '{{%neoblocks_owners}}'], [
                        'and',
                        "[[neoblocks_owners_$ns.blockId]] = [[elements_$ns.id]]",
                        "[[neoblocks_owners_$ns.ownerId]] = [[elements.id]]",
                    ])
                    ->andWhere([
                        "neoblocks_$ns.fieldId" => $this->id,
                        "elements_$ns.enabled" => true,
                        "elements_$ns.dateDeleted" => null,
                    ]),
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function getIsTranslatable(?ElementInterface $element = null): bool
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
            [
                'validateBlocks',
                'on' => [Element::SCENARIO_ESSENTIALS, Element::SCENARIO_DEFAULT, Element::SCENARIO_LIVE],
                'skipOnEmpty' => false,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function isValueEmpty(mixed $value, ElementInterface $element): bool
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
        $blocks = $value->all();
        $scenario = $element->getScenario();
        $allBlocksValidate = true;

        foreach ($blocks as $key => $block) {
            if (
                $scenario === Element::SCENARIO_ESSENTIALS ||
                ($block->enabled && $scenario === Element::SCENARIO_LIVE)
            ) {
                $block->setScenario($scenario);
            }

            if (!$block->validate()) {
                $element->addModelErrors($block, "{$this->handle}[{$key}]");
                $allBlocksValidate = false;
            }
        }

        if (!$allBlocksValidate) {
            $value->setCachedResult($blocks);
        }

        if ($scenario === Element::SCENARIO_LIVE) {
            if ($this->minBlocks || $this->maxBlocks) {
                $arrayValidator = new ArrayValidator([
                    'min' => $this->minBlocks ?: null,
                    'max' => $this->maxBlocks ?: null,
                    'tooFew' => Craft::t('neo', '{attribute} should contain at least {min, number} {min, plural, one{block} other{blocks}}.', [
                        'attribute' => Craft::t('site', $this->name),
                        'min' => $this->minBlocks,
                    ]),
                    'tooMany' => Craft::t('neo', '{attribute} should contain at most {max, number} {max, plural, one{block} other{blocks}}.', [
                        'attribute' => Craft::t('site', $this->name),
                        'max' => $this->maxBlocks,
                    ]),
                    'skipOnEmpty' => false,
                ]);

                if (!$arrayValidator->validate($blocks, $error)) {
                    $element->addError($this->handle, $error);
                }
            }

            $fieldValidator = new FieldValidator([
                'maxTopBlocks' => $this->maxTopBlocks ?: null,
                'minLevels' => $this->minLevels ?: null,
                'maxLevels' => $this->maxLevels ?: null,
            ]);
            $fieldValidator->validateAttribute($element, $this->handle);
        }
    }

    /**
     * @inheritdoc
     */
    public function getEagerLoadingMap(array $sourceElements): array|false|null
    {
        $sourceElementIds = [];

        foreach ($sourceElements as $sourceElement) {
            $sourceElementIds[] = $sourceElement->id;
        }

        // Return any relation data on these elements, defined with this field.
        $map = (new Query())
            ->select([
                'source' => 'neoblocks_owners.ownerId',
                'target' => 'neoblocks.id',
            ])
            ->from(['neoblocks' => '{{%neoblocks}}'])
            ->innerJoin(['neoblocks_owners' => '{{%neoblocks_owners}}'], [
                'and',
                '[[neoblocks_owners.blockId]] = [[neoblocks.id]]',
                ['neoblocks_owners.ownerId' => $sourceElementIds],
            ])
            ->where(['neoblocks.fieldId' => $this->id])
            // Join structural information to get the ordering of the blocks.
            ->leftJoin(
                '{{%neoblockstructures}} neoblockstructures',
                [
                    'and',
                    '[[neoblockstructures.ownerId]] = [[neoblocks_owners.ownerId]]',
                    '[[neoblockstructures.fieldId]] = [[neoblocks.fieldId]]',
                    '[[neoblockstructures.siteId]] = ' . Craft::$app->getSites()->getCurrentSite()->id,
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
            ->orderBy(['[[neoblocks_owners.sortOrder]]' => SORT_ASC])
            ->all();

        if (count($sourceElements) === 1) {
            $structureId = (new Query())
                ->select(['structureId'])
                ->from('{{%neoblockstructures}}')
                ->where([
                    'fieldId' => $this->id,
                    'ownerId' => $sourceElementIds[0],
                    'siteId' => $sourceElements[0]->siteId,
                ])
                ->scalar() ?: null;
        } else {
            $structureId = null;
        }

        return [
            'elementType' => Block::class,
            'map' => $map,
            'criteria' => [
                'fieldId' => $this->id,
                'ownerId' => $sourceElementIds,
                'structureId' => $structureId,
                'allowOwnerDrafts' => true,
                'allowOwnerRevisions' => true,
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
    public function afterSave(bool $isNew): void
    {
        Neo::$plugin->fields->save($this);

        if ($this->oldSettings !== null) {
            $oldPropagationMethod = $this->oldSettings['propagationMethod'] ?? self::PROPAGATION_METHOD_ALL;
            $oldPropagationKeyFormat = $this->oldSettings['propagationKeyFormat'] ?? null;

            if ($this->propagationMethod !== $oldPropagationMethod || $this->propagationKeyFormat !== $oldPropagationKeyFormat) {
                Queue::push(new ApplyNewPropagationMethod([
                    'description' => Translation::prep('neo', 'Applying new propagation method to Neo blocks'),
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
    public function afterElementPropagate(ElementInterface $element, bool $isNew): void
    {
        $resetValue = false;

        if ($element->duplicateOf !== null) {
            // If this is a draft, just duplicate the relations
            if ($element->getIsDraft()) {
                Neo::$plugin->fields->duplicateOwnership($this, $element->duplicateOf, $element);
            } elseif ($element->getIsRevision()) {
                Neo::$plugin->fields->createRevisionBlocks($this, $element->duplicateOf, $element);
            } else {
                Neo::$plugin->fields->duplicateBlocks($this, $element->duplicateOf, $element, true, !$isNew);
            }
            $resetValue = true;
        } elseif ($element->isFieldDirty($this->handle) || !empty($element->newSiteIds)) {
            Neo::$plugin->fields->saveValue($this, $element);
        } elseif ($element->mergingCanonicalChanges) {
            Neo::$plugin->fields->mergeCanonicalChanges($this, $element);
            $resetValue = true;
        }

        // Repopulate the Neo block query if this is a new element
        if ($resetValue || $isNew) {
            $value = $element->getFieldValue($this->handle);
            if ($value instanceof BlockQuery) {
                $this->_populateQuery($value, $element);
            }
            $value->clearCachedResult();
            $value->useMemoized(false);
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
                'siteId',
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
            $key = $blockStructure->siteId ?? 0;

            $allBlocksQuery = Block::find()
                ->status(null)
                ->fieldId($this->id)
                ->primaryOwnerId($element->id);

            if ($key !== 0) {
                $allBlocksQuery->siteId($key);
            }

            $allBlocks = $allBlocksQuery->all();

            // if the Neo block structure doesn't have the siteId set and has blocks
            // set the siteId of the Neo block structure.

            // it's set from the first block because we got all blocks related to this structure beforehand
            // so the siteId should be the same for all blocks.
            if (empty($blockStructure->siteId) && !empty($allBlocks)) {
                $blockStructure->siteId = $allBlocks[0]->siteId;
                // need to set the new key since the siteId is now set
                $key = $blockStructure->siteId;
            }

            $blocksBySite[$key] = $allBlocks;
        }

        // Delete all Neo blocks for this element and field
        foreach ($sitesService->getAllSiteIds() as $siteId) {
            $blocks = Block::find()
                ->status(null)
                ->fieldId($this->id)
                ->siteId($siteId)
                ->primaryOwnerId($element->id)
                ->inReverse()
                ->all();

            foreach ($blocks as $block) {
                $block->deletedWithOwner = true;
                $elementsService->deleteElement($block);
            }
        }

        // Recreate the block structures with the original block data
        foreach ($blockStructures as $blockStructure) {
            $key = $blockStructure->siteId ?? 0;
            Neo::$plugin->blocks->saveStructure($blockStructure);
            Neo::$plugin->blocks->buildStructure($blocksBySite[$key], $blockStructure);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function afterElementRestore(ElementInterface $element): void
    {
        $elementsService = Craft::$app->getElements();
        $supportedSites = ElementHelper::supportedSitesForElement($element);

        // Restore the Neo blocks that were deleted with $element
        // No need to do anything related to block structures here since they were recreated in `beforeElementDelete()`
        foreach ($supportedSites as $supportedSite) {
            $blocks = Block::find()
                ->status(null)
                ->siteId($supportedSite['siteId'])
                ->primaryOwnerId($element->id)
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
    public function getContentGqlType(): Type|array
    {
        $typeArray = NeoBlockTypeGenerator::generateTypes($this);
        $typeName = $this->handle . '_NeoField';
        $resolver = static function(Block $value) {
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
    public function getContentGqlMutationArgumentType(): Type|array
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
    protected function searchKeywords(mixed $value, ElementInterface $element): string
    {
        $allFields = Craft::$app->getFields()->getAllFields();
        $keywords = [];

        foreach ($value->all() as $block) {
            $fieldLayout = $block->getFieldLayout();

            if ($fieldLayout === null) {
                continue;
            }

            foreach ($allFields as $field) {
                if ($field->searchable && $fieldLayout->isFieldIncluded($field->handle)) {
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
    private function _getNamespaceDepth(): int
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
     * Creates Neo blocks out of the given serialized data.
     *
     * @param array $value The raw field data.
     * @param ElementInterface $element The element associated with this field
     * @return Block[] The Blocks created from the given data.
     */
    private function _createBlocksFromSerializedData(array $value, ElementInterface $element): array
    {
        $draftsService = Craft::$app->getDrafts();
        $request = Craft::$app->getRequest();
        $user = Craft::$app->getUser();
        $blockTypes = ArrayHelper::index(Neo::$plugin->blockTypes->getByFieldId($this->id), 'handle');

        // Get the old blocks
        if ($element->id) {
            $oldBlocksById = Block::find()
                ->fieldId($this->id)
                ->ownerId($element->id)
                ->limit(null)
                ->status(null)
                ->siteId($element->siteId)
                ->orderBy(['sortOrder' => SORT_ASC])
                ->indexBy('id')
                ->all();
        } else {
            $oldBlocksById = [];
        }

        // Should we ignore disabled blocks?
        $hideDisabledBlocks = !$request->getIsConsoleRequest() && (
                $request->getToken() !== null ||
                $request->getIsLivePreview()
            );

        $fieldNamespace = $element->getFieldParamNamespace();
        $baseBlockFieldNamespace = $fieldNamespace ? "$fieldNamespace.$this->handle" : null;

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
            if (isset($newBlockData[$blockId])) {
                $blockData = $newBlockData[$blockId];
            } elseif (
                isset(Elements::$duplicatedElementSourceIds[$blockId]) &&
                isset($newBlockData[Elements::$duplicatedElementSourceIds[$blockId]])
            ) {
                // $blockId is a duplicated block's ID, but the data was sent with the original block ID
                $blockData = $newBlockData[Elements::$duplicatedElementSourceIds[$blockId]];
            } else {
                $blockData = [];
            }

            // If this is a preexisting block but we don't have a record of it,
            // check to see if it was recently duplicated.
            if (
                !str_starts_with($blockId, 'new') &&
                !isset($oldBlocksById[$blockId]) &&
                isset(Elements::$duplicatedElementIds[$blockId]) &&
                isset($oldBlocksById[Elements::$duplicatedElementIds[$blockId]])
            ) {
                $blockId = Elements::$duplicatedElementIds[$blockId];
            }

            // Existing block?
            if (isset($oldBlocksById[$blockId])) {
                $block = $oldBlocksById[$blockId];
                $dirty = !empty($blockData);
                $blockEnabled = (bool)($blockData['enabled'] ?? $block->enabled);

                // Is this a derivative element, and does the block primarily belong to the canonical?
                if ($dirty && $element->getIsDerivative() && $block->primaryOwnerId === $element->getCanonicalId()) {
                    // Duplicate it as a draft. (We'll drop its draft status from `Fields::saveValue`.)
                    $block = $draftsService->createDraft($block, $user->getId(), null, null, [
                        'canonicalId' => $block->id,
                        'primaryOwnerId' => $element->id,
                        'owner' => $element,
                        'siteId' => $element->siteId,
                        'propagating' => false,
                        'markAsSaved' => false,
                        'structureId' => null,
                        'level' => $block->level,
                        'enabled' => $blockEnabled,
                    ]);
                } else {
                    // Just make sure we update the block's enabled state
                    $block->enabled = $blockEnabled;
                }

                $block->dirty = $dirty;
            } else {
                // Make sure it's a valid block type
                if (!isset($blockData['type']) || !isset($blockTypes[$blockData['type']])) {
                    continue;
                }

                $block = new Block();
                $block->fieldId = $this->id;
                $block->typeId = $blockTypes[$blockData['type']]->id;
                $block->primaryOwnerId = $block->ownerId = $element->id;
                $block->siteId = $element->siteId;
                $block->enabled = true;
            }

            $blockLevel = (int)($blockData['level'] ?? $block->level);

            $block->setOwner($element);
            $block->oldLevel = $block->level;
            $block->level = $blockLevel;

            if (isset($blockData['collapsed'])) {
                $block->setCollapsed((bool)$blockData['collapsed']);
            }

            // Skip disabled blocks on Live Preview requests
            if ($hideDisabledBlocks && !$block->enabled) {
                continue;
            }

            // Set the content post location on the block if we can
            if ($baseBlockFieldNamespace) {
                $block->setFieldParamNamespace("$baseBlockFieldNamespace.$blockId.fields");
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
    private function _populateQuery(BlockQuery $query, ?ElementInterface $element = null): void
    {
        // Existing element?
        $existingElement = $element && $element->id;

        if ($element && $element->id) {
            $query->ownerId = $element->id;

            // Clear out id=false if this query was populated previously
            if ($query->id === false) {
                $query->id = null;
            }

            // If the owner is a revision, allow revision blocks to be returned as well
            if ($element->getIsRevision()) {
                $query
                    ->revisions(null)
                    ->trashed(null);
            }

            // If the owner element exists, set the appropriate block structure
            $blockStructure = Neo::$plugin->blocks->getStructure($this->id, $element->id, (int)$element->siteId);

            if ($blockStructure) {
                $query->structureId($blockStructure->structureId);
            }
        } else {
            $query->id = false;
        }

        $query
            ->fieldId($this->id)
            ->siteId($element->siteId ?? null);
    }
}
