<?php
namespace Craft;

/**
 * Class Neo_GroupRecord
 *
 * @package Craft
 */
class Neo_GroupRecord extends BaseRecord
{
	public function getTableName()
	{
		return 'neogroups';
	}

	public function defineRelations()
	{
		return [
			'field' => [static::BELONGS_TO, 'FieldRecord',
				'required' => true,
				'onDelete' => static::CASCADE,
			],
		];
	}

	protected function defineAttributes()
	{
		return [
			'name' => AttributeType::Name,
			'sortOrder' => AttributeType::SortOrder,
		];
	}
}
