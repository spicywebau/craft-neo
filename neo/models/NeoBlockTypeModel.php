<?php
namespace Craft;

class NeoBlockTypeModel extends BaseModel
{
	public $hasFieldErrors = false;
	private $_fields;

	public function __toString()
	{
		return $this->handle;
	}

	public function behaviors()
	{
		return array(
			'fieldLayout' => new FieldLayoutBehavior(NeoElementType::NeoBlock),
		);
	}

	public function isNew()
	{
		return (!$this->id || strncmp($this->id, 'new', 3) === 0);
	}

	public function getFields()
	{
		if(!isset($this->_fields))
		{
			$this->_fields = array();

			// Preload all of the fields in this block type's context
			craft()->fields->getAllFields(null, 'global');

			$fieldLayoutFields = $this->getFieldLayout()->getFields();

			foreach($fieldLayoutFields as $fieldLayoutField)
			{
				$field = $fieldLayoutField->getField();
				$field->required = $fieldLayoutField->required;
				$this->_fields[] = $field;
			}
		}

		return $this->_fields;
	}

	public function setFields($fields)
	{
		$this->_fields = $fields;
	}

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
