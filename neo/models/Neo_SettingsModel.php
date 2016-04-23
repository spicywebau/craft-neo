<?php
namespace Craft;

class Neo_SettingsModel extends BaseModel
{
	private $_neoField;
	private $_blockTypes;
	private $_groups;

	public function __construct(FieldModel $neoField = null)
	{
		$this->_neoField = $neoField;
	}

	public function getField()
	{
		return $this->_neoField;
	}

	public function getBlockTypes()
	{
		if(!isset($this->_blockTypes))
		{
			if(!empty($this->_neoField->id))
			{
				$this->_blockTypes = craft()->neo->getBlockTypesByFieldId($this->_neoField->id);
			}
			else
			{
				$this->_blockTypes = [];
			}
		}

		return $this->_blockTypes;
	}

	public function setBlockTypes($blockTypes)
	{
		$this->_blockTypes = $blockTypes;
	}

	public function getGroups()
	{
		if(!isset($this->_groups))
		{
			if(!empty($this->_neoField->id))
			{
				$this->_groups = craft()->neo->getGroupsByFieldId($this->_neoField->id);
			}
			else
			{
				$this->_groups = [];
			}
		}

		return $this->_groups;
	}

	public function setGroups($groups)
	{
		$this->_groups = $groups;
	}

	public function validate($attributes = null, $clearErrors = true)
	{
		return (
			parent::validate($attributes, $clearErrors) &&
			craft()->neo->validateFieldSettings($this)
		);
	}

	protected function defineAttributes()
	{
		return [
			'maxBlocks' => AttributeType::Number,
		];
	}
}
