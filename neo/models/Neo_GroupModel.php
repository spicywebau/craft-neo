<?php
namespace Craft;

/**
 * Class Neo_GroupModel
 *
 * @package Craft
 */
class Neo_GroupModel extends BaseModel
{
	// Public methods

	public function __toString()
	{
		return $this->name;
	}


	// Protected methods

	protected function defineAttributes()
	{
		return [
			'id' => AttributeType::Number,
			'fieldId' => AttributeType::Number,
			'name' => AttributeType::String,
			'sortOrder' => AttributeType::Number,
		];
	}
}
