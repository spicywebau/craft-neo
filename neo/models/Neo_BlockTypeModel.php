<?php
namespace Craft;

class Neo_BlockTypeModel extends BaseModel
{
	public $hasFieldErrors = false;

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

	public function isNew()
	{
		return (!$this->id || strncmp($this->id, 'new', 3) === 0);
	}

	protected function defineAttributes()
	{
		return [
			'id' => AttributeType::Number,
			'fieldId' => AttributeType::Number,
			'fieldLayoutId' => AttributeType::String,
			'name' => AttributeType::String,
			'handle' => AttributeType::String,
			'maxBlocks' => AttributeType::Number,
			'childBlocks' => AttributeType::Mixed,
			'topLevel' => AttributeType::Bool,
			'sortOrder' => AttributeType::Number,
		];
	}
}
