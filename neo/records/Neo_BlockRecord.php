<?php
namespace Craft;

/**
 * Class Neo_BlockRecord
 *
 * @package Craft
 */
class Neo_BlockRecord extends BaseRecord
{
	public function getTableName()
	{
		return 'neoblocks';
	}

	public function defineRelations()
	{
		return [
			'element' => [static::BELONGS_TO, 'ElementRecord', 'id',
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
			'type' => [static::BELONGS_TO, 'Neo_BlockTypeRecord',
				'onDelete' => static::CASCADE,
			],
		];
	}

	public function defineIndexes()
	{
		return [
			['columns' => ['ownerId']],
			['columns' => ['fieldId']],
			['columns' => ['typeId']],
			['columns' => ['collapsed']],
		];
	}

	protected function defineAttributes()
	{
		return [
			'collapsed' => AttributeType::Bool,
			'ownerLocale' => AttributeType::Locale,
		];
	}
}
