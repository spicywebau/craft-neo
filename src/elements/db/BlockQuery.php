<?php

namespace benf\neo\elements\db;

use benf\neo\Plugin as Neo;
use benf\neo\elements\Block;
use benf\neo\models\BlockType;
use Craft;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\db\Table;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use craft\models\Site;
use yii\base\Exception;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\db\Connection;

/**
 * Class BlockQuery
 *
 * @package benf\neo\elements\db
 * @author Spicy Web <craft@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.0.0
 */
class BlockQuery extends ElementQuery
{
    // Public properties
    /**
     * @var int|array|null The field ID(s) to query for.
     */
    public $fieldId;

    /**
     * @var int|array|null The owner ID(s) to query for.
     */
    public $ownerId;

    /**
     * @var int|array|null The owner site ID to query for.
     * @deprecated in 2.4.0. Use [[$siteId]] instead.
     */
    public $ownerSiteId;

    /**
     * @var int|array|null The block type ID(s) to query for.
     */
    public $typeId;

    /**
     * @var bool|null Whether the owner elements can be drafts.
     * @since 2.9.7
     */
    public $allowOwnerDrafts;

    /**
     * @var bool|null Whether the owner elements can be revisions.
     * @since 2.9.7
     */
    public $allowOwnerRevisions;

    // Protected properties

    /**
     * @inheritdoc
     */
    protected $defaultOrderBy = ['neoblocks.sortOrder' => SORT_ASC];

    // Private properties

    /**
     * @var array|null The block data to be filtered in live preview mode.
     */
    private $_allElements;

    /**
     * @var bool Whether to operate on a memoized data set.
     */
    private $_useMemoized = false;

    private static $ownersById = [];

    // Public methods

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        $deprecatorService = Craft::$app->getDeprecator();

        switch ($name) {
            case 'ownerSite':
                {
                    Craft::$app->getDeprecator()->log('BlockQuery::ownerSite()',
                        'The “ownerSite” Neo block query param has been deprecated. Use “site” or “siteId” instead.');
                }
                break;
            case 'type':
                {
                    $this->type($value);
                }
                break;
            case 'ownerLocale':
                {
                    $deprecatorService->log('BlockQuery::ownerLocale()',
                        "The “ownerLocale” Neo block query param has been deprecated. Use “site” or “siteId” instead.");
                }
                break;
            default:
            {
                parent::__set($name, $value);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->withStructure = true;
        parent::init();
    }

    /**
     * Filters the query results based on the field ID.
     *
     * @param int|array|null $value The field ID(s).
     * @return $this
     */
    public function fieldId($value)
    {
        $this->fieldId = $value;

        return $this;
    }

    /**
     * Filters the query results based on the owner ID.
     *
     * @param int|array|null $value The owner ID(s).
     * @return $this
     */
    public function ownerId($value)
    {
        $this->ownerId = $value;

        return $this;
    }

    /**
     * Filters the query results based on the owner's site ID.
     *
     * @param int|string|null $value The site ID.
     * @return $this
     */
    public function ownerSiteId()
    {
        Craft::$app->getDeprecator()->log('BlockQuery::ownerSiteId()',
            'The “ownerSiteId” Neo block query param has been deprecated. Use “site” or “siteId” instead.');

        return $this;
    }

    /**
     * Filters the query results based on the owner's site.
     *
     * @param string|\craft\models\Site $value The site, specified either by a handle or a site model.
     * @return $this
     * @throws Exception if the site handle is invalid.
     */
    public function ownerSite()
    {
        Craft::$app->getDeprecator()->log('BlockQuery::ownerSiteId()',
            'The “ownerSite” Neo block query param has been deprecated. Use “site” or “siteId” instead.');

        return $this;
    }

    /**
     * Filters the query results based on the owner's site.
     *
     * @param string $value The site handle.
     * @return $this
     * @deprecated in 2.0.0.  Use `ownerSite()` or `ownerSiteId()` instead.
     */
    public function ownerLocale()
    {
        Craft::$app->getDeprecator()->log('ElementQuery::ownerLocale()',
            "The “ownerLocale” Neo block query param has been deprecated. Use “site” or “siteId” instead.");

        return $this;
    }

    /**
     * Filters the query results based on the owner.
     *
     * @param ElementInterface $value The owner.
     * @return $this
     */
    public function owner(ElementInterface $owner)
    {
        $this->ownerId = $owner->id;
        $this->siteId = $owner->siteId;

        return $this;
    }

    /**
     * Filters the query results based on the block type.
     *
     * @param BlockType|string|null The block type, specified either by a handle or a block type model.
     * @return $this
     */
    public function type($value)
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
     * @param int|array|null $value The block type ID(s).
     * @return $this
     */
    public function typeId($value)
    {
        $this->typeId = $value;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function count($q = '*', $db = null)
    {
        $isUsingMemoized = $this->isUsingMemoized();

        if ($isUsingMemoized && isset($this->_allElements)) {
            $this->setCachedResult($this->_getFilteredResult());
        }

        return parent::count($q, $db);
    }

    /**
     * @inheritdoc
     * @return Block[]|array
     */
    public function all($db = null)
    {
        $isUsingMemoized = $this->isUsingMemoized();

        if ($isUsingMemoized && isset($this->_allElements)) {
            $this->setCachedResult($this->_getFilteredResult());
        }

        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return Block|array|null
     */
    public function one($db = null)
    {
        $isUsingMemoized = $this->isUsingMemoized();

        if ($isUsingMemoized && isset($this->_allElements)) {
            $this->setCachedResult($this->_getFilteredResult());
        }

        return parent::one($db);
    }

    /**
     * @inheritdoc
     * @return Block|array|null
     */
    public function nth(int $n, Connection $db = null)
    {
        $isUsingMemoized = $this->isUsingMemoized();

        if ($isUsingMemoized && isset($this->_allElements)) {
            $this->setCachedResult($this->_getFilteredResult());
        }

        return parent::nth($n, $db);
    }

    /**
     * Sets all the elements (blocks) to be filtered against in Live Preview mode.
     * This becomes the main data source for Live Preview, instead of the database.
     *
     * @param array $elements
     */
    public function setAllElements(array $elements)
    {
        $this->_allElements = $elements;
    }

    /**
     * Whether the block query is operating on a memoized data set.
     *
     * @return bool
     */
    public function isUsingMemoized()
    {
        return $this->_useMemoized;
    }

    /**
     * Sets whether the block query operates on a memoized data set.
     *
     * @param bool|array $use - Either a boolean to enable/disable, or a dataset to use (which results in enabling)
     */
    public function useMemoized($use = true)
    {
        if (is_array($use)) {
            $this->setAllElements($use);
            $use = true;
        }

        $this->_useMemoized = $use;
    }


    /**
     * Narrows the query results based on whether the Neo blocks owners are drafts.
     *
     * @param bool|null $value The property value
     * @return static self reference
     * @since 2.9.7
     */
    public function allowOwnerDrafts($value = true)
    {
        $this->allowOwnerDrafts = $value;
        return $this;
    }

    /**
     * Narrows the query results based on whether the Neo blocks owners are revisions.
     *
     * @param bool|null $value The property value
     * @return static self reference
     * @since 2.9.7
     */
    public function allowOwnerRevisions($value = true)
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
        $this->joinElementTable('neoblocks');
        $isSaved = $this->id && is_numeric($this->id);

        if ($isSaved) {
            foreach (['fieldId', 'ownerId'] as $idProperty) {
                if (!$this->$idProperty) {
                    $this->$idProperty = (new Query())
                        ->select([$idProperty])
                        ->from(['{{%neoblocks}}'])
                        ->where(['id' => $this->id])
                        ->scalar();
                }
            }
        }

        $this->_normalizeProp('fieldId');
        $this->_normalizeProp('ownerId');

        $this->query->select([
            'neoblocks.fieldId',
            'neoblocks.ownerId',
            'neoblocks.typeId'
        ]);

        if (Neo::$plugin->blockHasSortOrder) {
            $this->query->addSelect(['neoblocks.sortOrder']);
        }

        if ($this->fieldId) {
            $this->subQuery->andWhere(Db::parseParam('neoblocks.fieldId', $this->fieldId));
        }

        if ($this->ownerId) {
            $this->subQuery->andWhere(Db::parseParam('neoblocks.ownerId', $this->ownerId));
        }

        if ($this->typeId !== null) {
            // If typeId is an empty array, it's because type() was called but no valid type handles were passed in
            if (is_array($this->typeId) && empty($this->typeId)) {
                return false;
            }

            $this->subQuery->andWhere(Db::parseParam('neoblocks.typeId', $this->typeId));
        }

        // Ignore revision/draft blocks by default
        $allowOwnerDrafts = $this->allowOwnerDrafts ?? ($this->id || $this->ownerId || $this->_isDraftRequest());
        $allowOwnerRevisions = $this->allowOwnerRevisions ?? ($this->id || $this->ownerId || $this->_isRevisionRequest());

        if (!$allowOwnerDrafts || !$allowOwnerRevisions) {
            $this->subQuery->innerJoin(['owners' => Table::ELEMENTS], '[[owners.id]] = [[neoblocks.ownerId]]');

            if (!$allowOwnerDrafts) {
                $this->subQuery->andWhere(['owners.draftId' => null]);
            }

            if (!$allowOwnerRevisions) {
                $this->subQuery->andWhere(['owners.revisionId' => null]);
            }
        }

        return parent::beforePrepare();
    }

    /**
     * @inheritdoc
     * @since 2.9.0
     */
    protected function cacheTags(): array
    {
        $tags = [];

        if ($this->fieldId && $this->ownerId) {
            foreach ($this->fieldId as $fieldId) {
                foreach ($this->ownerId as $ownerId) {
                    $tags[] = "field-owner:$fieldId-$ownerId";
                }
            }
        } else {
            if ($this->fieldId) {
                foreach ($this->fieldId as $fieldId) {
                    $tags[] = "field:$fieldId";
                }
            }
            if ($this->ownerId) {
                foreach ($this->ownerId as $ownerId) {
                    $tags[] = "owner:$ownerId";
                }
            }
        }

        return $tags;
    }

    // Private methods

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
        } else if ($this->descendantOf instanceof Block) {
            $ownerId = $this->descendantOf->ownerId;
        } else if ($this->ancestorOf instanceof Block) {
            $ownerId = $this->ancestorOf->ownerId;
        } else {
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
    private function _normalizeProp(string $prop)
    {
        if (!property_exists($this, $prop)) {
            throw new InvalidArgumentException('Tried to access invalid Neo block query property ' . $prop);
        }

        if (is_numeric($this->$prop)) {
            $this->$prop = [$this->$prop];
        } else if (empty($this->$prop)) {
            $this->$prop = null;
        }
    }

    /**
     * Returns the filtered blocks in live preview mode.
     *
     * @return array
     */
    private function _getFilteredResult()
    {
        $result = $this->_allElements ?? [];
        $criteria = $this->getCriteria();

        foreach (['limit', 'offset'] as $limitParam) {
            if ($this->$limitParam) {
                $criteria[$limitParam] = $this->$limitParam;
            }
        }

        foreach ($criteria as $param => $value) {
            $method = '__' . $param;

            if (method_exists($this, $method)) {
                $result = $this->$method($result, $value);
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
    private function _compareInt($value, $comparison)
    {
        if (is_int($comparison)) {
            return $value === $comparison;
        }

        if (is_string($comparison)) {
            $matches = [];
            preg_match('/([><]=?)\\s*([0-9]+)/', $comparison, $matches);

            if (count($matches) === 3) {
                $comparator = $matches[1];
                $comparison = (int)$matches[2];

                switch ($comparator) {
                    case '>': return $value > $comparison;
                    case '<': return $value < $comparison;
                    case '>=': return $value >= $comparison;
                    case '<=': return $value <= $comparison;
                }
            }
        }

        return false;
    }

    /**
     * Returns a block given an ID, or an actual block.
     * Saves having to check if some value is an integer or a block instance.
     *
     * @param Block|int $block
     * @return Block|null
     */
    private function _getBlock($block)
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
     * @param array $elements
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
     * @param array $elements The blocks being filtered.
     * @param Block $block The block having its previous siblings found.
     * @param int|null $index Optionally provide the block index to start checking from.
     * @return array The previous siblings.
     */
    private function _getPrevSiblings(array $elements, Block $block, int $index = null): array
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
     * @param array $elements The blocks being filtered.
     * @param Block $block The block having its next siblings found.
     * @param int|null $index Optionally provide the block index to start checking from.
     * @return array The next siblings.
     */
    private function _getNextSiblings(array $elements, Block $block, int $index = null): array
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
    // These methods must be prefixed with two underscores. They will automatically be detected and used when filtering.

    /**
     * @param array $elements
     * @param int $value
     * @return array
     */
    private function __typeId(array $elements, $value): array
    {
        if (!$value) {
            return $elements;
        }

        $newElements = array_filter($elements, function ($element) use ($value) {
            return in_array($element->typeId, $value);
        });

        return array_values($newElements);
    }

    /**
     * @param array $elements
     * @param int $value
     * @return array
     */
    private function __ancestorDist($elements, $value): array
    {
        if (!$value || !$this->ancestorOf) {
            return $elements;
        }

        $ancestors = array_filter($elements, function ($element) use ($value) {
            return $element->level >= $this->ancestorOf->level - $value;
        });

        return array_values($ancestors);
    }

    /**
     * @param array $elements
     * @param Block $value
     * @return array
     */
    private function __ancestorOf(array $elements, $value): array
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
                } else if ($found && $element->level == $level) {
                    $ancestors[] = $element;
                    $level--;
                }
            }
        }

        return array_reverse($ancestors);
    }

    /**
     * @param array $elements
     * @param int $value
     * @return array
     */
    private function __descendantDist($elements, $value)
    {
        if (!$value || !$this->descendantOf) {
            return $elements;
        }

        $descendants = array_filter($elements, function ($element) use ($value) {
            return $element->level <= $this->descendantOf->level + $value;
        });

        return array_values($descendants);
    }

    /**
     * @param array $elements
     * @param Block $value
     * @return array
     */
    private function __descendantOf(array $elements, $value): array
    {
        if (!$value) {
            return $elements;
        }

        $descendants = [];
        $found = false;

        foreach ($elements as $element) {
            if ($element === $value) {
                $found = true;
            } else if ($found) {
                if (($value->rgt && $element->rgt < $value->rgt) || $element->level > $value->level) {
                    $descendants[] = $element;
                } else {
                    break;
                }
            }
        }

        return $descendants;
    }

    /**
     * @param array $elements
     * @param int[]|int|null $value
     * @return array
     */
    private function __id(array $elements, $value): array
    {
        if (!$value) {
            return $elements;
        }

        if (!is_array($value)) {
            return $this->__id($elements, [$value]);
        }

        $ids = [];

        foreach ($value as $id) {
            $ids[$id] = true;
        }

        $newElements = array_filter($elements, function ($element) use ($ids) {
            return $element->id !== null && isset($ids[$element->id]);
        });

        return array_values($newElements);
    }

    /**
     * @param array $elements
     * @param bool $value
     * @return array
     */
    private function __inReverse(array $elements, bool $value = true): array
    {
        if (!$value) {
            return $elements;
        }

        return array_reverse($elements);
    }

    /**
     * @param array $elements
     * @param int $value
     * @return array
     */
    private function __level(array $elements, $value): array
    {
        if (!$value) {
            return $elements;
        }

        $newElements = array_filter($elements, function ($block) use ($value) {
            return $this->_compareInt($block->level, $value);
        });

        return array_values($newElements);
    }

    /**
     * @param array $elements
     * @param int $value
     * @return array
     */
    private function __limit(array $elements, $value): array
    {
        if (!$value) {
            return $elements;
        }

        return array_slice($elements, 0, $value);
    }

    /**
     * @param array $elements
     * @param Block|int $value
     * @return array
     */
    private function __nextSiblingOf(array $elements, $value): array
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
     * @param array $elements
     * @param int $value
     * @return array
     */
    private function __offset(array $elements, $value): array
    {
        if (!$value) {
            return $elements;
        }

        return array_slice($elements, $value);
    }

    /**
     * @param array $elements
     * @param Block|int $value
     * @return array
     */
    private function __positionedAfter(array $elements, $value): array
    {
        $value = $this->_getBlock($value);

        if (!$value) {
            return $elements;
        }

        $index = $this->_indexOfBlock($elements, $value);

        return array_slice($elements, $index + 1);
    }

    /**
     * @param array $elements
     * @param Block|int $value
     * @return array
     */
    private function __positionedBefore(array $elements, $value): array
    {
        $value = $this->_getBlock($value);

        if (!$value) {
            return $elements;
        }

        $newElements = [];
        $ancestors = $this->__ancestorOf($elements, $value);
        $ancestors = array_merge([$value], $ancestors);

        foreach (array_reverse($ancestors) as $ancestor) {
            $ancestorPrevSiblings = $this->_getPrevSiblings($elements, $ancestor);
            $newElements = array_merge($newElements, $ancestorPrevSiblings);
        }

        return $newElements;
    }

    /**
     * @param array $elements
     * @param Block|int $value
     * @return array
     */
    private function __prevSiblingOf(array $elements, $value)
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
     * @param array $elements
     * @param Block|int $value
     * @return array
     */
    private function __siblingOf(array $elements, $value): array
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
     * @param array $elements
     * @param string $value
     * @return array
     */
    private function __status(array $elements, $value): array
    {
        if (!$value) {
            return $elements;
        }

        $newElements = array_filter($elements, function ($element) use ($value) {
            return $element->status == $value;
        });

        return array_values($newElements);
    }
}
