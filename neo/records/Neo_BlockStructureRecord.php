<?php
namespace Craft;

class Neo_BlockStructureRecord extends BaseRecord
{
	public function getTableName()
	{
		return 'neoblockstructures';
	}

	public function defineRelations()
	{
		return [
			'structure' => [static::BELONGS_TO, 'StructureRecord',
				'required' => true,
				'onDelete' => static::CASCADE,
			],
			'owner' => [static::BELONGS_TO, 'ElementRecord',
				'required' => true,
				'onDelete' => static::CASCADE,
			],
			'field' => [static::BELONGS_TO, 'FieldRecord',
				'required' => true,
				'onDelete' => static::CASCADE,
			],
		];
	}

	public function defineIndexes()
	{
		return [
			['columns' => ['structureId']],
			['columns' => ['ownerId']],
			['columns' => ['fieldId']],
		];
	}
}
