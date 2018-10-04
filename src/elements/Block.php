<?php
namespace benf\neo\elements;

use yii\base\Exception;
use yii\base\InvalidConfigException;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ElementHelper;
use craft\validators\SiteIdValidator;

use benf\neo\Plugin as Neo;
use benf\neo\elements\db\BlockQuery;
use benf\neo\records\Block as BlockRecord;

class Block extends Element
{
	private $_allElements;
	private $_liveQueries = [];

	public static function displayName(): string
	{
		return Craft::t('neo', "Neo Block");
	}

	public static function refHandle(): string
	{
		return 'neoblock';
	}

	public static function hasContent(): bool
	{
		return true;
	}

	public static function isLocalized(): bool
	{
		return true;
	}

	public static function hasStatuses(): bool
	{
		return true;
	}

	public static function find(): ElementQueryInterface
	{
		return new BlockQuery(static::class);
	}

	public $fieldId;
	public $ownerId;
	public $ownerSiteId;
	public $typeId;

	private $_owner;
	public $_collapsed;

	public function extraFields(): array
	{
		$names = parent::extraFields();
		$names[] = 'owner';
		$names[] = 'type';

		return $names;
	}

	public function rules(): array
	{
		$rules = parent::rules();
		$rules[] = [['fieldId', 'ownerId', 'typeId'], 'number', 'integerOnly' => true];
		$rules[] = [['ownerSiteId'], SiteIdValidator::class];

		return $rules;
	}

	public function getSupportedSites(): array
	{
		$siteIds = [];

		if ($this->ownerSiteId !== null)
		{
			$siteIds[] = $this->ownerSiteId;
		}
		else
		{
			$owner = $this->getOwner();

			if ($owner)
			{
				foreach (ElementHelper::supportedSitesForElement($owner) as $siteInfo)
				{
					$siteIds[] = $siteInfo['siteId'];
				}
			}
			else
			{
				$siteIds[] = Craft::$app->getSites()->getPrimarySite()->id;
			}
		}

		return $siteIds;
	}

	public function getFieldLayout()
	{
		return parent::getFieldLayout() ?? $this->getType()->getFieldLayout();
	}

	public function getType()
	{
		if ($this->typeId === null)
		{
			throw new InvalidConfigException("Neo block is missing its type ID");
		}

		$blockType = Neo::$plugin->blockTypes->getById($this->typeId);

		if (!$blockType)
		{
			throw new InvalidConfigException("Invalid Neo block ID: $this->typeId");
		}

		return $blockType;
	}

	public function getOwner()
	{
		$owner = $this->_owner;

		if ($owner !== null)
		{
			if ($owner === false)
			{
				$owner = null;
			}
		}
		elseif ($this->ownerId !== null)
		{
			$owner = Craft::$app->getElements()->getElementById($this->ownerId, null, $this->siteId);

			if ($owner === null)
			{
				$this->_owner = false;
			}
		}

		return $owner;
	}

	public function setOwner(ElementInterface $owner = null)
	{
		$this->_owner = $owner;
	}

	public function getCollapsed()
	{
		$cacheService = Craft::$app->getCache();

		$collapsed = $this->_collapsed;

		if (!is_bool($collapsed))
		{
			if ($this->id)
			{
				$cacheKey = "neoblock-$this->id-collapsed";
				$collapsed = $cacheService->exists($cacheKey);
				$this->_collapsed = $collapsed;
			}
			else
			{
				$collapsed = false;
			}
		}

		return $collapsed;
	}

	public function setCollapsed(bool $value)
	{
		$this->_collapsed = $value;
	}

	public function cacheCollapsed()
	{
		$cacheService = Craft::$app->getCache();

		if (is_bool($this->_collapsed) && $this->id)
		{
			$cacheKey = "neoblock-$this->id-collapsed";

			if ($this->_collapsed)
			{
				$cacheService->add($cacheKey, 1);
			}
			else
			{
				$cacheService->delete($cacheKey);
			}
		}
	}

	public function forgetCollapsed()
	{
		$cacheService = Craft::$app->getCache();

		if ($this->id)
		{
			$cacheKey = "neoblock-$this->id-collapsed";
			$cacheService->delete($cacheKey);
		}
	}

	public function getHasFreshContent(): bool
	{
		$owner = $this->getOwner();

		return $owner ? $owner->getHasFreshContent() : false;
	}

	public function afterSave(bool $isNew)
	{
		$record = null;

		if ($isNew)
		{
			$record = new BlockRecord();
			$record->id = $this->id;
		}
		else
		{
			$record = BlockRecord::findOne($this->id);

			if (!$record)
			{
				throw new Exception("Invalid Neo block ID: $this->id");
			}
		}

		$record->fieldId = $this->fieldId;
		$record->ownerId = $this->ownerId;
		$record->ownerSiteId = $this->ownerSiteId;
		$record->typeId = $this->typeId;
		$record->save(false);

		parent::afterSave($isNew);
	}

	/**
	 * Allows memoizing all blocks (including this one) for a particular field.
	 * This is used for Live Preview mode, where certain methods, like `getAncestors`, create block queries which need
	 * a local set of blocks to query against.
	 *
	 * @param array $elements
	 */
	public function setAllElements($elements)
	{
		$this->_allElements = $elements;

		// Update the elements across any memoized block queries
		foreach ($this->_liveQueries as $name => $query)
		{
			$query->setAllElements($this->_allElements);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function getAncestors(int $dist = null)
	{
		// If the request is in Live Preview mode, use the Neo-extended block query, which supports Live Preview mode
		$isLivePreview = Craft::$app->getRequest()->getIsLivePreview();

		if ($isLivePreview)
		{
			if (!isset($this->_liveQueries['ancestors']))
			{
				$query = $this->_getBaseRelativeQuery();
				$query->ancestorOf = $this;
				$query->setAllElements($this->_allElements);

				$this->_liveQueries['ancestors'] = $query;
			}

			if ($dist)
			{
				$query = $this->_liveQueries['ancestors']->ancestorDist($dist);
				$query->setAllElements($this->_allElements);

				return $query;
			}

			return $this->_liveQueries['ancestors'];
		}

		return parent::getAncestors($dist);
	}

	/**
	 * @inheritdoc
	 */
	public function getParent()
	{
		// If the request is in Live Preview mode, use the Neo-extended block query, which supports Live Preview mode
		$isLivePreview = Craft::$app->getRequest()->getIsLivePreview();

		if ($isLivePreview)
		{
			if (!isset($this->_liveQueries['parent']))
			{
				$query = $this->_getBaseRelativeQuery();
				$query->ancestorOf = $this;
				$query->ancestorDist = 1;
				$query->setAllElements($this->_allElements);

				$this->_liveQueries['parent'] = $query;
			}

			return $this->_liveQueries['parent']->one();
		}

		return parent::getParent();
	}

	/**
	 * @inheritdoc
	 */
	public function getDescendants(int $dist = null)
	{
		// If the request is in Live Preview mode, use the Neo-extended block query, which supports Live Preview mode
		$isLivePreview = Craft::$app->getRequest()->getIsLivePreview();

		if ($isLivePreview)
		{
			if (!isset($this->_liveQueries['descendants']))
			{
				$query = $this->_getBaseRelativeQuery();
				$query->descendantOf = $this;
				$query->setAllElements($this->_allElements);

				$this->_liveQueries['descendants'] = $query;
			}

			if ($dist)
			{
				$query = $this->_liveQueries['descendants']->descendantDist($dist);
				$query->setAllElements($this->_allElements);

				return $query;
			}

			return $this->_liveQueries['descendants'];
		}

		return parent::getDescendants($dist);
	}

	/**
	 * @inheritdoc
	 */
	public function getChildren()
	{
		// If the request is in Live Preview mode, use the Neo-extended block query, which supports Live Preview mode
		$isLivePreview = Craft::$app->getRequest()->getIsLivePreview();

		if ($isLivePreview)
		{
			if (!isset($this->_liveQueries['children']))
			{
				$query = $this->_getBaseRelativeQuery();
				$query->descendantOf = $this;
				$query->descendantDist = 1;
				$query->setAllElements($this->_allElements);

				$this->_liveQueries['children'] = $query;
			}

			return $this->_liveQueries['children'];
		}

		return parent::getChildren();
	}

	/**
	 * @inheritdoc
	 */
	public function getSiblings()
	{
		// If the request is in Live Preview mode, use the Neo-extended block query, which supports Live Preview mode
		$isLivePreview = Craft::$app->getRequest()->getIsLivePreview();

		if ($isLivePreview)
		{
			if (!isset($this->_liveQueries['siblings']))
			{
				$query = $this->_getBaseRelativeQuery();
				$query->siblingOf = $this;
				$query->setAllElements($this->_allElements);

				$this->_liveQueries['siblings'] = $query;
			}

			return $this->_liveQueries['siblings'];
		}

		return parent::getSiblings();
	}

	/**
	 * @inheritdoc
	 */
	public function getPrevSibling()
	{
		// If the request is in Live Preview mode, use the Neo-extended block query, which supports Live Preview mode
		$isLivePreview = Craft::$app->getRequest()->getIsLivePreview();

		if ($isLivePreview)
		{
			if (!isset($this->_liveQueries['prevSibling']))
			{
				$query = $this->_getBaseRelativeQuery();
				$query->prevSiblingOf = $this;
				$query->setAllElements($this->_allElements);

				$this->_liveQueries['prevSibling'] = $query;
			}

			return $this->_liveQueries['prevSibling']->one();
		}

		return parent::getPrevSibling();
	}

	/**
	 * @inheritdoc
	 */
	public function getNextSibling()
	{
		// If the request is in Live Preview mode, use the Neo-extended block query, which supports Live Preview mode
		$isLivePreview = Craft::$app->getRequest()->getIsLivePreview();

		if ($isLivePreview)
		{
			if (!isset($this->_liveQueries['nextSibling']))
			{
				$query = $this->_getBaseRelativeQuery();
				$query->nextSiblingOf = $this;
				$query->setAllElements($this->_allElements);

				$this->_liveQueries['nextSibling'] = $query;
			}

			return $this->_liveQueries['nextSibling']->one();
		}

		return parent::getNextSibling();
	}

	private function _getBaseRelativeQuery()
	{
		$query = Block::find();
		$query->fieldId($this->fieldId);
		$query->ownerId($this->ownerId);
		$query->siteId($this->siteId);
		$query->limit(null);
		$query->status(null);
		$query->enabledForSite(false);
		$query->indexBy('id');

		return $query;
	}
}
