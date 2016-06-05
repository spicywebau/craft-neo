<?php
namespace Craft;

class Neo_BlockModel extends BaseElementModel
{
	protected $elementType = Neo_ElementType::NeoBlock;

	private $_owner;
	private $_allElements;
	private $_liveCriteria = [];

	public function getField()
	{
		return craft()->fields->getFieldById($this->fieldId);
	}

	public function getFieldLayout()
	{
		$blockType = $this->getType();
		return $blockType ? $blockType->getFieldLayout() : null;
	}

	public function getLocales()
	{
		if($this->ownerLocale)
		{
			return [$this->ownerLocale];
		}

		$owner = $this->getOwner();

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

		return [craft()->i18n->getPrimarySiteLocaleId()];
	}

	public function getType()
	{
		return $this->typeId ? craft()->neo->getBlockTypeById($this->typeId) : null;
	}

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

	public function setOwner(BaseElementModel $owner)
	{
		$this->_owner = $owner;
	}

	public function setAllElements($elements)
	{
		$this->_allElements = $elements;

		foreach($this->_liveCriteria as $name => $criteria)
		{
			$criteria->setAllElements($this->_allElements);
		}
	}

	public function getAncestors($dist = null)
	{
		if(craft()->request->isLivePreview())
		{
			if(!isset($this->_liveCriteria['ancestors']))
			{
				$criteria = craft()->neo->getCriteria();
				$criteria->setAllElements($this->_allElements);
				$criteria->ancestorOf = $this;

				$this->_liveCriteria['ancestors'] = $criteria;
			}

			if($dist)
			{
				return $this->_liveCriteria['ancestors']->ancestorDist($dist);
			}

			return $this->_liveCriteria['ancestors'];
		}

		return parent::getAncestors($dist);
	}

	public function getParent()
	{
		if(craft()->request->isLivePreview())
		{
			if(!isset($this->_liveCriteria['parent']))
			{
				$this->_liveCriteria['parent'] = $this->getAncestors(1)->status(null)->first();
			}

			return $this->_liveCriteria['parent'];
		}

		return parent::getParent();
	}

	public function getDescendants($dist = null)
	{
		if(craft()->request->isLivePreview())
		{
			if(!isset($this->_liveCriteria['descendants']))
			{
				$criteria = craft()->neo->getCriteria();
				$criteria->setAllElements($this->_allElements);
				$criteria->descendantOf = $this;

				$this->_liveCriteria['descendants'] = $criteria;
			}

			if($dist)
			{
				return $this->_liveCriteria['descendants']->descendantDist($dist);
			}

			return $this->_liveCriteria['descendants'];
		}

		return parent::getDescendants($dist);
	}

	public function getChildren($field = null)
	{
		if(craft()->request->isLivePreview())
		{
			if(!isset($this->_liveCriteria['children']))
			{
				$this->_liveCriteria['children'] = $this->getDescendants(1);
			}

			return $this->_liveCriteria['children'];
		}

		return parent::getChildren($field);
	}

	public function getSiblings()
	{
		if(craft()->request->isLivePreview())
		{
			if(!isset($this->_liveCriteria['siblings']))
			{
				$criteria = craft()->neo->getCriteria();
				$criteria->setAllElements($this->_allElements);
				$criteria->siblingOf = $this;

				$this->_liveCriteria['siblings'] = $criteria;
			}

			return $this->_liveCriteria['siblings'];
		}

		return parent::getSiblings();
	}

	public function getPrevSibling()
	{
		if(craft()->request->isLivePreview())
		{
			if(!isset($this->_liveCriteria['prevSibling']))
			{
				$criteria = craft()->neo->getCriteria();
				$criteria->setAllElements($this->_allElements);
				$criteria->prevSiblingOf = $this;
				$criteria->status = null;

				$this->_liveCriteria['prevSibling'] = $criteria->first();
			}

			return $this->_liveCriteria['prevSibling'];
		}

		return parent::getPrevSibling();
	}

	public function getNextSibling()
	{
		if(craft()->request->isLivePreview())
		{
			if(!isset($this->_liveCriteria['nextSibling']))
			{
				$criteria = craft()->neo->getCriteria();
				$criteria->setAllElements($this->_allElements);
				$criteria->nextSiblingOf = $this;
				$criteria->status = null;

				$this->_liveCriteria['nextSibling'] = $criteria->first();
			}

			return $this->_liveCriteria['nextSibling'];
		}

		return parent::getNextSibling();
	}

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
