<?php
namespace Craft;

class Neo_BlockModel extends BaseElementModel
{
	protected $elementType = Neo_ElementType::NeoBlock;

	private $_owner;

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
