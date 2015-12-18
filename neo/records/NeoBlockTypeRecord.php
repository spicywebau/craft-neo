<?php
namespace Craft;

class NeoBlockTypeRecord extends BaseRecord
{
	public $validateUniques = true;

	public function getTableName()
	{
		return 'neoblocktypes';
	}

	public function defineRelations()
	{
		return array(
			'field'       => array(static::BELONGS_TO, 'FieldRecord', 'required' => true, 'onDelete' => static::CASCADE),
			'fieldLayout' => array(static::BELONGS_TO, 'FieldLayoutRecord', 'onDelete' => static::SET_NULL),
		);
	}

	public function defineIndexes()
	{
		return array(
			array('columns' => array('name', 'fieldId'), 'unique' => true),
			array('columns' => array('handle', 'fieldId'), 'unique' => true),
		);
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
		return array(
			'name'       => array(AttributeType::Name, 'required' => true),
			'handle'     => array(AttributeType::Handle, 'required' => true),
			'sortOrder'  => AttributeType::SortOrder,
		);
	}
}
