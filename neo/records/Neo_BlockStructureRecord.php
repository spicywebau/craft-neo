<?php
namespace Craft;

/**
 * Class Neo_BlockStructureRecord
 *
 * @package Craft
 */
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
			'ownerLocale' => [static::BELONGS_TO, 'LocaleRecord', 'ownerLocale',
				'onDelete' => static::CASCADE,
				'onUpdate' => static::CASCADE,
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

	protected function defineAttributes()
	{
		return [
			'ownerLocale' => AttributeType::Locale,
		];
	}
}
