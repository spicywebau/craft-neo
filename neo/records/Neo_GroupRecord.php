<?php
namespace Craft;

class Neo_GroupRecord extends BaseRecord
{
	public function getTableName()
	{
		return 'neogroups';
	}

	public function defineRelations()
	{
		return array(
			'field' => array(static::BELONGS_TO, 'FieldRecord', 'required' => true, 'onDelete' => static::CASCADE),
		);
	}

	protected function defineAttributes()
	{
		return array(
			'name'      => AttributeType::Name,
			'sortOrder' => AttributeType::SortOrder,
		);
	}
}
