<?php
namespace Craft;

/**
 * Neo block model class.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2014, Pixel & Tonic, Inc.
 * @license   http://craftcms.com/license Craft License Agreement
 * @see       http://craftcms.com
 * @package   craft.app.models
 * @since     1.3
 */
class Neo_SettingsModel extends BaseModel
{
	// Properties
	// =========================================================================

	/**
	 * @var FieldModel|null
	 */
	private $_neoField;

	/**
	 * @var
	 */
	private $_blockTypes;
	private $_groups;

	// Public Methods
	// =========================================================================

	/**
	 * Constructor
	 *
	 * @param FieldModel|null $neoField
	 *
	 * @return Neo_SettingsModel
	 */
	public function __construct(FieldModel $neoField = null)
	{
		$this->_neoField = $neoField;
	}

	/**
	 * Returns the field associated with this.
	 *
	 * @return FieldModel
	 */
	public function getField()
	{
		return $this->_neoField;
	}

	/**
	 * Returns the block types.
	 *
	 * @return array
	 */
	public function getBlockTypes()
	{
		if (!isset($this->_blockTypes))
		{
			if (!empty($this->_neoField->id))
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

	/**
	 * Sets the block types.
	 *
	 * @param array $blockTypes
	 *
	 * @return null
	 */
	public function setBlockTypes($blockTypes)
	{
		$this->_blockTypes = $blockTypes;
	}

	/**
	 * Returns the groups.
	 *
	 * @return array
	 */
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
				$this->_groups = array();
			}
		}

		return $this->_groups;
	}

	/**
	 * Sets the groups.
	 *
	 * @param array $groups
	 */
	public function setGroups($groups)
	{
		$this->_groups = $groups;
	}

	/**
	 * Validates all of the attributes for the current Model. Any attributes that fail validation will additionally get
	 * logged to the `craft/storage/runtime/logs` folder with a level of LogLevel::Warning.
	 *
	 * In addition, we validate the block type settings.
	 *
	 * @param array|null $attributes
	 * @param bool       $clearErrors
	 *
	 * @return bool
	 */
	public function validate($attributes = null, $clearErrors = true)
	{
		// Enforce $clearErrors without copying code if we don't have to
		$validates = parent::validate($attributes, $clearErrors);

		if (!craft()->neo->validateFieldSettings($this))
		{
			$validates = false;
		}

		return $validates;
	}

	// Protected Methods
	// =========================================================================

	/**
	 * @inheritDoc BaseModel::defineAttributes()
	 *
	 * @return array
	 */
	protected function defineAttributes()
	{
		return array(
			'maxBlocks' => AttributeType::Number,
		);
	}
}
