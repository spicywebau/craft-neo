<?php
namespace benf\neo\records;

use yii\db\ActiveQueryInterface;

use craft\db\ActiveRecord;

class BlockTypeGroup extends ActiveRecord
{
	public static function tableName(): string
	{
		return '{{%neoblocktypegroups}}';
	}

	public function getField(): ActiveQueryInterface
	{
		return $this->hasOne(Field::class, [ 'id' => 'fieldId' ]);
	}
}
