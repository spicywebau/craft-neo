<?php
namespace benf\neo\models;

use craft\base\Model;

class BlockTypeGroup extends Model
{
	public $id;
	public $fieldId;
	public $name;
	public $sortOrder;

	public function rules()
	{
		return [
			[['id', 'fieldId', 'sortOrder' ], 'number', 'integerOnly' => true],
		];
	}

	public function __toString(): string
	{
		return (string)$this->name;
	}

	public function getIsNew(): bool
	{
		return (!$this->id || strpos($this->id, 'new') === 0);
	}
}
