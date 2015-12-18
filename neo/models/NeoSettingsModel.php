<?php
namespace Craft;

class NeoSettingsModel extends BaseModel
{
	private $_neoField;
	private $_blockTypes;

	public function __construct(FieldModel $neoField = null)
	{
		// parent::__construct();

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
				$this->_blockTypes = array();
			}
		}

		return $this->_blockTypes;
	}

	public function setBlockTypes($blockTypes)
	{
		$this->_blockTypes = $blockTypes;
	}

	public function validate($attributes = null, $clearErrors = true)
	{
		// Enforce $clearErrors without copying code if we don't have to
		$validates = parent::validate($attributes, $clearErrors);

		if(!craft()->neo->validateFieldSettings($this))
		{
			$validates = false;
		}

		return $validates;
	}

	protected function defineAttributes()
	{
		return array(
			'maxBlocks' => AttributeType::Number,
		);
	}
}
