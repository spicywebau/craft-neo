<?php
namespace benf\neo\models;

use craft\base\Model;

class BlockStructure extends Model
{
	public $id;
	public $structureId;
	public $fieldId;
	public $ownerId;
	public $ownerSiteId;

	public function rules()
	{
		return [
			[['id', 'structureId', 'fieldId', 'ownerId', 'ownerSiteId' ], 'number', 'integerOnly' => true],
		];
	}
}
