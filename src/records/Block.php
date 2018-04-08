<?php
namespace benf\neo\records;

use yii\db\ActiveQueryInterface;

use craft\db\ActiveRecord;

class Block extends ActiveRecord
{
	public static function tableName(): string
	{
		return '{{%neoblocks}}';
	}

	public function getElement(): ActiveQueryInterface
	{
		return $this->hasOne(Element::class, ['id' => 'id']);
	}

	public function getOwner(): ActiveQueryInterface
	{
		return $this->hasOne(Element::class, ['id' => 'ownerId']);
	}

	public function getOwnerSite(): ActiveQueryInterface
	{
		return $this->hasOne(Element::class, ['id' => 'ownerSiteId']);
	}

	public function getField(): ActiveQueryInterface
	{
		return $this->hasOne(Element::class, ['id' => 'fieldId']);
	}

	public function getType(): ActiveQueryInterface
	{
		return $this->hasOne(Element::class, ['id' => 'typeId']);
	}
}
