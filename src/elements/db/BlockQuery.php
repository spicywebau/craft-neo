<?php

namespace benf\neo\elements\db;

use benf\neo\elements\Block;
use benf\neo\models\BlockType;
use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\db\Table;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use Illuminate\Support\Collection;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\base\Model as BaseModel;
use yii\db\Connection;

/**
 * Class BlockQuery
 *
 * @package benf\neo\elements\db
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.0.0
 */
class BlockQuery extends ElementQuery
{
    // Public properties
    /**
     * @var int|int[]|null The field ID(s) to query for.
     */
    public int|array|null $fieldId = null;

    /**
     * @var int|int[]|null The primary owner ID(s) to query for.
     * @since 3.0.0
     */
    public int|array|null $primaryOwnerId = null;

    /**
     * @var int|int[]|null The owner ID(s) to query for.
     */
    public int|array|null $ownerId = null;

    /**
     * @var int|int[]|null The block type ID(s) to query for.
     */
    public int|array|null $typeId = null;

    /**
     * @var bool|null Whether the owner elements can be drafts.
     * @since 2.9.7
     */
    public ?bool $allowOwnerDrafts = null;

    /**
     * @var bool|null Whether the owner elements can be revisions.
     * @since 2.9.7
     */
    public ?bool $allowOwnerRevisions = null;

    // Protected properties

    /**
     * @inheritdoc
     */
    protected array $defaultOrderBy = ['neoblocks_owners.sortOrder' => SORT_ASC];

    // Private properties

    /**
     * @var Block[]|null The block data to be filtered in live preview mode.
     */
    private ?array $_allElements = null;

    /**
     * @var bool Whether to operate on a memoized data set.
     */
    private bool $_useMemoized = false;

    private static $ownersById = [];

    // Public methods

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        match ($name) {
            'field' => $this->field($value),
            'owner' => $this->owner($value),
            'primaryOwner' => $this->primaryOwner($value),
            'type' => $this->type($value),
            default => parent::__set($name, $value),
        };
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->withStructure = true;
        parent::init();
    }

    /**
     * Filters the query results based on the field.
     *
     * @param string[]|Field|null $value An array of Neo field handles, a Neo field instance, or `null`
     * @return $this
     * @since 2.13.5
     */
    public function field($value)
    {
        $this->fieldId = !$value ? null : ($value instanceof Field ? [$value->id] : (new Query())
            ->select(['id'])
            ->from([Table::FIELDS])
            ->where(Db::parseParam('handle', $value))
            ->andWhere(['type' => Field::class])
            ->column());

        return $this;
    }

    /**
     * Filters the query results based on the field ID.
     *
     * @param int|int[]|null $value The field ID(s).
     * @return self
     */
    public function fieldId(int|array|null $value): self
    {
        $this->fieldId = $value;

        return $this;
    }

    /**
     * Filters the query results based on the primary owner ID.
     *
     * @param int|int[]|null $value
     * @return self
     * @since 3.0.0
     */
    public function primaryOwnerId(int|array|null $value): self
    {
        $this->primaryOwnerId = $value;
        return $this;
    }

    /**
     * Filters the query results based on the primary owner.
     *
     * @param ElementInterface $value
     * @return self
     * @since 3.0.0
     */
    public function primaryOwner(ElementInterface $primaryOwner): self
    {
        $this->primaryOwnerId = [$primaryOwner->id];
        $this->siteId = $primaryOwner->siteId;
        return $this;
    }

    /**
     * Filters the query results based on the owner ID.
     *
     * @param int|int[]|null $value The owner ID(s).
     * @return self
     */
    public function ownerId(int|array|null $value): self
    {
        $this->ownerId = $value;

        return $this;
    }

    /**
     * Filters the query results based on the owner.
     *
     * @param ElementInterface $owner
     * @return self
     */
    public function owner(ElementInterface $owner): self
    {
        $this->ownerId = $owner->id;
        $this->siteId = $owner->siteId;

        return $this;
    }

    /**
     * Filters the query results based on the block type.
     *
     * @param BlockType|string[]|string|null The block type(s) to set
     * @return self
     */
    public function type(BlockType|array|string|null $value): self
    {
        if ($value instanceof BlockType) {
            $this->typeId = $value->id;
        } else {
            if ($value !== null) {
                $this->typeId = (new Query())
                    ->select(['neoblocktypes.id'])
                    ->from(['{{%neoblocktypes}} neoblocktypes'])
                    ->where(Db::parseParam('neoblocktypes.handle', $value))
                    ->column();
            } else {
                $this->typeId = null;
            }
        }

        return $this;
    }

    /**
     * Filters the query results based on the block type IDs.
     *
     * @param int|int[]|null $value The block type ID(s).
     * @return self
     */
    public function typeId(int|array|null $value): self
    {
        $this->typeId = $value;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function count($q = '*', $db = null): string|int|bool|null
    {
        return $this->_setFilteredResultIfUsingMemoized(fn() => parent::count($q, $db));
    }

    /**
     * @inheritdoc
     * @return Block[]|array
     */
    public function all($db = null): array
    {
        return $this->_setFilteredResultIfUsingMemoized(fn() => parent::all($db));
    }

    /**
     * @inheritdoc
     * @return Block|array|null
     */
    public function one($db = null): BaseModel|array|null
    {
        return $this->_setFilteredResultIfUsingMemoized(fn() => parent::one($db));
    }

    /**
     * @inheritdoc
     */
    public function exists($db = null): bool
    {
        $isUsingMemoized = $this->isUsingMemoized() && isset($this->_allElements);
        return $this->_setFilteredResultIfUsingMemoized(
            fn() => (!$isUsingMemoized || !empty($this->getCachedResult())) && parent::exists($db)
        );
    }

    /**
     * @inheritdoc
     * @return Block|array|null
     */
    public function nth(int $n, ?Connection $db = null): BaseModel|array|null
    {
        return $this->_setFilteredResultIfUsingMemoized(fn() => parent::nth($n, $db));
    }

    /**
     * @inheritdoc
     */
    public function ids(?Connection $db = null): array
    {
        return $this->isUsingMemoized() && isset($this->_allElements)
            ? array_map(fn($block) => $block->id, $this->all())
            : parent::ids($db);
    }

    /**
     * Sets all the elements (blocks) to be filtered against in Live Preview mode.
     * This becomes the main data source for Live Preview, instead of the database.
     *
     * @param Block[] $elements
     */
    public function setAllElements(array $elements): void
    {
        $this->_allElements = $elements;
    }

    /**
     * Whether the block query is operating on a memoized data set.
     *
     * @return bool
     */
    public function isUsingMemoized(): bool
    {
        return $this->_useMemoized;
    }

    /**
     * Sets whether the block query operates on a memoized data set.
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
     * Narrows the query results based on whether the Neo blocks owners are drafts.
     *
     * @param bool|null $value The property value
     * @return self
     * @since 2.9.7
     */
    public function allowOwnerDrafts(?bool $value = true): self
    {
        $this->allowOwnerDrafts = $value;
        return $this;
    }

    /**
     * Narrows the query results based on whether the Neo blocks owners are revisions.
     *
     * @param bool|null $value The property value
     * @return self
     * @since 2.9.7
     */
    public function allowOwnerRevisions(?bool $value = true): self
    {
        $this->allowOwnerRevisions = $value;
        return $this;
    }

    // Protected methods

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->fieldId = $this->_normalizeProp('fieldId');

        try {
            $this->primaryOwnerId = $this->_normalizeProp('primaryOwnerId');
        } catch (InvalidArgumentException) {
            throw new InvalidConfigException('Invalid primaryOwnerId param value');
        }

        try {
            $this->ownerId = $this->_normalizeProp('ownerId');
        } catch (InvalidArgumentException) {
            throw new InvalidConfigException('Invalid ownerId param value');
        }

        $this->joinElementTable('neoblocks');

        $ownersCondition = [
            'and',
            '[[neoblocks_owners.blockId]] = [[elements.id]]',
            $this->ownerId ? ['neoblocks_owners.ownerId' => $this->ownerId] : '[[neoblocks_owners.ownerId]] = [[neoblocks.primaryOwnerId]]',
        ];

        $this->query->innerJoin(['neoblocks_owners' => '{{%neoblocks_owners}}'], $ownersCondition);
        $this->subQuery->innerJoin(['neoblocks_owners' => '{{%neoblocks_owners}}'], $ownersCondition);

        $this->query->addSelect([
            'neoblocks.fieldId',
            'neoblocks.primaryOwnerId',
            'neoblocks.typeId',
            'neoblocks_owners.ownerId',
            'neoblocks_owners.sortOrder',
        ]);

        if ($this->fieldId) {
            $this->subQuery->andWhere(['neoblocks.fieldId' => $this->fieldId]);
        }

        if ($this->primaryOwnerId) {
            $this->subQuery->andWhere(['neoblocks.primaryOwnerId' => $this->primaryOwnerId]);
        }

        if ($this->typeId !== null) {
            // If typeId is an empty array, it's because type() was called but no valid type handles were passed in
            if (is_array($this->typeId) && empty($this->typeId)) {
                return false;
            }

            $this->subQuery->andWhere(Db::parseNumericParam('neoblocks.typeId', $this->typeId));
        }

        // Ignore revision/draft blocks by default
        $allowOwnerDrafts = $this->allowOwnerDrafts ?? ($this->id || $this->primaryOwnerId || $this->ownerId || $this->_isDraftRequest());
        $allowOwnerRevisions = $this->allowOwnerRevisions ?? ($this->id || $this->primaryOwnerId || $this->ownerId || $this->_isRevisionRequest());

        if (!$allowOwnerDrafts || !$allowOwnerRevisions) {
            $this->subQuery->innerJoin(
                ['owners' => Table::ELEMENTS],
                $this->ownerId ? '[[owners.id]] = [[neoblocks_owners.ownerId]]' : '[[owners.id]] = [[neoblocks.primaryOwnerId]]'
            );

            if (!$allowOwnerDrafts) {
                $this->subQuery->andWhere(['owners.draftId' => null]);
            }

            if (!$allowOwnerRevisions) {
                $this->subQuery->andWhere(['owners.revisionId' => null]);
            }
        }

        if ($this->status !== null) {
            // Inner join the `neoblocktypes` table, so we can pretend blocks with disabled block types are disabled
            $this->subQuery->innerJoin(
                ['neoblocktypes' => '{{%neoblocktypes}}'],
                '[[neoblocktypes.id]] = [[neoblocks.typeId]]',
            );
        }

        return parent::beforePrepare();
    }

    /**
     * @inheritdoc
     */
    protected function afterPrepare(): bool
    {
        // Try to narrow down the structure criteria for the blocks we need, so we don't return duplicate blocks because
        // they belong to a draft as well as the draft's canonical element
        if (!$this->trashed && !$this->revisions && $this->withStructure && !$this->structureId) {
            $this->subQuery->innerJoin(
                ['neoblockstructures' => '{{%neoblockstructures}}'],
                '[[neoblockstructures.structureId]] = [[structureelements.structureId]]',
            );

            if ($this->fieldId) {
                $this->subQuery->andWhere(['neoblockstructures.fieldId' => $this->fieldId]);
            }

            if ($this->ownerId) {
                $this->subQuery->andWhere(['neoblockstructures.ownerId' => $this->ownerId]);
            }

            if ($this->siteId) {
                $this->subQuery->andWhere(['neoblockstructures.siteId' => $this->siteId]);
            }
        }

        return parent::afterPrepare();
    }

    /**
     * @inheritdoc
     * @since 2.9.0
     */
    protected function cacheTags(): array
    {
        $tags = [];

        if ($this->fieldId && $this->primaryOwnerId) {
            foreach ($this->fieldId as $fieldId) {
                foreach ($this->primaryOwnerId as $primaryOwnerId) {
                    $tags[] = "field-owner:$fieldId-$primaryOwnerId";
                }
            }
        } else {
            if ($this->fieldId) {
                foreach ($this->fieldId as $fieldId) {
                    $tags[] = "field:$fieldId";
                }
            }
            if ($this->primaryOwnerId) {
                foreach ($this->primaryOwnerId as $primaryOwnerId) {
                    $tags[] = "owner:$primaryOwnerId";
                }
            }
        }

        return $tags;
    }

    /**
     * @inheritdoc
     */
    protected function statusCondition(string $status): mixed
    {
        // TODO: remove this in Neo 4
        if (!Craft::$app->getDb()->columnExists('{{%neoblocktypes}}', 'enabled')) {
            return parent::statusCondition($status);
        }

        return match ($status) {
            Element::STATUS_ENABLED => [
                'elements.enabled' => true,
                'elements_sites.enabled' => true,
                'neoblocktypes.enabled' => true,
            ],
            Element::STATUS_DISABLED => [
                'or',
                ['elements.enabled' => false],
                ['elements_sites.enabled' => false],
                ['neoblocktypes.enabled' => false],
            ],
            default => false,
        };
    }

    // Private methods

    private function _setFilteredResultIfUsingMemoized(callable $resultFunction): mixed
    {
        $isUsingMemoized = $this->isUsingMemoized() && isset($this->_allElements);
        $notAlreadyCached = $this->getCachedResult() === null;

        if ($isUsingMemoized && $notAlreadyCached) {
            $this->setCachedResult($this->_getFilteredResult());
        }

        $result = $resultFunction();

        if ($isUsingMemoized && $notAlreadyCached) {
            $this->clearCachedResult();
        }

        return $result;
    }

    private function _tokenRouteHasProp(string $prop): bool
    {
        $request = Craft::$app->getRequest();
        $token = !$request->getIsConsoleRequest() ? $request->getParam('token') : '';
        $route = !empty($token) ? Craft::$app->tokens->getTokenRoute($token) : null;

        return $route && isset($route[1][$prop]) && $route[1][$prop] !== null;
    }

    private function _ownerElementHasProp(string $prop): bool
    {
        $ownerId = null;

        if (is_numeric($this->ownerId)) {
            $ownerId = $this->ownerId;
        } elseif ($this->descendantOf instanceof Block) {
            $ownerId = $this->descendantOf->ownerId;
        } elseif ($this->ancestorOf instanceof Block) {
            $ownerId = $this->ancestorOf->ownerId;
        }

        if ($ownerId === null) {
            return false;
        }

        if (!isset(self::$ownersById[$ownerId])) {
            self::$ownersById[$ownerId] = Craft::$app->getElements()->getElementById($ownerId, null, $this->siteId);
        }

        $owner = self::$ownersById[$ownerId];

        if ($owner === null) {
            throw new InvalidConfigException('Invalid Neo block owner ID: ' . $ownerId);
        }

        return property_exists($owner, $prop) && $owner->$prop !== null;
    }

    /**
     * Whether the current request is a draft.
     *
     * @return bool
     */
    private function _isDraftRequest(): bool
    {
        return Craft::$app->getRequest()->getIsPreview()
            || $this->_tokenRouteHasProp('draftId')
            || $this->_ownerElementHasProp('draftId');
    }

    /**
     * Whether the current request is a revision.
     *
     * @return bool
     */
    private function _isRevisionRequest(): bool
    {
        return $this->_tokenRouteHasProp('revisionId')
            || $this->_ownerElementHasProp('revisionId');
    }

    /**
     * Converts a property into an array if it's numeric, or null if it's empty.
     *
     * @param string $prop The property to convert.
     * @throws InvalidArgumentException if the property doesn't exist.
     */
    private function _normalizeProp(string $prop): ?array
    {
        if (!property_exists($this, $prop)) {
            throw new InvalidArgumentException('Tried to access invalid Neo block query property ' . $prop);
        }

        if (empty($this->$prop)) {
            return null;
        }

        if (is_numeric($this->$prop)) {
            return [$this->$prop];
        }

        return $this->$prop;
    }

    /**
     * Returns whether this block query is in live preview or eager loading mode.
     *
     * @return bool
     */
    private function _isLivePreviewOrEagerLoading(): bool
    {
        return $this->isUsingMemoized() && isset($this->_allElements);
    }

    /**
     * Returns the filtered blocks in live preview mode.
     *
     * @return Block[]
     */
    private function _getFilteredResult(): array
    {
        $result = $this->_allElements ?? [];
        $originalIds = array_map(fn($block) => $block->id ?? -$block->unsavedId, $result);
        $criteria = $this->getCriteria();

        foreach (['limit', 'offset'] as $limitParam) {
            if ($this->$limitParam) {
                $criteria[$limitParam] = $this->$limitParam;
            }
        }

        foreach ($criteria as $param => $value) {
            $method = '___' . $param;

            if (method_exists($this, $method)) {
                $currentFiltered = $this->$method($this->_allElements, $value);
                $result = array_values(
                    array_uintersect(
                        $result,
                        $currentFiltered,
                        fn($a, $b) => array_search($a->id ?? -$a->unsavedId, $originalIds) <=> array_search($b->id ?? -$b->unsavedId, $originalIds)
                    )
                );
            }
        }

        return $result;
    }

    /**
     * Compares an integer against a criteria model integer comparison string, or integer.
     * Takes in comparison inputs such as `1`, `'>=23'`, and `'< 4'`.
     *
     * @param int $value
     * @param int|string $comparison
     * @return bool
     */
    private function _compareInt(int $value, int|string $comparison)
    {
        if (is_int($comparison)) {
            return $value === $comparison;
        }

        if (!is_string($comparison)) {
            return false;
        }

        $matches = [];
        preg_match('/([><]=?)\\s*([0-9]+)/', $comparison, $matches);

        if (count($matches) !== 3) {
            return false;
        }

        $comparator = $matches[1];
        $comparison = (int)$matches[2];

        return match ($comparator) {
            '>' => $value > $comparison,
            '<' => $value < $comparison,
            '>=' => $value >= $comparison,
            '<=' => $value <= $comparison,
            default => false
        };
    }

    /**
     * Returns a block given an ID, or an actual block.
     * Saves having to check if some value is an integer or a block instance.
     *
     * @param Block|int|null $block
     * @return Block|null
     */
    private function _getBlock(Block|int|null $block): ?Block
    {
        if (is_int($block)) {
            foreach ($this->_allElements as $element) {
                if ($element->id == $block) {
                    $block = $element;
                    break;
                }
            }
        }

        if ($block instanceof Block) {
            return $block;
        }

        return null;
    }

    /**
     * Finds the position of a block inside a list of blocks.
     * It checks using the block's ID, so the passed block doesn't have to be strictly the same instance it matches to.
     * If no match is found, `-1` is returned.
     *
     * @param Block[] $elements
     * @param Block $block
     * @return int
     */
    private function _indexOfBlock(array $elements, Block $block): int
    {
        foreach ($elements as $i => $element) {
            if ($element->id === $block->id) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * Returns the previous siblings of a given block.
     *
     * @param Block[] $elements The blocks being filtered.
     * @param Block $block The block having its previous siblings found.
     * @param int|null $index Optionally provide the block index to start checking from.
     * @return Block[] The previous siblings.
     */
    private function _getPrevSiblings(array $elements, Block $block, ?int $index = null): array
    {
        if ($index === null) {
            $index = $this->_indexOfBlock($elements, $block);
        }

        if ($index < 0) {
            return [];
        }

        $prevSiblings = [];

        for ($i = $index - 1; $i >= 0; $i--) {
            $element = $elements[$i];

            if ($element->level < $block->level) {
                break;
            }

            if ($element->level == $block->level) {
                array_unshift($prevSiblings, $element);
            }
        }

        return $prevSiblings;
    }

    /**
     * Returns the next siblings of a given block.
     *
     * @param Block[] $elements The blocks being filtered.
     * @param Block $block The block having its next siblings found.
     * @param int|null $index Optionally provide the block index to start checking from.
     * @return Block[] The next siblings.
     */
    private function _getNextSiblings(array $elements, Block $block, ?int $index = null): array
    {
        if ($index === null) {
            $index = $this->_indexOfBlock($elements, $block);
        }

        if ($index < 0) {
            return [];
        }

        $nextSiblings = [];

        $elementsCount = count($elements);
        for ($i = $index + 1; $i < $elementsCount; $i++) {
            $element = $elements[$i];

            if ($element->level < $block->level) {
                break;
            }

            if ($element->level == $block->level) {
                $nextSiblings[] = $element;
            }
        }

        return $nextSiblings;
    }

    // Live Preview methods
    // These methods must be prefixed with three underscores. They will automatically be detected and used when filtering.

    /**
     * @param Block[] $elements
     * @param int[]|int|null $value
     * @return Block[]
     */
    private function ___typeId(array $elements, array|int|null $value): array
    {
        if (!$value) {
            return $elements;
        }

        if (is_array($value)) {
            $newElements = array_filter($elements, fn($element) => in_array($element->typeId, $value));
        } else {
            $newElements = array_filter($elements, fn($element) => $element->typeId == $value);
        }

        return array_values($newElements);
    }

    /**
     * @param Block[] $elements
     * @param int|null $value
     * @return Block[]
     */
    private function ___ancestorDist($elements, ?int $value): array
    {
        if (!$value || !$this->ancestorOf) {
            return $elements;
        }

        $ancestors = array_filter($elements, fn($element) => $element->level >= $this->ancestorOf->level - $value);

        return array_values($ancestors);
    }

    /**
     * @param Block[] $elements
     * @param Block|null $value
     * @return Block[]
     */
    private function ___ancestorOf(array $elements, ?Block $value): array
    {
        if (!$value) {
            return $elements;
        }

        $ancestors = [];
        $found = false;
        $level = $value->level - 1;

        foreach (array_reverse($elements) as $element) {
            if ($level < 1) {
                break;
            } else {
                if ($element === $value) {
                    $found = true;
                } elseif ($found && $element->level == $level) {
                    $ancestors[] = $element;
                    $level--;
                }
            }
        }

        return array_reverse($ancestors);
    }

    /**
     * @param Block[] $elements
     * @param int|null $value
     * @return Block[]
     */
    private function ___descendantDist(array $elements, ?int $value): array
    {
        if (!$value || !$this->descendantOf) {
            return $elements;
        }

        $descendants = array_filter($elements, fn($element) => $element->level <= $this->descendantOf->level + $value);

        return array_values($descendants);
    }

    /**
     * @param Block[] $elements
     * @param Block|null $value
     * @return Block[]
     */
    private function ___descendantOf(array $elements, ?Block $value): array
    {
        if (!$value) {
            return $elements;
        }

        $descendants = [];
        $found = false;

        foreach ($elements as $element) {
            if ($element === $value) {
                $found = true;
            } elseif ($found) {
                if (
                    $value->rgt && $element->rgt && $element->rgt < $value->rgt ||
                    (!$value->rgt || !$element->rgt) && $element->level > $value->level
                ) {
                    $descendants[] = $element;
                } else {
                    break;
                }
            }
        }

        return $descendants;
    }

    /**
     * @param Block[] $elements
     * @param int|int[]|null $value
     * @return Block[]
     */
    private function ___id(array $elements, int|array|null $value): array
    {
        if (!$value) {
            return $elements;
        }

        if (!is_array($value)) {
            return $this->___id($elements, [$value]);
        }

        $ids = [];

        foreach ($value as $id) {
            $ids[$id] = true;
        }

        $newElements = array_filter($elements, fn($element) => $element->id !== null && isset($ids[$element->id]));

        return array_values($newElements);
    }

    /**
     * @param Block[] $elements
     * @param bool $value
     * @return Block[]
     */
    private function ___inReverse(array $elements, bool $value = true): array
    {
        if (!$value) {
            return $elements;
        }

        return array_reverse($elements);
    }

    /**
     * @param Block[] $elements
     * @param int|string|null $value
     * @return Block[]
     */
    private function ___level(array $elements, int|string|null $value): array
    {
        if (!$value) {
            return $elements;
        }

        $newElements = array_filter($elements, fn($block) => $this->_compareInt($block->level, $value));

        return array_values($newElements);
    }

    /**
     * @param Block[] $elements
     * @param int|null $value
     * @return Block[]
     */
    private function ___limit(array $elements, ?int $value): array
    {
        if (!$value) {
            return $elements;
        }

        return array_slice($elements, 0, $value);
    }

    /**
     * @param Block[] $elements
     * @param Block|int|null $value
     * @return Block[]
     */
    private function ___nextSiblingOf(array $elements, Block|int|null $value): array
    {
        $value = $this->_getBlock($value);

        if (!$value) {
            return $elements;
        }

        $nextSiblings = $this->_getNextSiblings($elements, $value);

        if (empty($nextSiblings)) {
            return [];
        }

        return [$nextSiblings[0]];
    }

    /**
     * @param Block[] $elements
     * @param int|null $value
     * @return Block[]
     */
    private function ___offset(array $elements, ?int $value): array
    {
        if (!$value) {
            return $elements;
        }

        return array_slice($elements, $value);
    }

    /**
     * @param Block[] $elements
     * @param Block|int|null $value
     * @return Block[]
     */
    private function ___positionedAfter(array $elements, Block|int|null $value): array
    {
        $value = $this->_getBlock($value);

        if (!$value) {
            return $elements;
        }

        $index = $this->_indexOfBlock($elements, $value);

        return array_slice($elements, $index + 1);
    }

    /**
     * @param Block[] $elements
     * @param Block|int|null $value
     * @return Block[]
     */
    private function ___positionedBefore(array $elements, Block|int|null $value): array
    {
        $value = $this->_getBlock($value);

        if (!$value) {
            return $elements;
        }

        $newElements = [];
        $ancestors = $this->___ancestorOf($elements, $value);
        $ancestors = array_merge([$value], $ancestors);

        foreach (array_reverse($ancestors) as $ancestor) {
            $ancestorPrevSiblings = $this->_getPrevSiblings($elements, $ancestor);
            $newElements = array_merge($newElements, $ancestorPrevSiblings);
        }

        return $newElements;
    }

    /**
     * @param Block[] $elements
     * @param Block|int|null $value
     * @return Block[]
     */
    private function ___prevSiblingOf(array $elements, Block|int|null $value)
    {
        $value = $this->_getBlock($value);

        if (!$value) {
            return $elements;
        }

        $prevSiblings = $this->_getPrevSiblings($elements, $value);

        if (empty($prevSiblings)) {
            return [];
        }

        return [end($prevSiblings)];
    }

    /**
     * @param Block[] $elements
     * @param Block|int|null $value
     * @return Block[]
     */
    private function ___siblingOf(array $elements, Block|int|null $value): array
    {
        $value = $this->_getBlock($value);

        if (!$value) {
            return $elements;
        }

        $mid = $this->_indexOfBlock($elements, $value);
        $prevSiblings = $this->_getPrevSiblings($elements, $value, $mid);
        $nextSiblings = $this->_getNextSiblings($elements, $value, $mid);

        return array_merge($prevSiblings, $nextSiblings);
    }

    /**
     * @param Block[] $elements
     * @param string|string[] $value
     * @return Block[]
     */
    private function ___status(array $elements, string|array|null $value): array
    {
        if (!$value) {
            return $elements;
        }

        $newElements = array_filter(
            $elements,
            fn($element) => is_array($value) ? in_array($element->status, $value) : $element->status == $value
        );

        return array_values($newElements);
    }
}
