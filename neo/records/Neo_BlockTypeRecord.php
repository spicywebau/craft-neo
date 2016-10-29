<?php
namespace Craft;

/**
 * Class Neo_BlockTypeRecord
 *
 * @package Craft
 */
class Neo_BlockTypeRecord extends BaseRecord
{
	// Private properties

	private $_validateUniques = true;


	// Public methods

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

	/**
	 * Returns the validation rules for the record.
	 * Excludes unique validators if flag is set (@link validateUniques).
	 *
	 * @return array
	 */
	public function rules()
	{
		$rules = parent::rules();

		if(!$this->_validateUniques)
		{
			// Remove unique validators from the rule set
			return array_filter($rules, function($rule)
			{
				return $rule[1] != 'Craft\CompositeUniqueValidator';
			});
		}

		return $rules;
	}

	/**
	 * Determines if the values on the record are valid.
	 * Allows option to include or exclude validating unique values.
	 *
	 * @param bool|true $includeUniques
	 * @param array|null $attributes
	 * @param bool|true $clearErrors
	 * @return bool
	 */
	public function validateUniques($includeUniques = true, $attributes = null, $clearErrors = true)
	{
		$this->_validateUniques = $includeUniques;
		$isValid = $this->validate($attributes, $clearErrors);
		$this->_validateUniques = true;

		return $isValid;
	}


	// Protected methods

	protected function defineAttributes()
	{
		return [
			'name' => [AttributeType::Name, 'required' => true],
			'handle' => [AttributeType::Handle, 'required' => true],
			'maxBlocks' => [AttributeType::Number, 'default' => 0],
			'maxChildBlocks' => [AttributeType::Number, 'default' => 0],
			'childBlocks' => AttributeType::Mixed,
			'topLevel' => [AttributeType::Bool, 'default' => true],
			'sortOrder' => AttributeType::SortOrder,
		];
	}
}
