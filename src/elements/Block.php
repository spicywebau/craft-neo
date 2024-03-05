<?php

namespace benf\neo\elements;

use benf\neo\elements\conditions\BlockCondition;
use benf\neo\elements\db\BlockQuery;
use benf\neo\Field;
use benf\neo\models\BlockType;
use benf\neo\Plugin as Neo;
use benf\neo\records\Block as BlockRecord;
use Craft;
use craft\base\BlockElementInterface;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Db;
use craft\models\FieldLayout;
use Illuminate\Support\Collection;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Class Block
 *
 * @package benf\neo\elements
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.0.0
 */
class Block extends Element implements BlockElementInterface
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('neo', "Neo Block");
    }

    /**
     * @inheritdoc
     */

    public static function lowerDisplayName(): string
    {
        return Craft::t('neo', 'Neo block');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('neo', 'Neo Blocks');
    }

    /**
     * @inheritdoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('neo', 'Neo blocks');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle(): ?string
    {
        return 'neoblock';
    }

    /**
     * @inheritdoc
     */
    public static function trackChanges(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     * @return BlockQuery
     */
    public static function find(): ElementQueryInterface
    {
        return new BlockQuery(static::class);
    }

    /**
     * @inheritdoc
     * @return BlockCondition
     */
    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(BlockCondition::class, [static::class]);
    }

    /**
     * @inheritdoc
     */
    public static function eagerLoadingMap(array $sourceElements, string $handle): array|false|null
    {
        $map = false;
        $separatedHandle = explode(':', $handle);

        if (count($separatedHandle) === 2) {
            $fieldHandle = $separatedHandle[1];
            $map = parent::eagerLoadingMap($sourceElements, $fieldHandle);
        }

        return $map;
    }

    /**
     * @inheritdoc
     */
    public static function gqlTypeNameByContext(mixed $context): string
    {
        /** @var BlockType $context */
        return $context->getField()->handle . '_' . $context->handle . '_BlockType';
    }

    /**
     * @var int|null The field ID.
     */
    public ?int $fieldId = null;

    /**
     * @var int|null
     * @since 3.0.0
     */
    public ?int $primaryOwnerId = null;

    /**
     * @var int|null The owner ID.
     */
    public ?int $ownerId = null;

    /**
     * @var int|null The block type ID.
     */
    public ?int $typeId = null;

    /**
     * @var bool
     */
    public bool $deletedWithOwner = false;

    /**
     * @var bool
     * @since 3.0.0
     */
    public bool $saveOwnership = true;

    /**
     * @var ElementInterface|null The owner.
     */
    private ?ElementInterface $_owner = null;

    /**
     * @var array|null Any eager-loaded elements for this block type.
     */
    private ?array $_eagerLoadedBlockTypeElements = null;

    /**
     * @var bool|null Whether this block should display as collapsed.
     */
    public ?bool $_collapsed = null;

    /**
     * @var int|null
     * @since 2.7.0
     */
    public ?int $sortOrder = null;

    /**
     * @var int|null
     * @since 2.7.0
     */
    public ?int $oldLevel = null;

    /**
     * @var bool Whether the block has changed.
     * @internal
     * @since 2.6.0
     */
    public bool $dirty = false;

    /**
     * @var int|null If this is an unsaved block, what the `X` in the `newX` placeholder should be.
     * @internal
     * @since 3.3.4
     */
    public ?int $unsavedId = null;

    /**
     * @var Block[]|null All blocks belonging to the same field as this one.
     */
    private ?array $_allElements = null;

    /**
     * @var BlockQuery[] Live queries for relatives of this block in live preview mode.
     */
    private array $_liveQueries = [];

    /**
     * @var bool Whether to operate on a memoized data set.
     */
    private bool $_useMemoized = false;

    /**
     * @var Field|null The Neo field associated with this block.
     */
    private ?Field $_field = null;

    /**
     * @inheritdoc
     */
    public function attributes(): array
    {
        $names = parent::attributes();
        $names[] = 'owner';

        return $names;
    }

    /**
     * @inheritdoc
     */
    public function extraFields(): array
    {
        $names = parent::extraFields();
        $names[] = 'owner';
        $names[] = 'type';

        return $names;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['fieldId', 'primaryOwnerId', 'typeId', 'sortOrder'], 'number', 'integerOnly' => true];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function validate($attributeNames = null, $clearErrors = true): bool
    {
        $validates = parent::validate($attributeNames, $clearErrors);

        if ($this->getScenario() === Element::SCENARIO_LIVE) {
            // Check the block type's min/max child blocks
            $blockType = $this->getType();
            $childCount = $this->getChildren()->count();
            $minChildBlocks = $blockType->minChildBlocks;
            $maxChildBlocks = $blockType->maxChildBlocks;

            if ($childCount < $minChildBlocks) {
                $validates = false;
                $this->addError(
                    '__NEO_MIN_CHILD_BLOCKS__',
                    Craft::t('neo', 'Blocks of type {type} must have at least {min, number} child {min, plural, one{block} other{blocks}}.', [
                        'type' => Craft::t('site', $blockType->name),
                        'min' => $minChildBlocks,
                    ])
                );
            }

            if ($maxChildBlocks > 0 && $childCount > $maxChildBlocks) {
                $validates = false;
                $this->addError(
                    '__NEO_MAX_CHILD_BLOCKS__',
                    Craft::t('neo', 'Blocks of type {type} must have at most {max, number} child {max, plural, one{block} other{blocks}}.', [
                        'type' => Craft::t('site', $blockType->name),
                        'max' => $maxChildBlocks,
                    ])
                );
            }

            // Check whether the block type is at the top level and allowed to exist there
            if ($this->level == 1 && !$blockType->topLevel) {
                $validates = false;
                $this->addError(
                    '__NEO_TOP_LEVEL__',
                    Craft::t('neo', 'Blocks of type {type} are not allowed at the top level.', [
                        'type' => Craft::t('site', $blockType->name),
                    ])
                );
            }

            // Check whether this block is a valid child block for its parent
            if (($parent = $this->getParent()) !== null) {
                $parentBlockType = $parent->getType();
                $parentAllowedChildTypes = $parentBlockType->childBlocks;

                if (
                    empty($parentAllowedChildTypes) ||
                    is_array($parentAllowedChildTypes) && !in_array($blockType->handle, $parentAllowedChildTypes)
                ) {
                    $validates = false;
                    $this->addError(
                        '__NEO_CHILD_BLOCKS__',
                        Craft::t('neo', 'Blocks of type {childType} are not allowed as children of blocks of type {parentType}.', [
                            'childType' => Craft::t('site', $blockType->name),
                            'parentType' => Craft::t('site', $parentBlockType->name),
                        ])
                    );
                }
            }
        }

        return $validates;
    }

    /**
     * @inheritdoc
     */
    public function getCanonical(bool $anySite = false): ElementInterface
    {
        // Element::getCanonical() will fail to find a Neo block's canonical block because it sets the structure ID on
        // the element query, but the canonical block belongs to a different structure, so let's try it without setting
        // the structure ID
        $canonical = $this->getIsCanonical() ? null : Block::find()
            ->id($this->getCanonicalId())
            ->siteId($anySite ? '*' : $this->siteId)
            ->preferSites([$this->siteId])
            ->unique()
            ->status(null)
            ->ignorePlaceholders()
            ->one();

        if ($canonical !== null) {
            $this->setCanonical($canonical);
        }

        return $canonical ?? $this;
    }

    /**
     * @inheritdoc
     */
    public function getSupportedSites(): array
    {
        try {
            $owner = $this->getOwner() ?? $this->duplicateOf;
        } catch (InvalidConfigException $e) {
            $owner = $this->duplicateOf;
        }

        if (!$owner) {
            return [Craft::$app->getSites()->getPrimarySite()->id];
        }

        $field = $this->getField();
        return Neo::$plugin->fields->getSupportedSiteIds($field->propagationMethod, $owner, $field->propagationKeyFormat);
    }

    /**
     * @inheritdoc
     * @since 2.9.0
     */
    public function getCacheTags(): array
    {
        return [
            "field-owner:$this->fieldId-$this->primaryOwnerId",
            "field:$this->fieldId",
            "owner:$this->primaryOwnerId",
        ];
    }

    /**
     * @inheritdoc
     */
    public function getLocalized(): ElementQueryInterface|Collection
    {
        $query = parent::getLocalized();

        if ($query instanceof BlockQuery && $this->ownerId !== null) {
            // Maintain the same ownerId for queried blocks
            $query->ownerId($this->ownerId);
        }

        return $query;
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout(): ?FieldLayout
    {
        return parent::getFieldLayout() ?? $this->getType()->getFieldLayout();
    }

    /**
     * Returns this block's type.
     *
     * @return BlockType
     * @throws InvalidConfigException if this block's block type ID is invalid.
     */
    public function getType(): BlockType
    {
        if ($this->typeId === null) {
            throw new InvalidConfigException('Neo block is missing its type ID');
        }

        $blockType = Neo::$plugin->blockTypes->getById($this->typeId);

        if (!$blockType) {
            throw new InvalidConfigException('Invalid Neo block ID: ' . $this->typeId);
        }

        return $blockType;
    }

    /**
     * Returns this block's owner, if it has one.
     *
     * @return ElementInterface|null
     * @throws
     */
    public function getOwner(): ?ElementInterface
    {
        if ($this->_owner === null) {
            if ($this->ownerId === null) {
                throw new InvalidConfigException('Neo block is missing its owner ID');
            }

            if (($this->_owner = Craft::$app->getElements()->getElementById($this->ownerId, null, $this->siteId)) === null) {
                // The owner has been deleted
                return null;
            }
        }

        return $this->_owner;
    }

    /**
     * Sets this block's owner.
     *
     * @param ElementInterface|null $owner
     */
    public function setOwner(?ElementInterface $owner = null)
    {
        $this->_owner = $owner;
        $this->ownerId = $owner->id;
    }

    /**
     * Returns whether this block is collapsed.
     *
     * @return bool|null
     */
    public function getCollapsed()
    {
        $cacheService = Craft::$app->getCache();

        $collapsed = $this->_collapsed;

        if (!is_bool($collapsed)) {
            if ($this->id) {
                $cacheKey = "neoblock-$this->id-collapsed";
                $collapsed = $cacheService->exists($cacheKey);
                $this->_collapsed = $collapsed;
            } else {
                $collapsed = false;
            }
        }

        return $collapsed;
    }

    /**
     * Sets this block's collapsed state.
     *
     * @param bool $value Whether or not this block should be collapsed.
     */
    public function setCollapsed(bool $value)
    {
        $this->_collapsed = $value;
    }

    /**
     * Sets this block's collapsed state in the Craft CMS cache.
     */
    public function cacheCollapsed(): void
    {
        $cacheService = Craft::$app->getCache();

        if (is_bool($this->_collapsed) && $this->id) {
            $cacheKey = "neoblock-$this->id-collapsed";

            if ($this->_collapsed) {
                $cacheService->add($cacheKey, 1);
            } else {
                $cacheService->delete($cacheKey);
            }
        }
    }

    /**
     * Removes this block's collapsed state from the Craft CMS cache.
     */
    public function forgetCollapsed(): void
    {
        $cacheService = Craft::$app->getCache();

        if ($this->id) {
            $cacheKey = "neoblock-$this->id-collapsed";
            $cacheService->delete($cacheKey);
        }
    }

    /**
     * @inheritdoc
     */
    public function hasEagerLoadedElements(string $handle): bool
    {
        $typeHandlePrefix = $this->_getTypeHandlePrefix();
        $typeElementHandle = $typeHandlePrefix . $handle;
        $hasEagerLoadedElements = isset($this->_eagerLoadedBlockTypeElements[$typeElementHandle]);

        if ($hasEagerLoadedElements) {
            return true;
        }

        return parent::hasEagerLoadedElements($handle);
    }

    /**
     * @inheritdoc
     */
    public function getEagerLoadedElements(string $handle): ?Collection
    {
        $blockTypeHandle = $this->_getTypeHandlePrefix() . $handle;

        if (isset($this->_eagerLoadedBlockTypeElements[$blockTypeHandle])) {
            return new Collection($this->_eagerLoadedBlockTypeElements[$blockTypeHandle]);
        }

        return parent::getEagerLoadedElements($handle);
    }

    /**
     * @inheritdoc
     */
    public function setEagerLoadedElements(string $handle, array $elements): void
    {
        $typeHandlePrefix = $this->_getTypeHandlePrefix();
        $hasMatchingHandlePrefix = strpos($handle, $typeHandlePrefix) === 0;

        if ($hasMatchingHandlePrefix) {
            $this->_eagerLoadedBlockTypeElements[$handle] = $elements;
        } else {
            parent::setEagerLoadedElements($handle, $elements);
        }
    }

    /**
     * @inheritdoc
     */
    public function getGqlTypeName(): string
    {
        return static::gqlTypeNameByContext($this->getType());
    }

    /**
     * @inheritdoc
     */
    public function beforeSave(bool $isNew): bool
    {
        if (!$this->primaryOwnerId && !$this->ownerId) {
            throw new InvalidConfigException('No owner ID assigned to the Neo block.');
        }

        return parent::beforeSave($isNew);
    }

    /**
     * @inheritdoc
     * @throws Exception if the block ID is invalid.
     */
    public function afterSave(bool $isNew): void
    {
        $record = null;

        if (!$this->propagating) {
            $this->primaryOwnerId = $this->primaryOwnerId ?? $this->ownerId;
            $this->ownerId = $this->ownerId ?? $this->primaryOwnerId;

            if ($isNew) {
                $record = new BlockRecord();
                $record->id = (int)$this->id;
            } else {
                $record = BlockRecord::findOne($this->id);

                if (!$record) {
                    throw new Exception('Invalid Neo block ID: ' . $this->id);
                }
            }

            $record->fieldId = (int)$this->fieldId;
            $record->primaryOwnerId = $this->primaryOwnerId ?? $this->ownerId;
            $record->typeId = (int)$this->typeId;

            $record->save(false);

            // ownerId will be null when creating a revision
            if ($this->saveOwnership) {
                if ($isNew) {
                    Db::insert('{{%neoblocks_owners}}', [
                        'blockId' => $this->id,
                        'ownerId' => $this->ownerId,
                        'sortOrder' => $this->sortOrder ?? 0,
                    ]);
                } else {
                    Db::update('{{%neoblocks_owners}}', [
                        'sortOrder' => $this->sortOrder ?? 0,
                    ], [
                        'blockId' => $this->id,
                        'ownerId' => $this->ownerId,
                    ]);
                }
            }
        }

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function beforeDelete(): bool
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        // Update this block's DB row with whether it was deleted with its owner element
        Craft::$app->getDb()
            ->createCommand()
            ->update('{{%neoblocks}}', [
                'deletedWithOwner' => $this->deletedWithOwner,
            ], ['id' => $this->id], [], false)
            ->execute();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function afterDelete(): void
    {
        // Remove this block's collapsed state from the cache
        $this->forgetCollapsed();

        // If the block was hard-deleted, make sure its row in the `neoblocks` table is deleted, if it wasn't already
        if ($this->hardDelete) {
            Db::delete('{{%neoblocks}}', [
                'id' => $this->id,
            ]);
        }

        parent::afterDelete();
    }

    /**
     * Allows memoizing all blocks (including this one) for a particular field.
     * This is used for Live Preview mode, where certain methods, like `getAncestors`, create block queries which need
     * a local set of blocks to query against.
     *
     * @param array $elements
     */
    public function setAllElements(array $elements): void
    {
        $this->_allElements = $elements;

        // Update the elements across any memoized block queries
        foreach ($this->_liveQueries as $name => $query) {
            $query->useMemoized($this->isUsingMemoized());
            $query->setAllElements($this->_allElements);
        }
    }

    /**
     * Whether block queries operate on a memoized data set.
     *
     * @return bool
     */
    public function isUsingMemoized(): bool
    {
        return $this->_useMemoized;
    }

    /**
     * Sets whether block queries operate on a memoized data set.
     *
     * @param bool|Block[]|Collection $use - Either a boolean to enable/disable, or a dataset to use (which results in enabling)
     */
    public function useMemoized(bool|array|Collection $use = true): void
    {
        if (is_array($use)) {
            $this->setAllElements($use);
            $use = true;
        } elseif ($use instanceof Collection) {
            $this->setAllElements($use->all());
            $use = true;
        }

        $this->_useMemoized = $use;
    }

    /**
     * Whether current view is a draft or not
     *
     * @return bool
     */
    public function isDraftPreview(): bool
    {
        // if console request then ignore
        if (Craft::$app->request->getIsConsoleRequest()) {
            return false;
        }

        // get token
        $token = Craft::$app->request->getParam('token');

        if (!empty($token)) {
            // get the route of the token
            $route = Craft::$app->tokens->getTokenRoute($token);

            // check it's a shared entry
            if ($route && $route[0] == 'entries/view-shared-entry') {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function getAncestors(?int $dist = null): ElementQueryInterface|Collection
    {
        // If the request is in Live Preview mode, use the Neo-extended block query, which supports Live Preview mode
        $isLivePreview = Craft::$app->getRequest()->getIsLivePreview();
        $hasLocalElements = isset($this->_allElements);
        $isUsingMemoized = $this->isUsingMemoized();
        $isDraftPreview = $this->isDraftPreview();

        if (($isLivePreview && $hasLocalElements) || $isUsingMemoized || $isDraftPreview) {
            if (!isset($this->_liveQueries['ancestors'])) {
                $query = $this->_getBaseRelativeQuery();
                $query->ancestorOf = $this;
                $query->useMemoized($this->_allElements);

                $this->_liveQueries['ancestors'] = $query;
            }

            if ($dist) {
                $query = $this->_liveQueries['ancestors']->ancestorDist($dist);
                $query->useMemoized($this->_allElements);

                return $query;
            }

            return $this->_liveQueries['ancestors'];
        }

        return parent::getAncestors($dist);
    }

    /**
     * @inheritdoc
     */
    public function getParent(): ?ElementInterface
    {
        // If the request is in Live Preview mode, use the Neo-extended block query, which supports Live Preview mode
        $isLivePreview = Craft::$app->getRequest()->getIsLivePreview();
        $hasLocalElements = isset($this->_allElements);
        $isUsingMemoized = $this->isUsingMemoized();
        $isDraftPreview = $this->isDraftPreview();

        if (($isLivePreview && $hasLocalElements) || $isUsingMemoized || $isDraftPreview) {
            if (!isset($this->_liveQueries['parent'])) {
                $query = $this->_getBaseRelativeQuery();
                $query->ancestorOf = $this;
                $query->ancestorDist = 1;
                $query->useMemoized($this->_allElements);

                $this->_liveQueries['parent'] = $query;
            }

            return $this->_liveQueries['parent']->one();
        }

        return parent::getParent();
    }

    /**
     * @inheritdoc
     */
    public function getDescendants(?int $dist = null): ElementQueryInterface|Collection
    {
        // If the request is in Live Preview mode, use the Neo-extended block query, which supports Live Preview mode
        $isLivePreview = Craft::$app->getRequest()->getIsLivePreview();
        $hasLocalElements = isset($this->_allElements);
        $isUsingMemoized = $this->isUsingMemoized();
        $isDraftPreview = $this->isDraftPreview();

        if (($isLivePreview && $hasLocalElements) || $isUsingMemoized || $isDraftPreview) {
            if (!isset($this->_liveQueries['descendants'])) {
                $query = $this->_getBaseRelativeQuery();
                $query->descendantOf = $this;
                $query->useMemoized($this->_allElements);

                $this->_liveQueries['descendants'] = $query;
            }

            if ($dist) {
                $query = $this->_liveQueries['descendants']->descendantDist($dist);
                $query->useMemoized($this->_allElements);

                return $query;
            }

            return $this->_liveQueries['descendants'];
        }

        // As of Craft 3.7, `Element::getDescendants()` looks for descendants of the canonical element. If this Neo
        // block belongs to an entry draft or revision, its canonical block on the live entry belongs to a different
        // block structure, so the query would return no results. We need to ensure we do look for descendants of *this*
        // block so we get the child blocks.
        $descendants = parent::getDescendants($dist);

        return is_array($descendants) ? $descendants : $descendants->descendantOf($this);
    }

    /**
     * @inheritdoc
     */
    public function getChildren(): ElementQueryInterface|Collection
    {
        // If the request is in Live Preview mode, use the Neo-extended block query, which supports Live Preview mode
        $isLivePreview = Craft::$app->getRequest()->getIsLivePreview();
        $hasLocalElements = isset($this->_allElements);
        $isUsingMemoized = $this->isUsingMemoized();
        $isDraftPreview = $this->isDraftPreview();

        if (($isLivePreview && $hasLocalElements) || $isUsingMemoized || $isDraftPreview) {
            if (!isset($this->_liveQueries['children'])) {
                $query = $this->_getBaseRelativeQuery();
                $query->descendantOf = $this;
                $query->descendantDist = 1;
                $query->useMemoized($this->_allElements);

                $this->_liveQueries['children'] = $query;
            }

            return $this->_liveQueries['children'];
        }

        // As of Craft 3.7, `Element::getChildren()` looks for descendants of the canonical element. If this Neo block
        // belongs to an entry draft or revision, its canonical block on the live entry belongs to a different block
        // structure, so the query would return no results. We need to ensure we do look for descendants of *this* block
        // so we get the child blocks.
        $children = parent::getChildren();

        return is_array($children) ? $children : $children->descendantOf($this);
    }

    /**
     * @inheritdoc
     */
    public function getSiblings(): ElementQueryInterface|Collection
    {
        // If the request is in Live Preview mode, use the Neo-extended block query, which supports Live Preview mode
        $isLivePreview = Craft::$app->getRequest()->getIsLivePreview();
        $hasLocalElements = isset($this->_allElements);
        $isUsingMemoized = $this->isUsingMemoized();
        $isDraftPreview = $this->isDraftPreview();

        if (($isLivePreview && $hasLocalElements) || $isUsingMemoized || $isDraftPreview) {
            if (!isset($this->_liveQueries['siblings'])) {
                $query = $this->_getBaseRelativeQuery();
                $query->siblingOf = $this;
                $query->useMemoized($this->_allElements);

                $this->_liveQueries['siblings'] = $query;
            }

            return $this->_liveQueries['siblings'];
        }

        return parent::getSiblings();
    }

    /**
     * @inheritdoc
     */
    public function getPrevSibling(): ?ElementInterface
    {
        // If the request is in Live Preview mode, use the Neo-extended block query, which supports Live Preview mode
        $isLivePreview = Craft::$app->getRequest()->getIsLivePreview();
        $hasLocalElements = isset($this->_allElements);
        $isUsingMemoized = $this->isUsingMemoized();
        $isDraftPreview = $this->isDraftPreview();

        if (($isLivePreview && $hasLocalElements) || $isUsingMemoized || $isDraftPreview) {
            if (!isset($this->_liveQueries['prevSibling'])) {
                $query = $this->_getBaseRelativeQuery();
                $query->prevSiblingOf = $this;
                $query->useMemoized($this->_allElements);

                $this->_liveQueries['prevSibling'] = $query;
            }

            return $this->_liveQueries['prevSibling']->one();
        }

        return parent::getPrevSibling();
    }

    /**
     * @inheritdoc
     */
    public function getNextSibling(): ?ElementInterface
    {
        // If the request is in Live Preview mode, use the Neo-extended block query, which supports Live Preview mode
        $isLivePreview = Craft::$app->getRequest()->getIsLivePreview();
        $hasLocalElements = isset($this->_allElements);
        $isUsingMemoized = $this->isUsingMemoized();
        $isDraftPreview = $this->isDraftPreview();

        if (($isLivePreview && $hasLocalElements) || $isUsingMemoized || $isDraftPreview) {
            if (!isset($this->_liveQueries['nextSibling'])) {
                $query = $this->_getBaseRelativeQuery();
                $query->nextSiblingOf = $this;
                $query->useMemoized($this->_allElements);

                $this->_liveQueries['nextSibling'] = $query;
            }

            return $this->_liveQueries['nextSibling']->one();
        }

        return parent::getNextSibling();
    }

    /**
     * Returns the Neo field associated with this block.
     *
     * @return Field
     */
    public function getField(): Field
    {
        if (!$this->_field) {
            $this->_field = Craft::$app->getFields()->getFieldById($this->fieldId);
        }

        return $this->_field;
    }

    /**
     * Returns a basic query for any blocks that are relatives of this block.
     *
     * @return BlockQuery
     */
    private function _getBaseRelativeQuery(): BlockQuery
    {
        $query = Block::find();
        $query->fieldId($this->fieldId);
        $query->ownerId($this->ownerId);
        $query->siteId($this->siteId);
        $query->limit(null);
        $query->status('enabled');
        $query->indexBy('id');

        return $query;
    }

    /**
     * Returns the block type handle in the form of a prefix for finding fields belonging to this block's type.
     *
     * @return string
     */
    private function _getTypeHandlePrefix(): string
    {
        $type = $this->getType();
        $typeHandlePrefix = $type->handle . ':';

        return $typeHandlePrefix;
    }
}
