<?php
namespace Craft;

/**
 * Class Neo_BlockTypeModel
 *
 * @package Craft
 */
class Neo_BlockTypeModel extends BaseModel
{
	// Public properties

	public $hasFieldErrors = false;


	// Public methods

	public function __toString()
	{
		return $this->handle;
	}

	public function behaviors()
	{
		return [
			'fieldLayout' => new FieldLayoutBehavior(Neo_ElementType::NeoBlock),
		];
	}

	/**
	 * Determine if the block is not already saved in the database.
	 *
	 * @return bool
	 */
	public function isNew()
	{
		return (!$this->id || strncmp($this->id, 'new', 3) === 0);
	}


	// Protected methods

	protected function defineAttributes()
	{
		return [
			'id' => AttributeType::Number,
			'dateCreated' => AttributeType::DateTime,
			'dateUpdated' => AttributeType::DateTime,
			'fieldId' => AttributeType::Number,
			'fieldLayoutId' => AttributeType::String,
			'name' => AttributeType::String,
			'handle' => AttributeType::String,
			'maxBlocks' => AttributeType::Number,
			'maxChildBlocks' => AttributeType::Number,
			'childBlocks' => AttributeType::Mixed,
			'topLevel' => AttributeType::Bool,
			'sortOrder' => AttributeType::Number,
		];
	}
}
