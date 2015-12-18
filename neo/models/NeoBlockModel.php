<?php
namespace Craft;

class NeoBlockModel extends BaseElementModel
{
	protected $elementType = NeoElementType::NeoBlock;
	private $_owner;

	public function getFieldLayout()
	{
		$blockType = $this->getType();

		if($blockType)
		{
			return $blockType->getFieldLayout();
		}

		return null;
	}

	public function getLocales()
	{
		// If the Neo field is translatable, than each individual block is tied to a single locale, and thus aren't
		// translatable. Otherwise all blocks belong to all locales, and their content is translatable.

		if($this->ownerLocale)
		{
			return array($this->ownerLocale);
		}

		$owner = $this->getOwner();

		if($owner)
		{
			// Just send back an array of locale IDs -- don't pass along enabledByDefault configs
			$localeIds = array();

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

		return array(craft()->i18n->getPrimarySiteLocaleId());
	}

	public function getType()
	{
		if($this->typeId)
		{
			return craft()->neo->getBlockTypeById($this->typeId);
		}

		return null;
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

		if($this->_owner)
		{
			return $this->_owner;
		}

		return null;
	}

	public function setOwner(BaseElementModel $owner)
	{
		$this->_owner = $owner;
	}

	public function getContentTable()
	{
		return craft()->neo->getContentTableName($this->_getField());
	}

	public function getFieldColumnPrefix()
	{
		return 'field_' . $this->getType()->handle . '_';
	}

	public function getFieldContext()
	{
		return 'global';
	}

	protected function defineAttributes()
	{
		return array_merge(parent::defineAttributes(), array(
			'fieldId'     => AttributeType::Number,
			'ownerId'     => AttributeType::Number,
			'ownerLocale' => AttributeType::Locale,
			'typeId'      => AttributeType::Number,
			'sortOrder'   => AttributeType::Number,
			'collapsed'   => AttributeType::Bool,
		));
	}

	private function _getField()
	{
		return craft()->fields->getFieldById($this->fieldId);
	}
}
