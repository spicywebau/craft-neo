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
use benf\neo\jobs\DeleteBlocks;
use benf\neo\jobs\SaveBlockStructures;
use benf\neo\models\BlockStructure;
use benf\neo\models\BlockType;
use benf\neo\models\BlockTypeGroup;
use benf\neo\Plugin as Neo;
use benf\neo\validators\FieldValidator;
use Craft;
use craft\base\EagerLoadingFieldInterface;
use craft\base\Element;
use craft\base\ElementContainerFieldInterface;
use craft\base\ElementInterface;
use craft\base\Field as BaseField;
use craft\base\GqlInlineFragmentFieldInterface;
use craft\base\GqlInlineFragmentInterface;
use craft\base\NestedElementInterface;
use craft\db\Query;
use craft\db\Table;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\enums\AttributeStatus;
use craft\enums\Color;
use craft\enums\PropagationMethod;
use craft\errors\InvalidFieldException;
use craft\fields\conditions\EmptyFieldConditionRule;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\ElementHelper;
use craft\helpers\Gql as GqlHelper;
use craft\helpers\Html;
use craft\helpers\Queue;
use craft\helpers\StringHelper;
use craft\services\Elements;
use craft\validators\ArrayValidator;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Collection;
use yii\base\InvalidArgumentException;

/**
 * Class Field
 *
 * @package benf\neo
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.0.0
 */
class Field extends BaseField implements
    EagerLoadingFieldInterface,
    ElementContainerFieldInterface,
    GqlInlineFragmentFieldInterface
{
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
    public static function icon(): string
    {
        return '@benf/neo/icon.svg';
    }

    /**
     * @inheritdoc
     */
    public static function dbType(): array|string|null
    {
        return null;
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
    public static function phpType(): string
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
     * @var int|null The minimum number of top-level blocks this field can have.
     * @since 3.3.0
     */
    public ?int $minTopBlocks = null;

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
     * @var Array<BlockType|BlockTypeGroup>|null The block types and groups associated with this field.
     */
    private ?array $_items = null;

    /**
     * @var BlockType[]|null The block types' fields.
     */
    private ?array $_blockTypeFields = null;

    /**
     * @var PropagationMethod
     *
     * This will be set to one of the following:
     *
     * - `PropagationMethod::None` – Only save blocks in the site they were created in
     * - `PropagationMethod::SiteGroup` – Save blocks to other sites in the same site group
     * - `PropagationMethod::Language` – Save blocks to other sites with the same language
     * - `PropagationMethod::All` – Save blocks to all sites supported by the owner element
     * - `PropagationMethod::Custom` – Save blocks to sites depending on the [[propagationKeyFormat]] value
     *
     * @since 2.4.0
     */
    public PropagationMethod $propagationMethod = PropagationMethod::All;

    /**
     * @var PropagationMethod|null The old propagation method for this field
     */
    private ?PropagationMethod $_oldPropagationMethod = null;

    /**
     * @var string|null The field’s propagation key format, if [[propagationMethod]] is `PropagationMethod::Custom`
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
     * Returns all of the block types' fields.
     *
     * @param int[]|null $typeIds The Neo block type IDs to return fields for.
     * If null, all block type fields will be returned.
     * @return FieldInterface[]
     */
    public function getBlockTypeFields(?array $typeIds = null): array
    {
        if (!isset($this->_blockTypeFields)) {
            $this->_blockTypeFields = [];
            $fieldsService = Craft::$app->getFields();
            $blockTypes = array_filter($this->getBlockTypes(), fn($blockType) => $blockType->fieldLayoutId !== null);
            $layoutIds = array_map(fn($blockType) => $blockType->fieldLayoutId, $blockTypes);
            $fieldsById = ArrayHelper::index($fieldsService->getAllFields(), 'id');
            $fieldIdsByLayoutId = $fieldsService->getFieldIdsByLayoutIds($layoutIds);
            $blockTypesWithFields = array_filter(
                $blockTypes,
                fn($blockType) => isset($fieldIdsByLayoutId[$blockType->fieldLayoutId])
            );

            foreach ($blockTypesWithFields as $blockType) {
                foreach ($fieldIdsByLayoutId[$blockType->fieldLayoutId] as $fieldId) {
                    $this->_blockTypeFields[$blockType->id][] = $fieldsById[$fieldId];
                }
            }
        }

        $fields = [];

        foreach ($this->_blockTypeFields as $blockTypeId => $blockTypeFields) {
            if ($typeIds === null || in_array($blockTypeId, $typeIds)) {
                foreach (array_filter($blockTypeFields, fn($field) => !isset($fields[$field->id])) as $field) {
                    $fields[$field->id] = $field;
                }
            }
        }

        return array_values($fields);
    }

    /**
     * Sets this field's block types.
     *
     * @param array $blockTypes The block types to associate with this field.
     */
    public function setBlockTypes(array $blockTypes)
    {
        $newBlockTypes = [];

        foreach ($blockTypes as $id => $blockType) {
            $newBlockTypes[] = $blockType instanceof BlockType
                ? $blockType
                : $this->_createBlockType($blockType, $id);
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
            $newBlockTypeGroups[] = $blockTypeGroup instanceof BlockTypeGroup
                ? $blockTypeGroup
                : $this->_createGroup($blockTypeGroup, $id);
        }

        $this->_blockTypeGroups = $newBlockTypeGroups;
    }

    /**
     * Sets the block type / group items for this field.
     *
     * @return array of block type / group items associated with this field
     * @since 3.8.0
     */
    public function getItems(): array
    {
        if (!isset($this->_items)) {
            $this->_items = array_merge($this->getBlockTypes(), $this->getGroups());
            usort($this->_items, fn($a, $b) => $a->sortOrder <=> $b->sortOrder);
        }

        return $this->_items;
    }

    /**
     * Sets the block type / group items for this field.
     *
     * @param array $items The block type / group items to associate with this field.
     * @since 3.8.0
     */
    public function setItems(array $items): void
    {
        $blockTypes = [];
        $groups = [];

        foreach ($items['sortOrder'] as $i => $itemId) {
            $sortOrder = $i + 1;
            $overrides = [
                'sortOrder' => $sortOrder,
            ];

            if (str_starts_with($itemId, 'blocktype:')) {
                $itemId = substr($itemId, 10);

                if (isset($items['blockTypes'][$itemId])) {
                    $blockTypes[] = $this->_createBlockType($overrides + $items['blockTypes'][$itemId], $itemId);
                } else {
                    $blockType = Neo::$plugin->blockTypes->getById((int)$itemId);
                    $blockType->sortOrder = $sortOrder;
                    $blockTypes[] = $blockType;
                }
            } elseif (str_starts_with($itemId, 'group:')) {
                $itemId = substr($itemId, 6);

                if (isset($items['groups'][$itemId])) {
                    $groups[] = $this->_createGroup($overrides + $items['groups'][$itemId], $itemId);
                } else {
                    $group = Neo::$plugin->blockTypes->getGroupById((int)$itemId);
                    $group->sortOrder = $sortOrder;
                    $groups[] = $group;
                }
            }
        }

        $this->_blockTypes = $blockTypes;
        $this->_blockTypeGroups = $groups;
    }

    private function _createBlockType(array $blockType, int|string $id): BlockType
    {
        $conditionsService = Craft::$app->getConditions();
        $fieldsService = Craft::$app->getFields();
        $request = Craft::$app->getRequest();

        foreach (array_keys($blockType['conditions'] ?? []) as $elementType) {
            if (!isset($blockType['conditions'][$elementType]['conditionRules'])) {
                // Don't bother setting condition data for any element types that have no rules set
                unset($blockType['conditions'][$elementType]);
            } else {
                // Get the condition config
                $blockType['conditions'][$elementType] = $conditionsService->createCondition($blockType['conditions'][$elementType])->getConfig();
            }
        }

        // Ensure min/max child blocks only applies if we're actually allowed to have child blocks
        $childBlocks = $blockType['childBlocks'] ?: null;

        if (!empty($childBlocks)) {
            $minChildBlocks = (int)($blockType['minChildBlocks'] ?? 0);
            $maxChildBlocks = (int)($blockType['maxChildBlocks'] ?? 0);
        } else {
            $minChildBlocks = $maxChildBlocks = 0;
        }

        $newBlockType = new BlockType();
        $newBlockType->id = (int)$id;
        $newBlockType->fieldId = $this->id;
        $newBlockType->name = $blockType['name'];
        $newBlockType->handle = $blockType['handle'];
        $newBlockType->enabled = $blockType['enabled'] ?? true;
        $newBlockType->ignorePermissions = $blockType['ignorePermissions'] ?? true;
        $newBlockType->description = $blockType['description'] ?? '';
        $newBlockType->iconFilename = $blockType['iconFilename'] ?? '';
        $newBlockType->iconId = !empty($blockType['iconId']) ? (int)$blockType['iconId'] : null;
        $newBlockType->color = !empty($blockType['color']) && $blockType['color'] !== '__blank__'
            ? Color::from($blockType['color'])
            : null;
        $newBlockType->minBlocks = (int)($blockType['minBlocks'] ?? 0);
        $newBlockType->maxBlocks = (int)($blockType['maxBlocks'] ?? 0);
        $newBlockType->minSiblingBlocks = (int)($blockType['minSiblingBlocks'] ?? 0);
        $newBlockType->maxSiblingBlocks = (int)($blockType['maxSiblingBlocks'] ?? 0);
        $newBlockType->minChildBlocks = $minChildBlocks;
        $newBlockType->maxChildBlocks = $maxChildBlocks;
        $newBlockType->topLevel = (bool)($blockType['topLevel'] ?? true);
        $newBlockType->groupChildBlockTypes = isset($blockType['groupChildBlockTypes']) ? (bool)$blockType['groupChildBlockTypes'] : true;
        $newBlockType->childBlocks = $childBlocks;
        $newBlockType->sortOrder = (int)$blockType['sortOrder'];
        $newBlockType->conditions = $blockType['conditions'] ?? [];
        $newBlockType->groupId = isset($blockType['groupId']) ? (int)$blockType['groupId'] : null;

        // Allow the `fieldLayoutId` to be set in the blockType settings
        if ($fieldLayoutId = ($blockType['fieldLayoutId'] ?? null)) {
            if ($fieldLayout = $fieldsService->getLayoutById($fieldLayoutId)) {
                $newBlockType->setFieldLayout($fieldLayout);
                $newBlockType->fieldLayoutId = $fieldLayout->id;
            }
        } elseif ($request->getBodyParam('neoBlockType' . (string)$id) !== null) {
            // Otherwise, check for a field layout in the POST data
            $fieldLayout = $fieldsService->assembleLayoutFromPost('neoBlockType' . (string)$id);
            $fieldLayout->type = Block::class;

            // Ensure the field layout ID and UID are set, if they exist
            if (is_int($id)) {
                $layoutResult = (new Query())
                    ->select([
                        'bt.fieldLayoutId',
                        'fl.uid',
                    ])
                    ->from('{{%neoblocktypes}} bt')
                    ->innerJoin('{{%fieldlayouts}} fl', '[[fl.id]] = [[bt.fieldLayoutId]]')
                    ->where(['bt.id' => $id])
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
                ->where(['id' => $id])
                ->scalar();

            if ($fieldLayoutId) {
                $fieldLayout = $fieldsService->getLayoutById($fieldLayoutId);
                $newBlockType->setFieldLayout($fieldLayout);
                $newBlockType->fieldLayoutId = $fieldLayoutId;
            }
        }

        return $newBlockType;
    }

    private function _createGroup(array $blockTypeGroup, int|string $id): BlockTypeGroup
    {
        $alwaysShowDropdown = $blockTypeGroup['alwaysShowDropdown'] ?? null;
        $newBlockTypeGroup = new BlockTypeGroup();
        $newBlockTypeGroup->id = (int)$id;
        $newBlockTypeGroup->fieldId = $this->id;
        $newBlockTypeGroup->name = $blockTypeGroup['name'];
        $newBlockTypeGroup->sortOrder = (int)$blockTypeGroup['sortOrder'];
        $newBlockTypeGroup->alwaysShowDropdown = match ($alwaysShowDropdown) {
            BlockTypeGroupDropdown::Show => true,
            BlockTypeGroupDropdown::Hide => false,
            BlockTypeGroupDropdown::Global => null,
            // Handle cases where `alwaysShowDropdown` is already set to true/false/null
            true, false, null => $alwaysShowDropdown,
        };

        return $newBlockTypeGroup;
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

            $html = $viewService->renderTemplate('neo/settings', [
                'neoField' => $this,
                'items' => $this->getItems(),
            ]);
        }

        return $html;
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(mixed $value, ?ElementInterface $element = null, bool $inline = false): string
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

            $view->registerAssetBundle(InputAsset::class);
            $view->registerJs(InputAsset::createInputJs($this, $element));
            $filteredBlockTypes = ArrayHelper::index(InputAsset::$filteredBlockTypes, 'id');

            $blocks = [];
            $skippedBlockLevel = null;

            foreach ($value as $block) {
                if ($skippedBlockLevel !== null) {
                    // If an ancestor has been skipped due to having a filtered-out block type, skip this one too
                    if ($block->level > $skippedBlockLevel) {
                        continue;
                    }

                    $skippedBlockLevel = null;
                }

                if (isset($filteredBlockTypes[$block->typeId])) {
                    $blocks[] = $block;
                } else {
                    // If the block type data isn't there, it's been filtered out and the block should be skipped
                    $skippedBlockLevel = $block->level;
                }
            }

            foreach ($value as $block) {
                if ($block->id === null) {
                    // A validation error occurred and we're sending unsaved blocks back
                    $block->unsavedId = $newIdCounter++;
                }

                $block->useMemoized($value);
            }

            // Explanation: https://github.com/craftcms/cms/blob/4.4.13/src/fields/Matrix.php#L732-L735
            if (
                $this->minBlocks != 0 &&
                count($filteredBlockTypes) === 1 &&
                (!$element || !$element->hasErrors($this->handle)) &&
                count($value) < $this->minBlocks
            ) {
                $view->setInitialDeltaValue($this->handle, null);
            }

            return $view->renderTemplate('neo/input', [
                'handle' => $this->handle,
                'blocks' => $blocks,
                'blockTypes' => $filteredBlockTypes,
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
        return $this->_normalizeValueInternal($value, $element, false);
    }

    /**
     * @inheritdoc
     */
    public function normalizeValueFromRequest(mixed $value, ?ElementInterface $element = null): mixed
    {
        return $this->_normalizeValueInternal($value, $element, true);
    }

    private function _normalizeValueInternal(mixed $value, ?ElementInterface $element, bool $fromRequest): mixed
    {
        if ($value instanceof ElementQueryInterface) {
            return $value;
        }

        $query = $this->_createBlockQuery($element);

        // Set the initially matched elements if $value is already set, which is the case if there was a validation
        // error or we're loading an entry revision.
        if ($value === '') {
            $query->setCachedResult([]);
            $query->useMemoized(false);
        } elseif ($element && is_array($value)) {
            $elements = $this->_createBlocksFromSerializedData($value, $element, $fromRequest);
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
                    ->innerJoin(["elements_owners_$ns" => Table::ELEMENTS_OWNERS], [
                        'and',
                        "[[elements_owners_$ns.elementId]] = [[elements_$ns.id]]",
                        "[[elements_owners_$ns.ownerId]] = [[elements.id]]",
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
        return $this->propagationMethod !== PropagationMethod::All;
    }

    /**
     * @inheritdoc
     */
    public function getStatus(ElementInterface $element): ?array
    {
        return $element->isFieldOutdated($this->handle) ? [
            AttributeStatus::Outdated,
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
        $allBlocks = (clone $value)->status(null)->all();
        $enabledBlocks = array_filter($allBlocks, fn($block) => $block->enabled);
        $scenario = $element->getScenario();
        $allBlocksValidate = true;

        foreach ($enabledBlocks as $key => $block) {
            if (in_array($scenario, [Element::SCENARIO_ESSENTIALS, Element::SCENARIO_LIVE])) {
                $block->setScenario($scenario);
            }

            if (!$block->validate()) {
                $element->addModelErrors($block, "{$this->handle}[{$key}]");
                $allBlocksValidate = false;
            }
        }

        if (!$allBlocksValidate) {
            $value->setCachedResult($allBlocks);
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

                if (!$arrayValidator->validate($enabledBlocks, $error)) {
                    $element->addError($this->handle, $error);
                }
            }

            $fieldValidator = new FieldValidator([
                'minTopBlocks' => $this->minTopBlocks ?: null,
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
                'source' => 'elements_owners.ownerId',
                'target' => 'neoblocks.id',
            ])
            ->from(['neoblocks' => '{{%neoblocks}}'])
            ->innerJoin(['elements_owners' => Table::ELEMENTS_OWNERS], [
                'and',
                '[[elements_owners.elementId]] = [[neoblocks.id]]',
                ['elements_owners.ownerId' => $sourceElementIds],
            ])
            ->where(['neoblocks.fieldId' => $this->id])
            // Join structural information to get the ordering of the blocks.
            ->leftJoin(
                '{{%neoblockstructures}} neoblockstructures',
                [
                    'and',
                    '[[neoblockstructures.ownerId]] = [[elements_owners.ownerId]]',
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
            ->orderBy(['[[elements_owners.sortOrder]]' => SORT_ASC])
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

        // If a block type doesn't already have a field layout set, check for POST data from the field layout designer
        foreach ($this->getBlockTypes() as $blockType) {
            if (!$blockType->fieldLayout && $requestService->getBodyParam("types.{$class}.items.blockTypes.{$blockType->id}") !== null) {
                $fieldLayout = $fieldsService->assembleLayoutFromPost("types.{$class}.items.blockTypes.{$blockType->id}");
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
            $oldPropagationMethod = PropagationMethod::tryFrom($this->oldSettings['propagationMethod'] ?? '')
                ?? PropagationMethod::All;
            $oldPropagationKeyFormat = $this->oldSettings['propagationKeyFormat'] ?? null;

            if ($this->propagationMethod !== $oldPropagationMethod || $this->propagationKeyFormat !== $oldPropagationKeyFormat) {
                Neo::$plugin->fields->applyPropagationMethod($this);
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
            // getIsUnpublishedDraft is needed for "save as new" duplication
            } elseif (!$element->getIsDraft() || $element->getIsUnpublishedDraft()) {
                Neo::$plugin->fields->duplicateBlocks($this, $element->duplicateOf, $element, true, !$isNew);
            }
            $resetValue = true;
        } elseif ($element->isFieldDirty($this->handle) || !empty($element->newSiteIds)) {
            Neo::$plugin->fields->saveValue($this, $element);
        } elseif ($element->mergingCanonicalChanges) {
            Neo::$plugin->fields->mergeCanonicalChanges($this, $element);
            $resetValue = true;
        }

        // Always reset the value if the owner is new
        if ($isNew || $resetValue) {
            $dirtyFields = $element->getDirtyFields();
            $element->setFieldValue($this->handle, $this->_createBlockQuery($element));
            $element->setDirtyFields($dirtyFields, false);
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

        $elementsService = Craft::$app->getElements();

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
        } else {
            // If the owner element is being hard-deleted, make sure any block structure data is deleted
            foreach (ArrayHelper::getColumn(ElementHelper::supportedSitesForElement($element), 'siteId') as $siteId) {
                $blockStructures = Neo::$plugin->blocks->getStructures([
                    'fieldId' => $this->id,
                    'ownerId' => $element->id,
                    'siteId' => $siteId,
                ]);
                foreach ($blockStructures as $blockStructure) {
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

        // Recreate the block structures with the original block data, if not hard-deleting the owner
        if (!$element->hardDelete) {
            foreach ($blockStructures as $blockStructure) {
                $key = $blockStructure->siteId ?? 0;
                $siteId = $key ?: 1;
                Queue::push(new SaveBlockStructures([
                    'fieldId' => $this->id,
                    'ownerId' => $element->id,
                    'siteId' => $siteId,
                    'otherSupportedSiteIds' => [],
                    'blocks' => array_map(
                        fn($block) => [
                            'id' => $block->id,
                            'level' => $block->level,
                            'lft' => $block->lft,
                            'rgt' => $block->rgt,
                        ],
                        $blocksBySite[$key]
                    ),
                ]));
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function afterElementDelete(ElementInterface $element): void
    {
        // Ensure draft/revision block structures aren't marked as deleted, in case $element gets restored
        if (!$element->hardDelete) {
            $draftIds = (new Query())
                ->select(['id'])
                ->from(Table::DRAFTS)
                ->where(['canonicalId' => $element->id])
                ->column();
            $revisionIds = (new Query())
                ->select(['id'])
                ->from(Table::REVISIONS)
                ->where(['canonicalId' => $element->id])
                ->column();
            $draftRevisionElementIds = (new Query())
                ->select(['id'])
                ->from(Table::ELEMENTS)
                ->where([
                    'or',
                    ['draftId' => $draftIds],
                    ['revisionId' => $revisionIds],
                ])
                ->column();
            $structureIds = (new Query())
                ->select(['structureId'])
                ->from('{{%neoblockstructures}}')
                ->where([
                    'ownerId' => $draftRevisionElementIds,
                    'fieldId' => $this->id,
                ])
                ->column();
            Db::update(
                Table::STRUCTURES,
                ['dateDeleted' => null],
                ['id' => $structureIds],
            );
        }
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
                ->andWhere(['elements.deletedWithOwner' => true])
                ->all();

            foreach ($blocks as $block) {
                $elementsService->restoreElement($block);
            }
        }

        parent::afterElementRestore($element);
    }

    // ElementContainerFieldInterface methods

    /**
     * @inheritdoc
     */
    public function getFieldLayoutProviders(): array
    {
        return $this->getBlockTypes();
    }

    /**
     * @inheritdoc
     */
    public function getUriFormatForElement(NestedElementInterface $element): ?string
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getRouteForElement(NestedElementInterface $element): mixed
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getSupportedSitesForElement(NestedElementInterface $element): array
    {
        try {
            $owner = $element->getOwner();
        } catch (InvalidConfigException) {
            $owner = $element->duplicateOf;
        }

        return !$owner
            ? [Craft::$app->getSites()->getPrimarySite()->id]
            : ElementHelper::supportedSitesForElement($owner);
    }

    /**
     * @inheritdoc
     */
    public function canViewElement(NestedElementInterface $element, User $user): ?bool
    {
        $owner = $element->getOwner();
        return $owner && Craft::$app->getElements()->canView($owner, $user);
    }

    /**
     * @inheritdoc
     */
    public function canSaveElement(NestedElementInterface $element, User $user): ?bool
    {
        $owner = $element->getOwner();

        if (!$owner || !Craft::$app->getElements()->canSave($owner, $user)) {
            return false;
        }

        // If this is a new block, make sure we aren't hitting the Max Block limit
        if (!$element->id && $element->getIsCanonical() && $this->_maxBlocksReached($owner)) {
            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function canDuplicateElement(NestedElementInterface $element, User $user): ?bool
    {
        $owner = $element->getOwner();

        if (!$owner || !Craft::$app->getElements()->canSave($owner, $user)) {
            return false;
        }

        // Make sure we aren't hitting the Max Blocks limit
        return !$this->_maxBlocksReached($owner);
    }

    /**
     * @inheritdoc
     */
    public function canDeleteElement(NestedElementInterface $element, User $user): ?bool
    {
        $owner = $element->getOwner();

        if (!$owner || !Craft::$app->getElements()->canSave($owner, $user)) {
            return false;
        }

        // Make sure we aren't hitting the Min Blocks limit
        return !$this->_minBlocksReached($owner);
    }

    /**
     * @inheritdoc
     */
    public function canDeleteElementForSite(NestedElementInterface $element, User $user): ?bool
    {
        $owner = $element->getOwner();

        if (!$owner || !Craft::$app->getElements()->canSave($owner, $user)) {
            return false;
        }

        // Make sure we aren't hitting the Min Blocks limit
        return !$this->_minBlocksReached($owner);
    }

    private function _minBlocksReached(ElementInterface $owner): bool
    {
        return (
            $this->minBlocks &&
            $this->minBlocks >= $this->_totalBlocks($owner)
        );
    }

    private function _maxBlocksReached(ElementInterface $owner): bool
    {
        return (
            $this->maxBlocks &&
            $this->maxBlocks <= $this->_totalBlocks($owner)
        );
    }

    private function _totalBlocks(ElementInterface $owner): int
    {
        /** @var EntryQuery|ElementCollection $value */
        $value = $owner->getFieldValue($this->handle);

        if ($value instanceof BlockQuery) {
            return (clone $value)
                ->drafts(null)
                ->status(null)
                ->site('*')
                ->limit(null)
                ->unique()
                ->count();
        }

        return $value->count();
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
     * @param bool $fromRequest Whether the data came from the request post data
     * @return Block[] The Blocks created from the given data.
     */
    private function _createBlocksFromSerializedData(array $value, ElementInterface $element, bool $fromRequest): array
    {
        $draftsService = Craft::$app->getDrafts();
        $request = Craft::$app->getRequest();
        $user = Craft::$app->getUser();
        $blockTypes = ArrayHelper::index(Neo::$plugin->blockTypes->getByFieldId($this->id), 'handle');

        // Were the blocks posted by UUID or ID?
        $uids = (
            (isset($value['blocks']) && str_starts_with(array_key_first($value['blocks']), 'uid:')) ||
            (isset($value['sortOrder']) && StringHelper::isUUID(reset($value['sortOrder'])))
        );

        if ($uids) {
            // strip out the `uid:` key prefixes
            if (isset($value['blocks'])) {
                $value['blocks'] = array_combine(
                    array_map(fn(string $key) => StringHelper::removeLeft($key, 'uid:'), array_keys($value['blocks'])),
                    array_values($value['blocks']),
                );
            }
        }

        // Get the old blocks
        if ($element->id) {
            $oldBlocksById = Block::find()
                ->fieldId($this->id)
                ->ownerId($element->id)
                ->limit(null)
                ->status(null)
                ->siteId($element->siteId)
                ->orderBy(['sortOrder' => SORT_ASC])
                ->indexBy($uids ? 'uid' : 'id')
                ->all();
        } else {
            $oldBlocksById = [];
        }

        if ($uids) {
            // Get the canonical block UUIDs in case the data was posted with them
            $derivatives = Collection::make($oldBlocksById)
                ->filter(fn(Block $block) => $block->getIsDerivative())
                ->keyBy(fn(Block $block) => $block->getCanonicalId());

            if ($derivatives->isNotEmpty()) {
                $canonicalUids = (new Query())
                    ->select(['id', 'uid'])
                    ->from(Table::ELEMENTS)
                    ->where(['id' => $derivatives->keys()->all()])
                    ->pairs();
                $derivativeUidMap = [];
                $canonicalUidMap = [];
                foreach ($canonicalUids as $canonicalId => $canonicalUid) {
                    $derivativeUid = $derivatives->get($canonicalId)->uid;
                    $derivativeUidMap[$canonicalUid] = $derivativeUid;
                    $canonicalUidMap[$derivativeUid] = $canonicalUid;
                }
            }
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

        $blocks = [];
        $prevBlock = null;

        foreach ($newSortOrder as $i => $blockId) {
            if (isset($newBlockData[$blockId])) {
                $blockData = $newBlockData[$blockId];
            } elseif (
                $uids &&
                isset($canonicalUidMap[$blockId]) &&
                isset($newBlockData[$canonicalUidMap[$blockId]])
            ) {
                // $blockId is a draft block's UUID, but the data was sent with the canonical block UUID
                $entryData = $newBlockData[$canonicalUidMap[$blockId]];
            } else {
                $blockData = [];
            }

            // If this is a preexisting block but we don't have a record of it,
            // check to see if it was recently duplicated.
            if (
                $uids &&
                !isset($oldBlocksById[$blockId]) &&
                isset($derivativeUidMap[$blockId]) &&
                isset($oldBlocksById[$derivativeUidMap[$blockId]])
            ) {
                $blockId = $derivativeUidMap[$blockId];
            }

            // Existing block?
            if (isset($oldBlocksById[$blockId])) {
                $block = $oldBlocksById[$blockId];
                $forceSave = !empty($blockData);
                $blockEnabled = (bool)($blockData['enabled'] ?? $block->enabled);

                // Is this a derivative element, and does the block primarily belong to the canonical?
                if ($forceSave && $element->getIsDerivative() && $block->getPrimaryOwnerId() === $element->getCanonicalId()) {
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

                $block->forceSave = $forceSave;
            } else {
                // Make sure it's a valid block type
                if (!isset($blockData['type']) || !isset($blockTypes[$blockData['type']])) {
                    continue;
                }

                $block = new Block();
                $block->fieldId = $this->id;
                $block->typeId = $blockTypes[$blockData['type']]->id;
                $block->setPrimaryOwner($element);
                $block->setOwner($element);
                $block->siteId = $element->siteId;
                $block->enabled = (bool)($blockData['enabled'] ?? true);
                $block->unsavedId = $i;

                // Use the provided UUID, so the block can persist across future autosaves
                if ($uids) {
                    $block->uid = $blockId;
                }
            }

            $blockLevel = (int)($blockData['level'] ?? $block->level);

            $block->oldLevel = $block->level;
            $block->level = $blockLevel;

            if (isset($blockData['collapsed'])) {
                $block->setCollapsed((bool)$blockData['collapsed']);
            }

            // Allow setting the UID for the block element
            if (isset($blockData['uid'])) {
                $block->uid = $blockData['uid'];
            }

            // Skip disabled blocks on Live Preview requests
            if ($hideDisabledBlocks && !$block->enabled) {
                continue;
            }

            $block->setOwner($element);

            // Set the content post location on the block if we can
            if ($baseBlockFieldNamespace) {
                if ($uids) {
                    $block->setFieldParamNamespace("$baseBlockFieldNamespace.uid:$blockId.fields");
                } else {
                    $block->setFieldParamNamespace("$baseBlockFieldNamespace.$blockId.fields");
                }
            }

            if (isset($blockData['fields'])) {
                foreach ($blockData['fields'] as $fieldHandle => $fieldValue) {
                    try {
                        if ($fromRequest) {
                            $block->setFieldValueFromRequest($fieldHandle, $fieldValue);
                        } else {
                            $block->setFieldValue($fieldHandle, $fieldValue);
                        }
                    } catch (InvalidFieldException) {
                    }
                }
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
                $block->useMemoized($blocks);

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
     * @param ElementInterface|null $owner
     */
    private function _createBlockQuery(?ElementInterface $owner): BlockQuery
    {
        $query = Block::find();

        // Existing element?
        if ($owner && $owner->id) {
            $query->ownerId = $owner->id;

            // Clear out id=false if this query was populated previously
            if ($query->id === false) {
                $query->id = null;
            }

            // If the owner is a revision, allow revision blocks to be returned as well
            if ($owner->getIsRevision()) {
                $query
                    ->revisions(null)
                    ->trashed(null);
            }

            // If the owner element exists, set the appropriate block structure
            $blockStructure = Neo::$plugin->blocks->getStructure($this->id, $owner->id, (int)$owner->siteId);

            if ($blockStructure) {
                $query->structureId($blockStructure->structureId);
            }

            // Prepare the query for lazy eager loading
            $query->prepForEagerLoading($this->handle, $owner);
        } else {
            $query->id = false;
        }

        $query
            ->fieldId($this->id)
            ->siteId($owner->siteId ?? null);

        return $query;
    }
}
