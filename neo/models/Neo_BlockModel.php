<?php
namespace Craft;

/**
 * Class Neo_BlockModel
 *
 * @package Craft
 */
class Neo_BlockModel extends BaseElementModel
{
	// Public properties

	/**
	 * Used to indicate whether this block needs to be saved to the database.
	 *
	 * @var bool
	 */
	public $modified = true;


	// Protected properties

	protected $elementType = Neo_ElementType::NeoBlock;


	// Private properties

	private $_owner;
	private $_allElements;
	private $_liveCriteria = [];
	private $_useMemoized = false;
	private $_eagerLoadedBlockTypeElements;


	// Public methods

	/**
	 * Returns the field the block is associated with.
	 *
	 * @return FieldModel|null
	 */
	public function getField()
	{
		return craft()->fields->getFieldById($this->fieldId);
	}

	/**
	 * Returns the field layout the block is associated with.
	 *
	 * @return FieldLayoutModel|null
	 */
	public function getFieldLayout()
	{
		$blockType = $this->getType();

		return $blockType ? $blockType->getFieldLayout() : null;
	}

	/**
	 * Returns the locale ID's the block is available in.
	 *
	 * @return array
	 */
	public function getLocales()
	{
		// If it's owner locale property is set, just return that
		if($this->ownerLocale)
		{
			return [$this->ownerLocale];
		}

		$owner = $this->getOwner();

		// Otherwise, if we at least know the owner element, then search that for locales
		if($owner)
		{
			$localeIds = [];

			foreach($owner->getLocales() as $localeId => $localeInfo)
			{
				if(is_numeric($localeId) && is_string($localeInfo))
				{
					$localeIds[] = $localeInfo;
				}
				else
				{
					$localeIds[] = $localeId;
				}
			}

			return $localeIds;
		}

		// Otherwise it's probably just available in the default site locale
		return [craft()->i18n->getPrimarySiteLocaleId()];
	}

	/**
	 * Returns the block's type.
	 *
	 * @return Neo_BlockTypeModel|null
	 */
	public function getType()
	{
		return $this->typeId ? craft()->neo->getBlockTypeById($this->typeId) : null;
	}

	/**
	 * Returns the element that this block belongs as a field value to.
	 *
	 * @return BaseElementModel|null
	 */
	public function getOwner()
	{
		if(!isset($this->_owner) && $this->ownerId)
		{
			$this->_owner = craft()->elements->getElementById($this->ownerId, null, $this->locale);

			if(!$this->_owner)
			{
				$this->_owner = false;
			}
		}

		return $this->_owner ? $this->_owner : null;
	}

	/**
	 * Allow the owner to be manually memoized, so that `getOwner` doesn't have to perform a DB query to find the owner.
	 * This is useful for Live Preview mode, where blocks might belong to a new owner, which won't have an ID yet.
	 *
	 * @param BaseElementModel $owner
	 */
	public function setOwner(BaseElementModel $owner)
	{
		$this->_owner = $owner;
	}

	/**
	 * Allows memoizing all blocks (including this one) for a particular field.
	 * This is used for Live Preview mode, where certain methods, like `getAncestors`, create element criteria models
	 * which need a local set of blocks to query against.
	 *
	 * @param array $elements
	 */
	public function setAllElements($elements)
	{
		$this->_allElements = $elements;

		// Update the elements across any memoized criteria models
		foreach($this->_liveCriteria as $name => $criteria)
		{
			$criteria->useMemoized($this->isUsingMemoized());
			$criteria->setAllElements($this->_allElements);
		}
	}

	/**
	 * Whether the criteria model is operating on a memoized data set.
	 *
	 * @return bool
	 */
	public function isUsingMemoized()
	{
		return $this->_useMemoized;
	}

	/**
	 * Sets whether the criteria model operates on a memoized data set.
	 *
	 * @param bool|true $use - Either a boolean to enable/disable, or a dataset to use (which results in enabling)
	 */
	public function useMemoized($use = true)
	{
		if(is_array($use))
		{
			$this->setAllElements($use);
			$use = true;
		}

		$this->_useMemoized = $use;
	}

	/**
	 * @inheritDoc BaseElementModel::hasEagerLoadedElements()
	 *
	 * @param string $handle
	 * @return bool
	 */
	public function hasEagerLoadedElements($handle)
	{
		if($this->getType())
		{
			// See if we have this stored with a block type-specific handle
			$blockTypeHandle = $this->getType()->handle . ':' . $handle;

			if(isset($this->_eagerLoadedBlockTypeElements[$blockTypeHandle]))
			{
				return true;
			}
		}

		return parent::hasEagerLoadedElements($handle);
	}

	/**
	 * @inheritDoc BaseElementModel::getEagerLoadedElements()
	 *
	 * @param string $handle
	 * @return BaseElementModel[]|null
	 */
	public function getEagerLoadedElements($handle)
	{
		if($this->getType())
		{
			// See if we have this stored with a block type-specific handle
			$blockTypeHandle = $this->getType()->handle . ':' . $handle;

			if(isset($this->_eagerLoadedBlockTypeElements[$blockTypeHandle]))
			{
				return $this->_eagerLoadedBlockTypeElements[$blockTypeHandle];
			}
		}

		return parent::getEagerLoadedElements($handle);
	}

	/**
	 * @inheritDoc BaseElementModel::setEagerLoadedElements()
	 *
	 * @param string $handle
	 * @param BaseElementModel[] $elements
	 */
	public function setEagerLoadedElements($handle, $elements)
	{
		if($this->getType())
		{
			// See if this was eager-loaded with a block type-specific handle
			$blockTypeHandlePrefix = $this->getType()->handle . ':';

			if(strncmp($handle, $blockTypeHandlePrefix, strlen($blockTypeHandlePrefix)) === 0)
			{
				$this->_eagerLoadedBlockTypeElements[$handle] = $elements;

				return;
			}
		}

		parent::setEagerLoadedElements($handle, $elements);
	}

	/**
	 * @inheritDoc BaseElementModel::getHasFreshContent()
	 *
	 * @return bool
	 */
	public function getHasFreshContent()
	{
		// Defer to the owner element
		$owner = $this->getOwner();

		return $owner ? $owner->getHasFreshContent() : false;
	}

	/**
	 * Returns the block's ancestors.
	 *
	 * @param int|null $dist
	 * @return ElementCriteriaModel
	 */
	public function getAncestors($dist = null)
	{
		// If the request is in Live Preview mode, use the Neo-extended criteria model, which supports Live Preview mode
		if(craft()->neo->isPreviewMode() || $this->isUsingMemoized())
		{
			if(!isset($this->_liveCriteria['ancestors']))
			{
				$criteria = craft()->neo->getCriteria();
				$criteria->useMemoized($this->isUsingMemoized());
				$criteria->setAllElements($this->_allElements);
				$criteria->ancestorOf = $this;

				$this->_liveCriteria['ancestors'] = $criteria;
			}

			if($dist)
			{
				$criteria = $this->_liveCriteria['ancestors']->ancestorDist($dist);
				$criteria->useMemoized($this->isUsingMemoized());
				$criteria->setAllElements($this->_allElements);

				return $criteria;
			}

			return $this->_liveCriteria['ancestors'];
		}

		return parent::getAncestors($dist);
	}

	/**
	 * Returns the block's parent.
	 *
	 * @return Neo_BlockModel|null
	 */
	public function getParent()
	{
		// If the request is in Live Preview mode, use the Neo-extended criteria model, which supports Live Preview mode
		if(craft()->neo->isPreviewMode() || $this->isUsingMemoized())
		{
			if(!isset($this->_liveCriteria['parent']))
			{
				$this->_liveCriteria['parent'] = $this->getAncestors(1)->status(null)->first();
			}

			return $this->_liveCriteria['parent'];
		}

		return parent::getParent();
	}

	/**
	 * Returns the block's descendants.
	 *
	 * @param int|null $dist
	 * @return ElementCriteriaModel
	 */
	public function getDescendants($dist = null)
	{
		// If the request is in Live Preview mode, use the Neo-extended criteria model, which supports Live Preview mode
		if(craft()->neo->isPreviewMode() || $this->isUsingMemoized())
		{
			if(!isset($this->_liveCriteria['descendants']))
			{
				$criteria = craft()->neo->getCriteria();
				$criteria->useMemoized($this->isUsingMemoized());
				$criteria->setAllElements($this->_allElements);
				$criteria->descendantOf = $this;

				$this->_liveCriteria['descendants'] = $criteria;
			}

			if($dist)
			{
				$criteria = $this->_liveCriteria['descendants']->descendantDist($dist);
				$criteria->useMemoized($this->isUsingMemoized());
				$criteria->setAllElements($this->_allElements);

				return $criteria;
			}

			return $this->_liveCriteria['descendants'];
		}

		return parent::getDescendants($dist);
	}

	/**
	 * Returns the block's children.
	 *
	 * @param null $field - This is a deprecated parameter
	 * @return ElementCriteriaModel
	 */
	public function getChildren($field = null)
	{
		// If the request is in Live Preview mode, use the Neo-extended criteria model, which supports Live Preview mode
		if(craft()->neo->isPreviewMode() || $this->isUsingMemoized())
		{
			if(!isset($this->_liveCriteria['children']))
			{
				$this->_liveCriteria['children'] = $this->getDescendants(1);
			}

			return $this->_liveCriteria['children'];
		}

		return parent::getChildren($field);
	}

	/**
	 * Returns the block's siblings.
	 *
	 * @return ElementCriteriaModel
	 */
	public function getSiblings()
	{
		// If the request is in Live Preview mode, use the Neo-extended criteria model, which supports Live Preview mode
		if(craft()->neo->isPreviewMode() || $this->isUsingMemoized())
		{
			if(!isset($this->_liveCriteria['siblings']))
			{
				$criteria = craft()->neo->getCriteria();
				$criteria->useMemoized($this->isUsingMemoized());
				$criteria->setAllElements($this->_allElements);
				$criteria->siblingOf = $this;

				$this->_liveCriteria['siblings'] = $criteria;
			}

			return $this->_liveCriteria['siblings'];
		}

		return parent::getSiblings();
	}

	/**
	 * Returns the block's previous sibling.
	 *
	 * @return Neo_BlockModel|null
	 */
	public function getPrevSibling()
	{
		// If the request is in Live Preview mode, use the Neo-extended criteria model, which supports Live Preview mode
		if(craft()->neo->isPreviewMode() || $this->isUsingMemoized())
		{
			if(!isset($this->_liveCriteria['prevSibling']))
			{
				$criteria = craft()->neo->getCriteria();
				$criteria->useMemoized($this->isUsingMemoized());
				$criteria->setAllElements($this->_allElements);
				$criteria->prevSiblingOf = $this;
				$criteria->status = null;

				$this->_liveCriteria['prevSibling'] = $criteria->first();
			}

			return $this->_liveCriteria['prevSibling'];
		}

		return parent::getPrevSibling();
	}

	/**
	 * Returns the block's next sibling.
	 *
	 * @return Neo_BlockModel|null
	 */
	public function getNextSibling()
	{
		// If the request is in Live Preview mode, use the Neo-extended criteria model, which supports Live Preview mode
		if(craft()->neo->isPreviewMode() || $this->isUsingMemoized())
		{
			if(!isset($this->_liveCriteria['nextSibling']))
			{
				$criteria = craft()->neo->getCriteria();
				$criteria->useMemoized($this->isUsingMemoized());
				$criteria->setAllElements($this->_allElements);
				$criteria->nextSiblingOf = $this;
				$criteria->status = null;

				$this->_liveCriteria['nextSibling'] = $criteria->first();
			}

			return $this->_liveCriteria['nextSibling'];
		}

		return parent::getNextSibling();
	}


	// Protected methods

	protected function defineAttributes()
	{
		return array_merge(parent::defineAttributes(), [
			'fieldId' => AttributeType::Number,
			'ownerId' => AttributeType::Number,
			'ownerLocale' => AttributeType::Locale,
			'typeId' => AttributeType::Number,
			'collapsed' => AttributeType::Bool,
		]);
	}
}
