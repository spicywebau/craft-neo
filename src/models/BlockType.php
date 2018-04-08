<?php
namespace benf\neo\models;

use craft\base\Model;
use craft\behaviors\FieldLayoutBehavior;

use benf\neo\elements\Block;

class BlockType extends Model
{
	public $id;
	public $fieldId;
	public $fieldLayoutId;
	public $name;
	public $handle;
	public $maxBlocks;
	public $maxChildBlocks;
	public $childBlocks;
	public $topLevel = true;
	public $sortOrder;

	public $hasFieldErrors = false;

	public function behaviors()
	{
		return [
			'fieldLayout' => [
				'class' => FieldLayoutBehavior::class,
				'elementType' => Block::class,
			],
		];
	}

	public function rules()
	{
		return [
			[['id', 'fieldId', 'sortOrder' ], 'number', 'integerOnly' => true],
			[['maxBlocks', 'maxChildBlocks'], 'integer', 'min' => 0],
		];
	}

	public function __toString(): string
	{
		return (string)$this->handle;
	}

	public function getIsNew(): bool
	{
		return (!$this->id || strpos($this->id, 'new') === 0);
	}
}
