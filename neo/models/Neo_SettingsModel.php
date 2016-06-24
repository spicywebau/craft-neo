<?php
namespace Craft;

/**
 * Class Neo_SettingsModel
 *
 * @package Craft
 */
class Neo_SettingsModel extends BaseModel
{
	// Private properties

	private $_neoField;
	private $_blockTypes;
	private $_groups;


	// Public methods

	/**
	 * Returns the field the settings belong to.
	 *
	 * @return FieldModel|null
	 */
	public function getField()
	{
		return $this->_neoField;
	}

	/**
	 * Sets the field the settings belong to.
	 *
	 * @param FieldModel $field
	 */
	public function setField(FieldModel $field = null)
	{
		$this->_neoField = $field;
	}

	/**
	 * Returns the fields' block types.
	 *
	 * @return array
	 */
	public function getBlockTypes()
	{
		if(!isset($this->_blockTypes))
		{
			if($this->_neoField && !empty($this->_neoField->id))
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

	/**
	 * Sets the fields' block types.
	 *
	 * @param $blockTypes
	 */
	public function setBlockTypes($blockTypes)
	{
		$this->_blockTypes = $blockTypes;
	}

	/**
	 * Returns the fields' groups.
	 *
	 * @return array
	 */
	public function getGroups()
	{
		if(!isset($this->_groups))
		{
			if($this->_neoField && !empty($this->_neoField->id))
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

	/**
	 * Sets the fields' groups.
	 *
	 * @param $groups
	 */
	public function setGroups($groups)
	{
		$this->_groups = $groups;
	}

	/**
	 * Validates all the attributes, block types and groups.
	 *
	 * @param array|null $attributes
	 * @param bool|true $clearErrors
	 * @return bool
	 */
	public function validate($attributes = null, $clearErrors = true)
	{
		return (
			parent::validate($attributes, $clearErrors) &&
			craft()->neo->validateFieldSettings($this)
		);
	}


	// Protected methods

	protected function defineAttributes()
	{
		return [
			'maxBlocks' => AttributeType::Number,
		];
	}
}
