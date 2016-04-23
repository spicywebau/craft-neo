<?php
namespace Craft;

class Neo_GroupModel extends BaseModel
{
	public function __toString()
	{
		return $this->name;
	}

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
