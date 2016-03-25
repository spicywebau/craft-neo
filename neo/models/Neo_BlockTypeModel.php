<?php
namespace Craft;

/**
 * Neo block type model class.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2014, Pixel & Tonic, Inc.
 * @license   http://craftcms.com/license Craft License Agreement
 * @see       http://craftcms.com
 * @package   craft.app.models
 * @since     1.3
 */
class Neo_BlockTypeModel extends BaseModel
{
	// Properties
	// =========================================================================

	/**
	 * @var bool
	 */
	public $hasFieldErrors = false;


	// Public Methods
	// =========================================================================

	/**
	 * Use the block type handle as the string representation.
	 *
	 * @return string
	 */
	public function __toString()
	{
		return $this->handle;
	}

	/**
	 * @return array
	 */
	public function behaviors()
	{
		return array(
			'fieldLayout' => new FieldLayoutBehavior(Neo_ElementType::NeoBlock),
		);
	}

	/**
	 * Returns whether this is a new component.
	 *
	 * @return bool
	 */
	public function isNew()
	{
		return (!$this->id || strncmp($this->id, 'new', 3) === 0);
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
			'id'            => AttributeType::Number,
			'fieldId'       => AttributeType::Number,
			'fieldLayoutId' => AttributeType::String,
			'name'          => AttributeType::String,
			'handle'        => AttributeType::String,
			'sortOrder'     => AttributeType::Number,
		);
	}
}
