<?php

namespace benf\neo\models;

use benf\neo\elements\Block;
use benf\neo\Field;
use benf\neo\fieldlayoutelements\ChildBlocksUiElement;
use benf\neo\Plugin as Neo;
use Craft;
use craft\base\GqlInlineFragmentInterface;
use craft\base\Model;
use craft\behaviors\FieldLayoutBehavior;
use craft\db\Table;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\StringHelper;

/**
 * Class BlockType
 *
 * @package benf\neo\models
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.0.0
 */
class BlockType extends Model implements GqlInlineFragmentInterface
{
    /**
     * @var int|null The block type ID.
     */
    public ?int $id = null;

    /**
     * @var int|null The field ID.
     */
    public ?int $fieldId = null;

    /**
     * @var int|null The field layout ID.
     */
    public ?int $fieldLayoutId = null;

    /**
     * @var int|null The ID of the block type group this block type belongs to, if any.
     * @since 2.13.0
     */
    public ?int $groupId = null;

    /**
     * @var string|null The block type's name.
     */
    public ?string $name = null;

    /**
     * @var string|null The block type's handle.
     */
    public ?string $handle = null;

    /**
     * @var string|null The block type's description.
     * @since 3.0.5
     */
    public ?string $description = null;

    /**
     * @var int|null The minimum number of blocks of this type allowed in this block type's field.
     * @since 3.3.0
     */
    public ?int $minBlocks = null;

    /**
     * @var int|null The maximum number of blocks of this type allowed in this block type's field.
     */
    public ?int $maxBlocks = null;

    /**
     * @var int|null The maximum number of blocks of this type allowed under one parent block.
     * @since 2.8.0
     */
    public ?int $maxSiblingBlocks = null;

    /**
     * @var int|null The minimum number of child blocks.
     * @since 3.3.0
     */
    public ?int $minChildBlocks = null;

    /**
     * @var int|null The maximum number of child blocks.
     */
    public ?int $maxChildBlocks = null;

    /**
     * @var string[]|string|null The child block types of this block type, either as an array of block type handles, the
     * string '*' representing all of the Neo field's block types, or null if no child block types.
     */
    public array|string|null $childBlocks = null;

    /**
     * @var bool Whether this is at the top level of its field.
     */
    public bool $topLevel = true;

    /**
     * @var array Conditions for the elements this block type can be used on.
     */
    public array $conditions = [];

    /**
     * @var int|null The sort order.
     */
    public ?int $sortOrder = null;

    /**
     * @var string|null
     */
    public ?string $uid = null;

    /**
     * @var bool
     */
    public bool $hasFieldErrors = false;

    /**
     * @var Field|null The Neo field associated with this block type.
     */
    private ?Field $_field = null;

    /**
     * @var BlockTypeGroup|null The block type group this block type belongs to, if any.
     */
    private ?BlockTypeGroup $_group = null;

    /**
     * @var bool|null
     */
    private ?bool $_hasChildBlocksUiElement = null;

    /**
     * @inheritdoc
     */
    public function __construct($config = [])
    {
        // `childBlocks` might be a string representing an array
        if (isset($config['childBlocks']) && !is_array($config['childBlocks'])) {
            $config['childBlocks'] = Json::decodeIfJson($config['childBlocks']);
        }

        if (!isset($config['conditions'])) {
            $config['conditions'] = [];
        }

        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    protected function defineBehaviors(): array
    {
        return [
            'fieldLayout' => [
                'class' => FieldLayoutBehavior::class,
                'elementType' => Block::class,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['id', 'fieldId', 'sortOrder'], 'number', 'integerOnly' => true],
            [['minBlocks', 'maxBlocks', 'minChildBlocks', 'maxChildBlocks'], 'integer', 'min' => 0],
        ];
    }

    /**
     * Returns the block type's handle as the string representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        return (string)$this->handle;
    }

    /**
     * Returns whether this block type is new.
     *
     * @return bool
     */
    public function getIsNew(): bool
    {
        return (!$this->id || strpos($this->id, 'new') === 0);
    }

    /**
     * Returns the Neo field associated with this block type.
     *
     * @return Field|null
     */
    public function getField(): ?Field
    {
        $fieldsService = Craft::$app->getFields();

        if (!$this->_field && $this->fieldId) {
            $this->_field = $fieldsService->getFieldById($this->fieldId);
        }

        return $this->_field;
    }

    /**
     * Returns the block type group this block type belongs to, if any.
     *
     * @return BlockTypeGroup|null
     * @since 2.13.0
     */
    public function getGroup(): ?BlockTypeGroup
    {
        if ($this->_group === null && $this->groupId !== null) {
            $this->_group = Neo::$plugin->blockTypes->getGroupById($this->groupId);
        }

        return $this->_group;
    }

    /**
     * @inheritdoc
     */
    public function getFieldContext(): string
    {
        return 'global';
    }

    /**
     * @inheritdoc
     */
    public function getEagerLoadingPrefix(): string
    {
        return $this->handle;
    }

    /**
     * Returns the block type config.
     *
     * @return array
     * @since 2.9.0
     */
    public function getConfig(): array
    {
        $group = $this->getGroup();
        $config = [
            'childBlocks' => $this->childBlocks,
            'field' => $this->getField()->uid,
            'group' => $group ? $group->uid : null,
            'handle' => $this->handle,
            'description' => $this->description,
            'minBlocks' => (int)$this->minBlocks,
            'maxBlocks' => (int)$this->maxBlocks,
            'minChildBlocks' => (int)$this->minChildBlocks,
            'maxChildBlocks' => (int)$this->maxChildBlocks,
            'maxSiblingBlocks' => (int)$this->maxSiblingBlocks,
            'name' => $this->name,
            'sortOrder' => (int)$this->sortOrder,
            'topLevel' => (bool)$this->topLevel,
            'conditions' => $this->conditions,
        ];
        $fieldLayout = $this->getFieldLayout();

        // Field layout ID might not be set even if the block type already had one -- just grab it from the block type
        $fieldLayout->id = $fieldLayout->id ?? $this->fieldLayoutId;
        $fieldLayoutConfig = $fieldLayout->getConfig();

        // No need to bother with the field layout if it has no tabs
        if ($fieldLayoutConfig !== null) {
            $fieldLayoutUid = $fieldLayout->uid ??
                ($fieldLayout->id ? Db::uidById(Table::FIELDLAYOUTS, $fieldLayout->id) : null) ??
                StringHelper::UUID();

            if (!$fieldLayout->uid) {
                $fieldLayout->uid = $fieldLayoutUid;
            }

            $config['fieldLayouts'][$fieldLayoutUid] = $fieldLayoutConfig;
        }

        return $config;
    }

    /**
     * Returns whether this block type's field layout contains the child blocks UI element.
     *
     * @return bool
     * @since 3.0.0
     */
    public function hasChildBlocksUiElement(): bool
    {
        if ($this->_hasChildBlocksUiElement !== null) {
            return $this->_hasChildBlocksUiElement;
        }

        foreach ($this->getFieldLayout()->getTabs() as $tab) {
            foreach ($tab->elements as $element) {
                if ($element instanceof ChildBlocksUiElement) {
                    return $this->_hasChildBlocksUiElement = true;
                }
            }
        }

        return $this->_hasChildBlocksUiElement = false;
    }
}
