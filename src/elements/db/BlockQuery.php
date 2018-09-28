<?php
namespace benf\neo\elements\db;

use yii\base\Exception;
use yii\db\Connection;

use Craft;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\models\Site;
use craft\helpers\Db;

use benf\neo\Plugin as Neo;
use benf\neo\elements\Block;
use benf\neo\models\BlockType;

class BlockQuery extends ElementQuery
{
	public $fieldId;
	public $ownerId;
	public $ownerSiteId;
	public $typeId;

	private $_allElements;
	private $_currentFilters = [];

	public function __set($name, $value)
	{
		$deprecatorService = Craft::$app->getDeprecator();

		switch ($name)
		{
			case 'ownerSite':
			{
				$this->ownerSite($value);
			}
			break;
			case 'type':
			{
				$this->type($value);
			}
			break;
			case 'ownerLocale':
			{
				$deprecatorService->log('BlockQuery::ownerLocale()', "The “ownerLocale” Neo block query param has been deprecated. Use “ownerSite” or “ownerSiteId” instead.");
				$this->ownerSite($value);
			}
			break;
			default:
			{
				parent::__set($name, $value);
			}
		}
	}

	public function init()
	{
		$this->withStructure = true;

		parent::init();
	}

	public function fieldId($value)
	{
		$this->fieldId = $value;

		return $this;
	}

	public function ownerId($value)
	{
		$this->ownerId = $value;

		return $this;
	}

	public function ownerSiteId($value)
	{
		$this->ownerSiteId = $value;

		if ($value && strtolower($value) !== ':empty:')
		{
			$this->siteId = (int)$value;
		}

		return $this;
	}

	public function ownerSite($value)
	{
		if ($value instanceof Site)
		{
			$this->ownerSiteId($value->id);
		}
		else
		{
			$site = Craft::$app->getSites()->getSiteByHandle($value);

			if (!$site)
			{
				throw new Exception("Invalid site handle: $value");
			}

			$this->ownerSiteId($site->id);
		}

		return $this;
	}

	public function ownerLocale($value)
	{
		Craft::$app->getDeprecator()->log('ElementQuery::ownerLocale()', "The “ownerLocale” Neo block query param has been deprecated. Use “site” or “siteId” instead.");
		$this->ownerSite($value);

		return $this;
	}

	public function owner(ElementInterface $owner)
	{
		$this->ownerId = $owner->id;
		$this->siteId = $owner->siteId;

		return $this;
	}

	public function type($value)
	{
		if ($value instanceof BlockType)
		{
			$this->typeId = $value->id;
		}
		else if ($value !== null)
		{
			$this->typeId = (new Query())
				->select(['id'])
				->from(['{{%neoblocktypes}}'])
				->where(Db::parseParam('handle', $value))
				->column();
		}
		else
		{
			$this->typeId = null;
		}

		return $this;
	}

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
		$isLivePreview = Craft::$app->getRequest()->getIsLivePreview();

		if ($isLivePreview && isset($this->_allElements))
		{
			$this->setCachedResult($this->_getFilteredResult());
		}

		return parent::count($q, $db);
	}

	/**
	 * @inheritdoc
	 */
	public function all($db = null)
	{
		$isLivePreview = Craft::$app->getRequest()->getIsLivePreview();

		if ($isLivePreview && isset($this->_allElements))
		{
			$this->setCachedResult($this->_getFilteredResult());
		}

		return parent::all($db);
	}

	/**
	 * @inheritdoc
	 */
	public function one($db = null)
	{
		$isLivePreview = Craft::$app->getRequest()->getIsLivePreview();

		if ($isLivePreview && isset($this->_allElements))
		{
			$this->setCachedResult($this->_getFilteredResult());
		}

		return parent::one($db);
	}

	/**
	 * @inheritdoc
	 */
	public function nth(int $n, Connection $db = null)
	{
		$isLivePreview = Craft::$app->getRequest()->getIsLivePreview();

		if ($isLivePreview && isset($this->_allElements))
		{
			$this->setCachedResult($this->_getFilteredResult());
		}

		return parent::nth($n, $db);
	}

	public function setCachedResult(array $elements)
	{
		$isLivePreview = Craft::$app->getRequest()->getIsLivePreview();

		if ($isLivePreview && !isset($this->_allElements))
		{
			$this->_allElements = $elements;
		}

		parent::setCachedResult($elements);
	}

	protected function beforePrepare(): bool
	{
		$this->joinElementTable('neoblocks');

		$isSaved = $this->id && is_numeric($this->id);

		if ($isSaved)
		{
			foreach (['fieldId', 'ownerId', 'ownerSiteId'] as $idProperty)
			{
				if (!$this->$idProperty)
				{
					$this->$idProperty = (new Query())
						->select([$idProperty])
						->from(['{{%neoblocks}}'])
						->where(['id' => $this->id])
						->scalar();
				}
			}

			if (!$this->structureId && $this->fieldId && $this->ownerId && $this->ownerSiteId)
			{
				$blockStructure = Neo::$plugin->blocks->getStructure($this->fieldId, $this->ownerId, $this->ownerSiteId);

				if ($blockStructure)
				{
					$this->structureId = $blockStructure->structureId;
				}
			}
		}

		$this->query->select([
			'neoblocks.fieldId',
			'neoblocks.ownerId',
			'neoblocks.ownerSiteId',
			'neoblocks.typeId',
		]);

		if ($this->fieldId)
		{
			$this->subQuery->andWhere(Db::parseParam('neoblocks.fieldId', $this->fieldId));
		}

		if ($this->ownerId)
		{
			$this->subQuery->andWhere(Db::parseParam('neoblocks.ownerId', $this->ownerId));
		}

		if ($this->ownerSiteId)
		{
			$this->subQuery->andWhere(Db::parseParam('neoblocks.ownerSiteId', $this->ownerSiteId));
		}

		if ($this->typeId !== null)
		{
			// If typeId is an empty array, it's because type() was called but no valid type handles were passed in
			if (is_array($this->typeId) && empty($this->typeId))
			{
				return false;
			}

			$this->subQuery->andWhere(Db::parseParam('neoblocks.typeId', $this->typeId));
		}

		return parent::beforePrepare();
	}


	// Private methods

	private function _getFilteredResult()
	{
		$result = $this->_allElements ?? [];
		$criteria = $this->getCriteria();

		foreach (['limit', 'offset'] as $limitParam)
		{
			if ($this->$limitParam)
			{
				$criteria[$limitParam] = $this->$limitParam;
			}
		}

		foreach ($criteria as $param => $value)
		{
			$method = '__' . $param;

			if (method_exists($this, $method))
			{
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
	 * @param int,string $comparison
	 * @return bool
	 */
	private function _compareInt($value, $comparison)
	{
		if (is_int($comparison))
		{
			return $value === $comparison;
		}

		if (is_string($comparison))
		{
			$matches = [];
			preg_match('/([><]=?)\\s*([0-9]+)/', $comparison, $matches);

			if (count($matches) === 3)
			{
				$comparator = $matches[1];
				$comparison = (int)$matches[2];

				switch ($comparator)
				{
					case '>':
					{
						return $value > $comparison;
					}
					case '<':
					{
						return $value < $comparison;
					}
					case '>=':
					{
						return $value >= $comparison;
					}
					case '<=':
					{
						return $value <= $comparison;
					}
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
		if (is_int($block))
		{
			$block = Neo::$plugin->blocks->getBlockById($block);
		}

		if ($block instanceof Block)
		{
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
		foreach ($elements as $i => $element)
		{
			if ($element->id === $block->id)
			{
				return $i;
			}
		}

		return -1;
	}

	private function _getPrevSiblings(array $elements, Block $block, int $index = null): array
	{
		if ($index === null)
		{
			$index = $this->_indexOfBlock($elements, $block);
		}

		if ($index < 0)
		{
			return [];
		}

		$prevSiblings = [];

		for ($i = $index - 1; $i >= 0; $i--)
		{
			$element = $elements[$i];

			if ($element->level < $block->level)
			{
				break;
			}

			if ($element->level == $block->level)
			{
				array_unshift($prevSiblings, $element);
			}
		}

		return $prevSiblings;
	}

	private function _getNextSiblings(array $elements, Block $block, int $index = null): array
	{
		if ($index === null)
		{
			$index = $this->_indexOfBlock($elements, $block);
		}

		if ($index < 0)
		{
			return [];
		}

		$nextSiblings = [];

		for ($i = $index + 1; $i < count($elements); $i++)
		{
			$element = $elements[$i];

			if ($element->level < $block->level)
			{
				break;
			}

			if ($element->level == $block->level)
			{
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
	private function __ancestorDist($elements, $value): array
	{
		if (!$value || !$this->ancestorOf)
		{
			return $elements;
		}

		$ancestors = array_filter($elements, function($element) use($value)
		{
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
		if (!$value)
		{
			return $elements;
		}

		$ancestors = [];
		$found = false;
		$level = $value->level - 1;

		foreach (array_reverse($elements) as $element)
		{
			if ($level < 1)
			{
				break;
			}
			else if ($element === $value)
			{
				$found = true;
			}
			else if ($found)
			{
				if ($element->level == $level)
				{
					$ancestors[] = $element;
					$level--;
				}
			}
		}

		return $ancestors;
	}

	/**
	 * @param array $elements
	 * @param int $value
	 * @return array
	 */
	private function __descendantDist($elements, $value)
	{
		if (!$value || !$this->descendantOf)
		{
			return $elements;
		}

		$descendants = array_filter($elements, function($element) use($value)
		{
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
		if (!$value)
		{
			return $elements;
		}

		$descendants = [];
		$found = false;

		foreach ($elements as $element)
		{
			if ($element === $value)
			{
				$found = true;
			}
			else if ($found)
			{
				if ($element->level > $value->level)
				{
					$descendants[] = $element;
				}
				else
				{
					break;
				}
			}
		}

		return $descendants;
	}

	/**
	 * @param array $elements
	 * @param int $value
	 * @return array
	 */
	private function __id(array $elements, $value): array
	{
		if (!$value)
		{
			return $elements;
		}

		$newElements = array_filter($elements, function($element) use($value)
		{
			return $element->id == $value;
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
		if (!$value)
		{
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
		if (!$value)
		{
			return $elements;
		}

		$newElements = array_filter($elements, function($block) use($value)
		{
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
		if (!$value)
		{
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

		if (!$value)
		{
			return $elements;
		}

		$nextSiblings = $this->_getNextSiblings($elements, $value);

		if (empty($nextSiblings))
		{
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
		if (!$value)
		{
			return $elements;
		}

		return array_slice($elements, $value);
	}

	/**
	 * @param array $elements
	 * @param Block|int $value
	 * @return array
	 */
	private function __prevSiblingOf(array $elements, $value)
	{
		$value = $this->_getBlock($value);

		if (!$value)
		{
			return $elements;
		}

		$prevSiblings = $this->_getPrevSiblings($elements, $value);

		if (empty($prevSiblings))
		{
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

		if (!$value)
		{
			return $elements;
		}

		$mid = $this->_indexOfBlock($elements, $value);
		$prevSiblings = $this->_getPrevSiblings($elements, $value, $mid);
		$nextSiblings = $this->_getNextSiblings($elements, $value, $mid);

		return array_merge($prevSiblings, $nextSiblings);
	}
}
