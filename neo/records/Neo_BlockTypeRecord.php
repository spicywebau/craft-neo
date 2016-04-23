<?php
namespace Craft;

class Neo_BlockTypeRecord extends BaseRecord
{
	public $validateUniques = true;

	public function getTableName()
	{
		return 'neoblocktypes';
	}

	public function defineRelations()
	{
		return [
			'field' => [static::BELONGS_TO, 'FieldRecord',
				'required' => true,
				'onDelete' => static::CASCADE
			],
			'fieldLayout' => [static::BELONGS_TO, 'FieldLayoutRecord',
				'onDelete' => static::SET_NULL
			],
		];
	}

	public function defineIndexes()
	{
		return [
			['columns' => ['name', 'fieldId'], 'unique' => true],
			['columns' => ['handle', 'fieldId'], 'unique' => true],
		];
	}

	public function rules()
	{
		$rules = parent::rules();

		if(!$this->validateUniques)
		{
			foreach($rules as $i => $rule)
			{
				if($rule[1] == 'Craft\CompositeUniqueValidator')
				{
					unset($rules[$i]);
				}
			}
		}

		return $rules;
	}

	protected function defineAttributes()
	{
		return [
			'name' => [AttributeType::Name, 'required' => true],
			'handle' => [AttributeType::Handle, 'required' => true],
			'maxBlocks' => [AttributeType::Number, 'default' => 0],
			'childBlocks' => AttributeType::Mixed,
			'topLevel' => [AttributeType::Bool, 'default' => true],
			'sortOrder' => AttributeType::SortOrder,
		];
	}
}
