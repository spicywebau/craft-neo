<?php
namespace benf\neo\records;

use yii\db\ActiveQueryInterface;

use craft\db\ActiveRecord;
use craft\validators\HandleValidator;

class BlockType extends ActiveRecord
{
	public static function tableName(): string
	{
		return '{{%neoblocktypes}}';
	}

	public $validateUniques = true;

	public function rules()
	{
		return [
			[['handle'], 'unique', 'targetAttribute' => ['handle', 'fieldId']],
			[['name', 'handle'], 'required'],
			[['name', 'handle'], 'string', 'max' => 255],
			[
				['handle'],
				HandleValidator::class,
				'reservedWords' => [
					'id',
					'dateCreated',
					'dateUpdated',
					'uid',
					'title',
				],
			],
		];
	}

	public function getField(): ActiveQueryInterface
	{
		return $this->hasOne(Field::class, ['id' => 'fieldId']);
	}

	public function getFieldLayout(): ActiveQueryInterface
	{
		return $this->hasOne(FieldLayout::class, ['id' => 'fieldLayoutId']);
	}
}
