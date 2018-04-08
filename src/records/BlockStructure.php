<?php
namespace benf\neo\records;

use yii\db\ActiveQueryInterface;

use craft\db\ActiveRecord;

class BlockStructure extends ActiveRecord
{
	public static function tableName(): string
	{
		return '{{%neoblockstructures}}';
	}

	public function getOwner(): ActiveQueryInterface
	{
		return $this->hasOne(Element::class, [ 'id' => 'ownerId' ]);
	}

	public function getOwnerSite(): ActiveQueryInterface
	{
		return $this->hasOne(Element::class, [ 'id' => 'ownerSiteId' ]);
	}

	public function getField(): ActiveQueryInterface
	{
		return $this->hasOne(Field::class, [ 'id' => 'fieldId' ]);
	}

	public function getStructure(): ActiveQueryInterface
	{
		return $this->hasOne(Structure::class, [ 'id' => 'structureId' ]);
	}
}
